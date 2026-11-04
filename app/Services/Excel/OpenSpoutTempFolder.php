<?php

namespace App\Services\Excel;

use OpenSpout\Reader\XLSX\Options as ReaderOptions;
use OpenSpout\Writer\XLSX\Options as WriterOptions;

/**
 * اختيار مجلد مؤقت قابل للكتابة لـ OpenSpout.
 *
 * OpenSpout بيستخدم sys_get_temp_dir() افتراضيًا (على ويندوز: C:\Windows\TEMP)،
 * والمجلد ده على سيرفرات IIS كتير مش قابل للكتابة لمستخدم الـ App Pool، فالقراءة
 * أو الكتابة بتفشل برسالة "C:\Windows\TEMP is not a writable folder".
 *
 * الكلاس ده بيدور على أول مجلد قابل للكتابة فعليًا (بنجرب كتابة حقيقية، مش بس
 * is_writable) وبيستخدمه للقارئ والكاتب على السواء.
 */
final class OpenSpoutTempFolder
{
    /** @var array<string,string> المجلد اللي اتحدد لكل مسار ملف */
    private static array $resolved = [];

    /**
     * بيظبط مجلد الـ temp على Options بتاعة القارئ أو الكاتب.
     *
     * @throws ImportFormatException
     */
    public static function configure(ReaderOptions|WriterOptions $options, ?string $nearPath = null): string
    {
        $folder = self::ensureDirectory($nearPath);

        try {
            $options->setTempFolder($folder);
        } catch (\Throwable $e) {
            report($e);

            throw new ImportFormatException(
                "مجلد الملفات المؤقتة على السيرفر مش قابل للكتابة: {$folder}. راجع صلاحيات storage/app/private للمستخدم اللي بيشغّل PHP."
            );
        }

        return $folder;
    }

    /**
     * بيرجع مجلد مؤقت موجود وقابل للكتابة، وبيعمل إنشاءه لو مش موجود.
     *
     * @throws ImportFormatException
     */
    public static function ensureDirectory(?string $nearPath = null): string
    {
        $key = $nearPath ?? '';

        if (isset(self::$resolved[$key])) {
            return self::$resolved[$key];
        }

        foreach (self::candidates($nearPath) as $folder) {
            if (self::isUsable($folder)) {
                return self::$resolved[$key] = $folder;
            }
        }

        throw new ImportFormatException(
            'مجلد الملفات المؤقتة على السيرفر مش قابل للكتابة. راجع صلاحيات storage/app/private و storage/app للمستخدم اللي بيشغّل PHP.'
        );
    }

    /**
     * مسار ملف xlsx جديد جوه مجلد مؤقت قابل للكتابة (بدل sys_get_temp_dir).
     *
     * @throws ImportFormatException
     */
    public static function file(string $prefix = 'excel-', ?string $nearPath = null): string
    {
        $folder = self::ensureDirectory($nearPath);

        return $folder . DIRECTORY_SEPARATOR . $prefix . uniqid('', true) . '.xlsx';
    }

    /** @return list<string> */
    private static function candidates(?string $nearPath): array
    {
        $candidates = [];

        if ($nearPath !== null && $nearPath !== '') {
            $nearDir = dirname($nearPath);

            // لو الملف نفسه جوه مجلد الـ temp بتاعنا، نستخدمه من غير ما نعمل مجلد متداخل.
            $candidates[] = basename($nearDir) === 'openspout-temp'
                ? $nearDir
                : $nearDir . DIRECTORY_SEPARATOR . 'openspout-temp';
        }

        $candidates[] = storage_path('app/private/openspout-temp');
        $candidates[] = storage_path('app/openspout-temp');
        $candidates[] = storage_path('app/private/tmp');
        $candidates[] = storage_path('framework/cache');
        $candidates[] = storage_path('framework/temp');
        $candidates[] = sys_get_temp_dir();

        return array_values(array_unique(array_filter(
            $candidates,
            static fn (?string $path): bool => $path !== null && $path !== '',
        )));
    }

    private static function isUsable(string $folder): bool
    {
        if (! is_dir($folder) && ! @mkdir($folder, 0775, true) && ! is_dir($folder)) {
            return false;
        }

        // is_writable ساعات بتدّي نتيجة غلط على ويندوز، فبنجرب كتابة فعلية.
        $probe = $folder . DIRECTORY_SEPARATOR . 'write-test-' . uniqid('', true);
        if (@file_put_contents($probe, 'ok') === false) {
            return false;
        }
        @unlink($probe);

        // OpenSpout نفسه بيتحقق بـ is_writable، فلازم تبقى true كمان.
        return is_writable($folder) && realpath($folder) !== false;
    }
}
