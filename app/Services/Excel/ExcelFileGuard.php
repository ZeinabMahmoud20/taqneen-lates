<?php

namespace App\Services\Excel;

use Throwable;
use XMLReader;
use ZipArchive;

/**
 * فحص ملف الإكسيل قبل ما نحاول نفتحه، علشان نطلع رسالة مفهومة للمستخدم
 * بدل أخطاء المكتبة العامة زي «Not a zip archive».
 */
final class ExcelFileGuard
{
    private const OLE2_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    private const ZIP_MAGIC = "PK\x03\x04";

    /** @throws ImportFormatException */
    public function assertCanBeOpened(string $path): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new ImportFormatException(
                'السيرفر ناقصه إضافة PHP Zip (ext-zip). فعّلها من php.ini أو ثبّت php-zip، وبعدها أعد تشغيل PHP/السيرفر.'
            );
        }

        if (! class_exists(XMLReader::class)) {
            throw new ImportFormatException(
                'السيرفر ناقصه إضافة PHP XMLReader (ext-xmlreader). فعّلها من php.ini أو ثبّت php-xml، وبعدها أعد تشغيل PHP/السيرفر.'
            );
        }

        if (! is_file($path)) {
            throw new ImportFormatException(
                'الملف المرفوع مش موجود على السيرفر. جرّب ترفعه تاني، ولو المشكلة كررت راجع صلاحيات مجلد storage/app/private.'
            );
        }

        if (! is_readable($path)) {
            throw new ImportFormatException(
                'الملف مرفوع لكن السيرفر مش قادر يقرأه. راجع صلاحيات مجلد storage/app/private للمستخدم اللي بيشغّل PHP.'
            );
        }

        if ($this->isOleContainer($path)) {
            if ($this->looksPasswordProtected($path)) {
                throw new ImportFormatException(
                    'الملف محمي بكلمة مرور. افتحه في Excel، امسح الحماية من File ← Info ← Protect Workbook ← Encrypt with Password، وبعدين اعمل Save As بصيغة xlsx وجرّب تاني.'
                );
            }

            throw new ImportFormatException(
                'الملف بصيغة xls قديمة أو محمي بكلمة مرور. افتحه في Excel واعمله Save As بصيغة xlsx وجرّب تاني.'
            );
        }

        if (! $this->isZipArchive($path)) {
            throw new ImportFormatException(
                'الملف تالف أو مش xlsx حقيقي. افتحه في Excel واعمله Save As بصيغة xlsx وجرّب تاني.'
            );
        }
    }

    /** ترجمة أخطاء فتح الملف (OpenSpout) لرسالة مفهومة للمستخدم */
    public function messageForOpenError(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Class "ZipArchive" not found')) {
            return 'السيرفر ناقصه إضافة PHP Zip (ext-zip). فعّلها من php.ini أو ثبّت php-zip، وبعدها أعد تشغيل PHP/السيرفر.';
        }

        if (str_contains($message, 'Class "XMLReader" not found')) {
            return 'السيرفر ناقصه إضافة PHP XMLReader (ext-xmlreader). فعّلها من php.ini أو ثبّت php-xml، وبعدها أعد تشغيل PHP/السيرفر.';
        }

        if (str_contains($message, 'encryption password required')
            || str_contains($message, 'EncryptedPackage')
            || str_contains($message, 'EncryptionInfo')) {
            return 'الملف محمي بكلمة مرور. افتحه في Excel، امسح الحماية، واعمله Save As بصيغة xlsx وجرّب تاني.';
        }

        if (str_contains($message, 'Not a zip archive') || str_contains($message, 'Zip archive inconsistent')) {
            return 'الملف تالف أو مش xlsx حقيقي. افتحه في Excel واعمله Save As بصيغة xlsx وجرّب تاني.';
        }

        if (str_contains($message, 'not readable') || str_contains($message, 'Cannot perform I/O')
            || str_contains($message, "Can't open file") || str_contains($message, 'Permission denied')) {
            return 'السيرفر مش قادر يقرأ الملف. راجع صلاحيات مجلد storage/app/private.';
        }

        if (str_contains($message, 'writable folder') || str_contains($message, 'Unable to create folder')) {
            return 'مجلد الملفات المؤقتة على السيرفر مش قابل للكتابة. راجع صلاحيات storage/app/private.';
        }

        return 'مش قادر أفتح الملف. اتأكد إنه ملف Excel بصيغة xlsx، وراجع سجل الأخطاء laravel.log للتفاصيل.';
    }

    private function isOleContainer(string $path): bool
    {
        return $this->head($path, strlen(self::OLE2_MAGIC)) === self::OLE2_MAGIC;
    }

    private function isZipArchive(string $path): bool
    {
        return $this->head($path, strlen(self::ZIP_MAGIC)) === self::ZIP_MAGIC;
    }

    /**
     * ملفات xlsx المشفّرة بتتحفظ كـ OLE2 container جوّاه streams
     * EncryptedPackage / EncryptionInfo. بنبحث عنهم في أول جزء من الملف.
     */
    private function looksPasswordProtected(string $path): bool
    {
        $head = @file_get_contents($path, false, null, 0, 65536);

        if ($head === false) {
            return false;
        }

        $utf16 = fn (string $text): string => implode("\0", str_split($text))."\0";

        foreach (['EncryptedPackage', 'EncryptionInfo'] as $marker) {
            if (str_contains($head, $marker) || str_contains($head, $utf16($marker))) {
                return true;
            }
        }

        return false;
    }

    private function head(string $path, int $length): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        try {
            $bytes = fread($handle, $length);

            return $bytes === false ? '' : $bytes;
        } finally {
            fclose($handle);
        }
    }
}
