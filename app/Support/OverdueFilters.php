<?php

namespace App\Support;

/**
 * فلاتر لوحة التحكم: فترة (تاريخ استحقاق القسط المتأخر) + مركز.
 * بتتبني من قيم الفلاتر الخام (اللي جاية من المتصفح)، فكل قيمة بتتنضف وبتتحقق منها:
 * قيمة مش مفهومة بتتجاهل بدل ما تكسر الصفحة.
 */
final class OverdueFilters
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $until = null,
        public readonly ?int $centerId = null,
    ) {
    }

    /** @param array<string, mixed>|null $raw */
    public static function fromArray(?array $raw): self
    {
        $from = self::date($raw['from'] ?? null);
        $until = self::date($raw['until'] ?? null);

        // لو المستخدم كتب الفترة معكوسة بنقلبها بدل ما نرجّع نتيجة فاضية مربكة
        if ($from !== null && $until !== null && $from > $until) {
            [$from, $until] = [$until, $from];
        }

        return new self($from, $until, self::id($raw['center_id'] ?? null));
    }

    public function isActive(): bool
    {
        return $this->from !== null || $this->until !== null || $this->centerId !== null;
    }

    /**
     * جملة WHERE (بـ placeholders) وقيمها، علشان مفيش قيمة بتتحط في الـ SQL مباشرة.
     *
     * @return array{0: string, 1: list<string|int>}
     */
    public function where(): array
    {
        $conditions = [];
        $bindings = [];

        if ($this->from !== null) {
            $conditions[] = 'due_date >= ?';
            $bindings[] = $this->from;
        }
        if ($this->until !== null) {
            $conditions[] = 'due_date <= ?';
            $bindings[] = $this->until;
        }
        if ($this->centerId !== null) {
            $conditions[] = 'center_id = ?';
            $bindings[] = $this->centerId;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $bindings];
    }

    /** وصف الفلاتر الشغّالة للعرض (null لو مفيش فلتر) */
    public function summary(?string $centerName = null): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        $parts = [];

        if ($this->from !== null && $this->until !== null) {
            $parts[] = "الاستحقاق من {$this->from} إلى {$this->until}";
        } elseif ($this->from !== null) {
            $parts[] = "الاستحقاق من {$this->from}";
        } elseif ($this->until !== null) {
            $parts[] = "الاستحقاق حتى {$this->until}";
        }

        if ($this->centerId !== null) {
            $parts[] = 'المركز: ' . ($centerName ?? '#' . $this->centerId);
        }

        return implode(' | ', $parts);
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? "{$m[1]}-{$m[2]}-{$m[3]}" : null;
    }

    private static function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
