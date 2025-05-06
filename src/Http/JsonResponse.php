<?php
// 严格类型声明
declare(strict_types=1);

// 定义命名空间
namespace GDApi\Http;

/**
 * JSON 响应类
 * Class JsonResponse
 *
 * 用于封装和发送 JSON 格式的 HTTP 响应。
 * 简化了设置响应头和编码 JSON 数据的过程。
 * @package GDApi\Http
 */
class JsonResponse
{
    // 使用 PHP 8.1 的构造函数属性提升和 readonly 属性
    // $data 可以是任何可被 json_encode 处理的数据，或者是一个预先格式化好的 JSON 字符串
    // $status 是 HTTP 状态码
    // $headers 是一个包含额外 HTTP 响应头的关联数组
    public function __construct(
        private readonly mixed $data,
        private readonly int $status = 200, // 默认 HTTP 状态码为 200 OK
        private readonly array $headers = [] // 默认没有额外的头信息
    ) {}

    /**
     * 发送 JSON 响应。
     *
     * 设置 HTTP 状态码和头信息，然后输出 JSON 编码后的数据。
     * 此方法执行后会终止脚本执行。
     *
     * @return never 此方法标记为 never，表示它不会正常返回，因为它会终止脚本。
     */
    public function send(): never // 使用 PHP 8.1 的 never 返回类型
    {
        // 设置 HTTP 状态码
        http_response_code($this->status);

        // 设置基本的 JSON Content-Type 头和允许跨域请求的头（生产环境应谨慎配置）
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *'); // 允许所有来源的跨域请求
        header('Access-Control-Allow-Methods: GET, OPTIONS'); // 允许的方法
        // 如果需要处理预检请求 (OPTIONS)，可能需要添加更多头信息
        // header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // 设置构造函数中传入的额外头信息
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        // 判断 $data 是否已经是 JSON 字符串
        if (is_string($this->data) && $this->isJson($this->data)) {
            // 如果是有效的 JSON 字符串，直接输出
            echo $this->data;
        } else {
            // 否则，对 $data 进行 JSON 编码
            // 使用 flag 避免斜杠被转义，并保持 Unicode 字符
            // JSON_PRETTY_PRINT 用于开发环境方便阅读，生产环境可以移除以节省带宽
            echo json_encode(
                $this->data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            );
        }

        // 终止脚本执行
        exit;
    }

    /**
     * 检查一个字符串是否是有效的 JSON。
     *
     * @param string $string 要检查的字符串。
     * @return bool 如果是有效 JSON 返回 true，否则返回 false。
     */
    private function isJson(string $string): bool
    {
        // 尝试解码 JSON
        json_decode($string);
        // 检查 json_last_error() 是否报告了错误
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * 创建一个表示成功的 JSON 响应。
     * (静态工厂方法)
     *
     * @param mixed $data 要包含在响应中的数据。
     * @param int $status HTTP 状态码 (默认为 200)。
     * @param array $headers 额外的 HTTP 头。
     * @return self 返回一个新的 JsonResponse 实例。
     */
    public static function success(mixed $data, int $status = 200, array $headers = []): self
    {
        // 直接返回包含数据的响应实例
        return new self($data, $status, $headers);
    }

    /**
     * 创建一个表示错误的 JSON 响应。
     * (静态工厂方法)
     *
     * @param string $errorMessage 错误消息。
     * @param int $status HTTP 状态码 (例如 400, 404, 500)。
     * @param mixed|null $errorDetails (可选) 额外的错误详情。
     * @param array $headers 额外的 HTTP 头。
     * @return self 返回一个新的 JsonResponse 实例，包含标准化的错误结构。
     */
    public static function error(string $errorMessage, int $status, mixed $errorDetails = null, array $headers = []): self
    {
        // 构建标准化的错误响应体
        $errorData = ['error' => $errorMessage];
        if ($errorDetails !== null) {
            $errorData['details'] = $errorDetails;
        }
        // 返回包含错误信息的响应实例
        return new self($errorData, $status, $headers);
    }
}
