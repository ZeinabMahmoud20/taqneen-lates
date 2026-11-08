<?php

namespace App\Support\Theme;

/**
 * ألوان الهوية البصرية (KFS Royal Egyptian) بصيغة palettes يفهمها Filament.
 *
 * Filament بياخد اللون الأساسي من الدرجة 600 (الأزرار والروابط)، فبنحط لون الـ design system
 * في 600 بالظبط، وباقي الدرجات (50 - 950) بتتولّد بالخلط مع الأبيض (للأفتح) أو الأسود (للأغمق).
 */
final class KfsTheme
{
    /** نسب الخلط مع الأبيض للدرجات الأفتح من 600 */
    private const TINTS = [500 => 0.12, 400 => 0.30, 300 => 0.50, 200 => 0.72, 100 => 0.86, 50 => 0.94];

    /** نسب الخلط مع الأسود للدرجات الأغمق من 600 */
    private const SHADES = [700 => 0.20, 800 => 0.38, 900 => 0.56, 950 => 0.72];

    /** @return array<string, array<int, string>> للاستخدام في ->colors(...) */
    public static function colors(): array
    {
        return [
            // الأخضر الملكي: 600 = --egypt-green، و500/400/800 من التوكنز (green-2 / green-3 / deep)
            'primary' => self::palette('#123F35', [500 => '#185447', 400 => '#216455', 800 => '#0A2924']),

            // رمادي دافئ من توكنز الـ design system (ivory / papyrus / sand / text ...)
            // الدرجات 400 و800 و900 قيم وسيطة اخترتها أنا (مش موجودة في الملف)
            'gray' => [
                50 => '#FAF8F2',   // --ivory
                100 => '#F4F0E6',  // --papyrus
                200 => '#E9E2D2',  // --sand
                300 => '#DCD2BD',  // --sand-deep
                400 => '#A8AAA1',
                500 => '#858A84',  // --muted
                600 => '#626963',  // --text-soft
                700 => '#303632',  // --text
                800 => '#2A302D',
                900 => '#252A27',
                950 => '#202522',  // --charcoal
            ],

            // ألوان الحالات (توكنز Government Status System)
            'success' => self::palette('#237A52'),
            'warning' => self::palette('#B27A24'),
            'danger' => self::palette('#A93232'),
            'info' => self::palette('#2D6477'),
        ];
    }

    /**
     * @param  array<int, string>  $overrides  قيم صريحة لدرجات معينة بدل المتولدة
     * @return array<int, string> الدرجات 50 - 950 (الدرجة 600 = اللون الأساسي بالظبط)
     */
    public static function palette(string $base, array $overrides = []): array
    {
        $base = self::normalize($base);
        $palette = [600 => $base];

        foreach (self::TINTS as $shade => $ratio) {
            $palette[$shade] = self::mix($base, '#FFFFFF', $ratio);
        }
        foreach (self::SHADES as $shade => $ratio) {
            $palette[$shade] = self::mix($base, '#000000', $ratio);
        }

        foreach ($overrides as $shade => $color) {
            $palette[$shade] = self::normalize($color);
        }

        ksort($palette);

        return $palette;
    }

    /** سطوع نسبي (WCAG) من 0 لـ 1، علشان نتأكد إن الدرجات مترتبة من الأفتح للأغمق */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(
            fn (int $channel): float => ($c = $channel / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            self::rgb($hex),
        );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function mix(string $from, string $to, float $ratio): string
    {
        $a = self::rgb($from);
        $b = self::rgb($to);

        return sprintf(
            '#%02X%02X%02X',
            (int) round($a[0] + ($b[0] - $a[0]) * $ratio),
            (int) round($a[1] + ($b[1] - $a[1]) * $ratio),
            (int) round($a[2] + ($b[2] - $a[2]) * $ratio),
        );
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim(self::normalize($hex), '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function normalize(string $hex): string
    {
        $hex = strtoupper(ltrim(trim($hex), '#'));

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (! preg_match('/^[0-9A-F]{6}$/', $hex)) {
            throw new \InvalidArgumentException("لون غير صالح: {$hex}");
        }

        return '#' . $hex;
    }
}