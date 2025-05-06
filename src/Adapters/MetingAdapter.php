<?php
// 严格类型声明
declare(strict_types=1);

// 定义命名空间
namespace GDApi\Adapters;

// 引入依赖：接口、枚举和遗留的 Meting 类
use GDApi\Interfaces\MusicSourceAdapterInterface;
use GDApi\Enums\MusicSource;
use Metowolf\Meting as LegacyMeting; // 使用 'as' 为遗留类创建别名，避免命名冲突
use Exception; // 引入 PHP 内建的 Exception 类

/**
 * Meting 音乐源适配器
 * Class MetingAdapter
 *
 * 实现了 MusicSourceAdapterInterface 接口。
 * 这是一个适配器模式的应用，它封装了对遗留的 Metowolf\Meting 库的调用，
 * 使其能够被现代化的应用程序（如 ApiHandler）透明地使用。
 * @package GDApi\Adapters
 */
class MetingAdapter implements MusicSourceAdapterInterface
{
    // 定义一个只读属性来持有遗留 Meting 库的实例
    // 使用 PHP 8.1 的 readonly 属性确保该实例在构造后不会被修改
    private readonly LegacyMeting $meting;

    /**
     * 构造函数
     *
     * @param MusicSource $source 要使用的音乐源枚举实例。
     * @throws Exception 如果无法实例化遗留 Meting 类（例如，文件调整不正确）。
     */
    public function __construct(MusicSource $source)
    {
        try {
            // 实例化遗留的 Meting 类，传入音乐源的字符串值
            $this->meting = new LegacyMeting($source->value);
            // 配置 Meting 实例返回格式化的 JSON 字符串
            $this->meting->format(true);
        } catch (\Throwable $e) {
            // 如果实例化失败，抛出异常，以便上层捕获和处理
            // 注意：需要确保 Legacy Meting 类能够被正确自动加载
            throw new Exception("无法初始化 Meting 适配器源 '{$source->value}': " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 搜索音乐。
     * 调用遗留 Meting 库的 search 方法。
     *
     * @param string $keyword 搜索关键词。
     * @param int $page 页码。
     * @param int $limit 每页数量。
     * @return string 返回 Meting 库生成的 JSON 字符串。
     * @throws Exception 如果 Meting 库调用过程中发生错误。
     */
    public function search(string $keyword, int $page = 1, int $limit = 20): string
    {
        // 调用遗留库的方法，并传递必要的选项
        // 注意：我们信任 Meting::search 会返回 JSON 字符串或在严重错误时可能抛出异常（尽管原始代码不常用异常）
        // 可以在这里添加 try-catch 并包装 Meting 可能产生的错误，但目前直接返回其结果
        $result = $this->meting->search($keyword, ['page' => $page, 'limit' => $limit]);
        // 对返回结果进行基本检查（例如，是否为非空字符串）
        if (!is_string($result) || empty($result)) {
            // 如果结果无效，可以记录日志或抛出特定异常
             // 为了简单起见，返回一个表示错误的 JSON 字符串
             return json_encode(['error' => 'Meting search returned invalid data.']);
        }
        // 返回 Meting 生成的 JSON 字符串
        return $result;
    }

    /**
     * 获取歌曲播放链接。
     * 调用遗留 Meting 库的 url 方法。
     *
     * @param string $songId 歌曲 ID。
     * @param int $bitrate 音质码率。
     * @return string 返回 Meting 库生成的 JSON 字符串。
     * @throws Exception 如果 Meting 库调用过程中发生错误。
     */
    public function getSongUrl(string $songId, int $bitrate): string
    {
        // 调用遗留库的 url 方法
        $result = $this->meting->url($songId, $bitrate);
        // 基本检查
        if (!is_string($result) || empty($result)) {
             return json_encode(['error' => 'Meting url returned invalid data.']);
        }
        // 返回 JSON 字符串
        return $result;
    }

    /**
     * 获取专辑封面图片链接。
     * 调用遗留 Meting 库的 pic 方法。
     *
     * @param string $picId 图片 ID。
     * @param int $size 图片尺寸。
     * @return string 返回 Meting 库生成的 JSON 字符串。
     * @throws Exception 如果 Meting 库调用过程中发生错误。
     */
    public function getAlbumArtUrl(string $picId, int $size): string
    {
        // 调用遗留库的 pic 方法
        $result = $this->meting->pic($picId, $size);
        // 基本检查
         if (!is_string($result) || empty($result)) {
             return json_encode(['error' => 'Meting pic returned invalid data.']);
         }
        // 返回 JSON 字符串
        return $result;
    }

    /**
     * 获取歌词。
     * 调用遗留 Meting 库的 lyric 方法。
     *
     * @param string $lyricId 歌词 ID。
     * @return string 返回 Meting 库生成的 JSON 字符串。
     * @throws Exception 如果 Meting 库调用过程中发生错误。
     */
    public function getLyrics(string $lyricId): string
    {
        // 调用遗留库的 lyric 方法
        $result = $this->meting->lyric($lyricId);
         // 基本检查
         if (!is_string($result) || empty($result)) {
             return json_encode(['error' => 'Meting lyric returned invalid data.']);
         }
        // 返回 JSON 字符串
        return $result;
    }

    // --- 如果需要实现接口中注释掉的方法 (getSongDetails, getAlbumSongs), 在这里添加 ---
    // public function getSongDetails(string $songId): string
    // {
    //     $result = $this->meting->song($songId);
    //     // ... 检查和返回 ...
    // }
    // public function getAlbumSongs(string $albumId): string
    // {
    //     $result = $this->meting->album($albumId);
    //     // ... 检查和返回 ...
    // }
}
