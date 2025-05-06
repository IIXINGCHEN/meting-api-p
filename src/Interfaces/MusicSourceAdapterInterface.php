<?php
// 使用严格类型模式，确保类型安全
declare(strict_types=1);

// 定义命名空间，遵循 PSR-4 标准
namespace GDApi\Interfaces;

/**
 * 音乐源适配器接口
 * Interface MusicSourceAdapterInterface
 *
 * 定义了所有音乐源适配器必须实现的方法契约。
 * 这确保了无论底层音乐源如何变化（例如，从 Meting 切换到其他库），
 * 只要新的适配器实现了这个接口，上层代码（如 ApiHandler）无需修改即可工作。
 * @package GDApi\Interfaces
 */
interface MusicSourceAdapterInterface
{
    /**
     * 搜索音乐。
     *
     * @param string $keyword 搜索关键词。
     * @param int $page 页码，默认为 1。
     * @param int $limit 每页数量，默认为 20。
     * @return string 返回包含搜索结果的 JSON 字符串。如果失败，应包含错误信息。
     */
    public function search(string $keyword, int $page = 1, int $limit = 20): string;

    /**
     * 获取歌曲播放链接。
     *
     * @param string $songId 歌曲 ID（具体格式取决于音乐源）。
     * @param int $bitrate 音质码率 (例如 128, 320, 999 表示无损)。
     * @return string 返回包含歌曲链接、实际音质、大小等信息的 JSON 字符串。
     */
    public function getSongUrl(string $songId, int $bitrate): string;

    /**
     * 获取专辑封面图片链接。
     *
     * @param string $picId 图片 ID（通常来自搜索结果，格式取决于音乐源）。
     * @param int $size 图片尺寸 (例如 300, 500)。
     * @return string 返回包含图片 URL 的 JSON 字符串。
     */
    public function getAlbumArtUrl(string $picId, int $size): string;

    /**
     * 获取歌词。
     *
     * @param string $lyricId 歌词 ID（通常等于歌曲 ID，格式取决于音乐源）。
     * @return string 返回包含 lrc 歌词和翻译歌词（如果可用）的 JSON 字符串。
     */
    public function getLyrics(string $lyricId): string;

    /**
     * 获取歌曲详细信息 (如果 Meting 库支持且需要)。
     * 注意：原始 Meting v1.5.20 有 song() 方法，但未在 api.php 中使用。
     * 如果需要此功能，可以在此接口中添加。
     *
     * @param string $songId 歌曲 ID。
     * @return string 返回包含歌曲详细信息的 JSON 字符串。
     */
    // public function getSongDetails(string $songId): string;

    /**
     * 获取专辑歌曲列表 (如果 Meting 库支持且需要)。
     * 注意：原始 Meting v1.5.20 有 album() 方法，但未在 api.php 中使用。
     * 如果需要此功能，可以在此接口中添加。
     *
     * @param string $albumId 专辑 ID。
     * @return string 返回包含专辑曲目列表的 JSON 字符串。
     */
    // public function getAlbumSongs(string $albumId): string;
}
