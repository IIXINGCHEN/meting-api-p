<?php
// 严格类型声明
declare(strict_types=1);

// 定义命名空间
namespace GDApi\Handler;

// 引入依赖：接口和枚举
use GDApi\Interfaces\MusicSourceAdapterInterface;
use GDApi\Enums\ApiType;
use InvalidArgumentException; // 引入参数无效异常

/**
 * API 请求处理器
 * Class ApiHandler
 *
 * 负责根据 API 请求类型调用相应的音乐源适配器方法。
 * 它通过依赖注入接收音乐源适配器实例，实现了与具体数据源的解耦。
 * @package GDApi\Handler
 */
class ApiHandler
{
    /**
     * 构造函数属性提升 (PHP 8.0+)
     * 声明一个私有的、只读的音乐源适配器实例。
     *
     * @param MusicSourceAdapterInterface $musicSourceAdapter 实现了音乐源接口的适配器实例。
     */
    public function __construct(
        private readonly MusicSourceAdapterInterface $musicSourceAdapter
    ) {}

    /**
     * 处理 API 请求。
     *
     * 根据请求类型和参数，调用适配器的相应方法。
     *
     * @param ApiType $type API 请求类型枚举实例。
     * @param array $params 包含所有已验证和处理过的请求参数的关联数组。
     * @return string 返回从适配器获取的 JSON 字符串结果。
     * @throws InvalidArgumentException 如果缺少所需参数。
     * @throws \Exception 如果适配器方法执行失败。
     */
    public function handleRequest(ApiType $type, array $params): string
    {
        // 使用 PHP 8.0 的 match 表达式进行类型分发，比 switch 更简洁、类型安全
        return match ($type) {
            // 处理搜索请求
            ApiType::SEARCH => $this->handleSearch($params),
            // 处理获取 URL 请求
            ApiType::URL    => $this->handleUrl($params),
            // 处理获取图片请求
            ApiType::PIC    => $this->handlePic($params),
            // 处理获取歌词请求
            ApiType::LYRIC  => $this->handleLyric($params),
            // 默认情况（理论上不应发生，因为类型已在入口点验证）
            // default => throw new InvalidArgumentException("不支持的 API 类型: " . $type->value),
        };
    }

    /**
     * 处理搜索请求的私有方法。
     *
     * @param array $params 请求参数。
     * @return string JSON 结果。
     * @throws InvalidArgumentException 如果缺少 'name' 参数。
     */
    private function handleSearch(array $params): string
    {
        // 检查必需的 'name' 参数是否存在且不为空
        if (empty($params['name'])) {
            throw new InvalidArgumentException("搜索请求缺少必需的 'name' 参数。");
        }
        // 调用适配器的 search 方法，使用 null 合并运算符 (??) 提供默认值
        return $this->musicSourceAdapter->search(
            $params['name'],
            $params['page'] ?? 1, // 如果 $params['page'] 不存在或为 null，则使用 1
            $params['limit'] ?? 20 // 如果 $params['limit'] 不存在或为 null，则使用 20
        );
    }

    /**
     * 处理获取 URL 请求的私有方法。
     *
     * @param array $params 请求参数。
     * @return string JSON 结果。
     * @throws InvalidArgumentException 如果缺少 'id' 参数。
     */
    private function handleUrl(array $params): string
    {
        // 检查必需的 'id' 参数
        if (empty($params['id'])) {
            throw new InvalidArgumentException("获取 URL 请求缺少必需的 'id' 参数。");
        }
        // 调用适配器的 getSongUrl 方法，提供默认音质
        return $this->musicSourceAdapter->getSongUrl(
            $params['id'],
            $params['br'] ?? 320 // 如果 $params['br'] 不存在或为 null，则使用 320
        );
    }

    /**
     * 处理获取图片请求的私有方法。
     *
     * @param array $params 请求参数。
     * @return string JSON 结果。
     * @throws InvalidArgumentException 如果缺少 'id' 参数。
     */
    private function handlePic(array $params): string
    {
        // 检查必需的 'id' 参数
        if (empty($params['id'])) {
            throw new InvalidArgumentException("获取图片请求缺少必需的 'id' 参数。");
        }
        // 调用适配器的 getAlbumArtUrl 方法，提供默认尺寸
        return $this->musicSourceAdapter->getAlbumArtUrl(
            $params['id'],
            $params['size'] ?? 300 // 如果 $params['size'] 不存在或为 null，则使用 300
        );
    }

    /**
     * 处理获取歌词请求的私有方法。
     *
     * @param array $params 请求参数。
     * @return string JSON 结果。
     * @throws InvalidArgumentException 如果缺少 'id' 参数。
     */
    private function handleLyric(array $params): string
    {
        // 检查必需的 'id' 参数
        if (empty($params['id'])) {
            throw new InvalidArgumentException("获取歌词请求缺少必需的 'id' 参数。");
        }
        // 调用适配器的 getLyrics 方法
        return $this->musicSourceAdapter->getLyrics($params['id']);
    }
}
