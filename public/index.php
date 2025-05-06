<?php
// 严格类型声明
declare(strict_types=1);

// --- 1. 启动与自动加载 ---
// 记录开始时间（用于调试或性能监控）
$startTime = microtime(true);

// 引入 Composer 生成的自动加载文件
// __DIR__ 指向当前文件所在目录 (public)，所以需要向上两级找到项目根目录下的 vendor
$autoloaderPath = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoloaderPath)) {
    // 如果找不到 autoload 文件，直接输出错误并退出
    // （这种情况通常意味着 composer install 没有运行）
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Internal Server Error: Autoloader not found. Please run "composer install".']);
    exit;
}
require_once $autoloaderPath;

// --- 2. 引入必要的类 ---
use GDApi\Enums\ApiType;
use GDApi\Enums\MusicSource;
use GDApi\Adapters\MetingAdapter;
use GDApi\Handler\ApiHandler;
use GDApi\Http\JsonResponse;
use GDApi\Interfaces\MusicSourceAdapterInterface; // 引入接口

// --- 3. 全局错误与异常处理 ---
// 设置错误报告级别（开发环境显示所有错误，生产环境应记录错误日志）
error_reporting(E_ALL);
ini_set('display_errors', '0'); // 生产环境关闭屏幕错误显示
ini_set('log_errors', '1'); // 生产环境启用错误日志记录
// ini_set('error_log', '/path/to/your/php-error.log'); // 指定错误日志文件路径

// 设置全局异常处理器
set_exception_handler(function (Throwable $exception) {
    // 在生产环境中，应该记录详细的异常信息到日志文件
    // error_log("Uncaught Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());

    // 向客户端发送通用的错误响应
    JsonResponse::error(
        'Internal Server Error', // 通用错误消息
        500 // HTTP 状态码 500
        // 可选：在开发模式下可以暴露更多错误细节，但生产环境不应暴露
        // , ['type' => get_class($exception), 'message' => $exception->getMessage()] // 开发模式下的详情
    )->send();
});

// 设置全局错误处理器 (将 PHP 错误转换为异常，以便被异常处理器捕获)
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    // 如果错误报告级别被抑制了 (@)，则不处理
    if (!(error_reporting() & $severity)) {
        return false;
    }
    // 将 PHP 错误抛出为 ErrorException
    throw new ErrorException($message, 0, $severity, $file, $line);
});


// --- 4. 输入参数解析与验证 ---
// 使用 null 合并运算符获取 GET 参数，提供 null 作为默认值
$rawType = $_GET['types'] ?? null;
$rawSource = $_GET['source'] ?? null;
$rawName = $_GET['name'] ?? null;
$rawId = $_GET['id'] ?? null;
$rawCount = $_GET['count'] ?? null;
$rawPage = $_GET['pages'] ?? null;
$rawBr = $_GET['br'] ?? null;
$rawSize = $_GET['size'] ?? null;

// 验证 'types' 参数
$apiType = ApiType::tryFrom($rawType);
if ($apiType === null) {
    JsonResponse::error('Invalid or missing "types" parameter.', 400)->send();
}

// 解析 'source' 参数 (枚举会自动处理默认值)
$musicSource = MusicSource::fromInput($rawSource);

// 验证其他参数 (基本类型和范围)
// 注意：更复杂的验证（如 ID 格式）可以在 Handler 或 Adapter 中进行
$params = [];
// 搜索关键词 (字符串)
$params['name'] = is_string($rawName) ? trim($rawName) : null; // trim 去除首尾空格
// ID (字符串)
$params['id'] = is_string($rawId) ? trim($rawId) : null;
// 数量 (整数, >= 1)
$params['limit'] = filter_var($rawCount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$params['limit'] = $params['limit'] === false ? 20 : $params['limit']; // 无效或小于1时使用默认值 20
// 页码 (整数, >= 1)
$params['page'] = filter_var($rawPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$params['page'] = $params['page'] === false ? 1 : $params['page']; // 无效或小于1时使用默认值 1
// 音质 (整数)
$brInt = filter_var($rawBr, FILTER_VALIDATE_INT);
$params['br'] = $brInt !== false && in_array($brInt, [128, 192, 320, 999]) ? $brInt : 320; // 验证是否在允许列表内，否则默认 320
// 图片尺寸 (整数)
$sizeInt = filter_var($rawSize, FILTER_VALIDATE_INT);
$params['size'] = $sizeInt !== false && in_array($sizeInt, [300, 400, 500]) ? $sizeInt : 300; // 验证是否在允许列表内，否则默认 300

// --- 5. 依赖注入与处理器实例化 ---
// 根据验证后的 $musicSource 实例化对应的适配器
// 目前只有一个适配器 MetingAdapter
// 如果未来有更多适配器，这里可以使用工厂模式或简单的 switch/match
$adapter = null;
try {
    // 这里我们直接实例化，因为 MusicSource 枚举保证了值的有效性
    $adapter = new MetingAdapter($musicSource);
} catch (Exception $e) {
    // 如果适配器初始化失败（例如，旧版 Meting 类有问题）
    // error_log("Adapter initialization failed: " . $e->getMessage()); // 记录错误
    JsonResponse::error('Failed to initialize music source adapter.', 500)->send();
}

// 确保 $adapter 确实是 MusicSourceAdapterInterface 的实例 (类型安全)
if (!$adapter instanceof MusicSourceAdapterInterface) {
     JsonResponse::error('Internal configuration error: Invalid adapter.', 500)->send();
}

// 实例化 API 处理器，并将适配器注入
$handler = new ApiHandler($adapter);

// --- 6. 处理请求 ---
try {
    // 调用处理器方法，传入 API 类型和已验证的参数
    $resultJsonString = $handler->handleRequest($apiType, $params);

    // 使用 JsonResponse 发送成功响应，直接传递从 Adapter 获取的 JSON 字符串
    JsonResponse::success($resultJsonString)->send();

} catch (InvalidArgumentException $e) {
    // 捕获由 Handler 抛出的参数验证异常
    JsonResponse::error($e->getMessage(), 400)->send(); // 400 Bad Request
} catch (Throwable $e) {
    // 捕获其他在处理过程中可能发生的异常（例如 Adapter 内部错误）
    // error_log("Request handling error: " . $e->getMessage()); // 记录错误
    JsonResponse::error('An error occurred while processing your request.', 500)->send(); // 500 Internal Server Error
}

// --- 7. (可选) 清理与收尾 ---
// 例如，记录执行时间
$endTime = microtime(true);
$executionTime = $endTime - $startTime;
// error_log("Request processed in " . $executionTime . " seconds.");

// 注意：JsonResponse->send() 之后代码不会执行
