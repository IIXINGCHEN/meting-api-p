<?php
// 严格类型声明
declare(strict_types=1);

// 定义命名空间
namespace GDApi\Enums;

/**
 * 音乐源枚举
 * Enum MusicSource
 *
 * 使用 PHP 8.1 的枚举类型表示支持的音乐源 ('source' 参数)。
 * 这里的列表基于提供的 Meting v1.5.20 代码确定。
 * @package GDApi\Enums
 */
enum MusicSource: string
{
    // 网易云音乐
    case NETEASE = 'netease';
    // QQ 音乐
    case TENCENT = 'tencent';
    // 酷狗音乐
    case KUGOU = 'kugou';
    // 酷我音乐
    case KUWO = 'kuwo';

    // 默认音乐源
    public const DEFAULT = self::NETEASE;

    /**
     * 尝试从字符串值创建 MusicSource 枚举实例。
     *
     * @param string|null $value 输入的字符串值 (来自 $_GET['source'])。
     * @return self 如果值有效则返回对应的枚举实例，如果无效或为空则返回默认值。
     */
    public static function fromInput(?string $value): self
    {
        // 如果输入值为 null 或空字符串，返回默认源
        if ($value === null || $value === '') {
            return self::DEFAULT;
        }
        // 尝试使用 PHP 8.1 的 Enum::tryFrom 方法精确匹配值
        // (注意：这里需要输入值大小写与 case 值完全一致)
        // 为了更灵活，我们使用自定义查找（忽略大小写）
        $lowerValue = strtolower($value);
        foreach (self::cases() as $case) {
            if ($lowerValue === $case->value) {
                return $case;
            }
        }
        // 如果没有找到匹配项，返回默认源
        return self::DEFAULT;
    }

    /**
     * 获取所有支持的音乐源的值列表。
     *
     * @return array<int, string> 返回包含所有支持源名称的数组。
     */
    public static function getAllowedSources(): array
    {
        // 使用 array_map 获取所有 case 的值
        return array_map(fn(self $case) => $case->value, self::cases());
    }
      }
