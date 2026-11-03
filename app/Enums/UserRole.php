<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';   // مشرف عام
    case Regulator  = 'regulator';     // مسئول تقنين
    case Leadership = 'leadership';    // قيادة

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مشرف عام',
            self::Regulator  => 'مسئول تقنين',
            self::Leadership => 'قيادة',
        };
    }

    // Filament بيستخدمها لعرض الاسم العربي في القوائم والـ badges
    public function getLabel(): ?string
    {
        return $this->label();
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public function canView(): bool
    {
        return true;
    }

    public function canExport(): bool
    {
        return true;
    }

    public function canCreateOrUpdate(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Regulator], true);
    }

    public function canDelete(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function canManageUsers(): bool
    {
        return $this === self::SuperAdmin;
    }
}
