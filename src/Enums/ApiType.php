<?php
// 严格类型声明
declare(strict_types=1);

// 定义命名空间
namespace GDApi\Enums;

/**
 * API 请求类型枚举
 * Enum ApiType
 *
 * 使用 PHP 8.1 的枚举类型来表示和验证 API 请求的类型 ('types' 参数)。
 * 这比使用字符串常量更安全、更清晰。
 * @package GDApi\Enums
 */
enum ApiType: string
{
    // 定义搜索类型
    case SEARCH = 'search';
    // 定义获取 URL 类型
    case URL = 'url';
    // 定义获取图片类型
    case PIC = 'pic';
    // 定义获取歌词类型
    case LYRIC = 'lyric';
    // 如果需要，可以添加 SONG, ALBUM, PLAYLIST 等

    /**
     * 尝试从字符串值创建 ApiType 枚举实例。
     *
     * @param string|null $value 输入的字符串值 (来自 $_GET['types'])。
     * @return self|null 如果值有效则返回对应的枚举实例，否则返回 null。
     */
    public static functiontryFrom(?string $value): ?self
    {
        // 如果输入值为 null 或空字符串，直接返回 null
        if ($value === null || $value === '') {
            return null;
        }
        // 遍历枚举的所有 case
        foreach (self::cases() as $case) {
            // 如果输入值（转为小写）与某个 case 的值匹配
            if (strtolower($value) === $case->value) {
                // 返回该 case 实例
                return $case;
            }
        }
        // 如果没有找到匹配的 case，返回 null
        return null;
    }
}
