<?php

namespace App\Services\Excel;

use RuntimeException;

/** الملف نفسه مش مظبوط (عناوين ناقصة، فاضي، ...) - مش خطأ في صف معين. */
class ImportFormatException extends RuntimeException
{
}
