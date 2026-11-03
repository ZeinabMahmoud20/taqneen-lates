<?php

namespace Tests\Unit;

use App\Support\ActivityChanges;
use PHPUnit\Framework\TestCase;

class ActivityChangesTest extends TestCase
{
    public function test_updated_event_shows_old_and_new_values_with_arabic_labels(): void
    {
        $rows = ActivityChanges::rows(
            ['attributes' => ['amount' => '150.00', 'status' => 'paid'], 'old' => ['amount' => '100.00', 'status' => 'pending']],
            'updated',
        );

        $this->assertSame([
            ['field' => 'المبلغ', 'old' => '100.00', 'new' => '150.00'],
            ['field' => 'الحالة', 'old' => 'لم يُسدد', 'new' => 'مسدد'],
        ], $rows);
    }

    public function test_created_event_has_no_old_values(): void
    {
        $rows = ActivityChanges::rows(['attributes' => ['name' => 'صبري', 'code' => '253190']], 'created');

        $this->assertSame('—', $rows[0]['old']);
        $this->assertSame('صبري', $rows[0]['new']);
    }

    public function test_deleted_event_has_no_new_values(): void
    {
        $rows = ActivityChanges::rows(['old' => ['name' => 'صبري']], 'deleted');

        $this->assertSame([['field' => 'الاسم', 'old' => 'صبري', 'new' => '—']], $rows);
    }

    public function test_technical_and_sensitive_fields_are_hidden(): void
    {
        $rows = ActivityChanges::rows(
            ['attributes' => ['name' => 'س', 'created_by' => 1, 'updated_by' => 1, 'password' => 'x', 'updated_at' => '2026-01-01']],
            'created',
        );

        $this->assertCount(1, $rows);
    }

    public function test_null_boolean_and_enum_like_values_are_formatted(): void
    {
        $rows = ActivityChanges::rows(
            ['attributes' => ['is_active' => 0, 'role' => 'regulator', 'ended_at' => null], 'old' => ['is_active' => 1, 'role' => 'leadership', 'ended_at' => '2026-01-01']],
            'updated',
        );

        $this->assertSame(['field' => 'الحساب مفعّل', 'old' => 'نعم', 'new' => 'لا'], $rows[0]);
        $this->assertSame(['field' => 'الدور', 'old' => 'قيادة', 'new' => 'مسئول تقنين'], $rows[1]);
        $this->assertSame(['field' => 'نهاية النيابة', 'old' => '2026-01-01', 'new' => '—'], $rows[2]);
    }

    public function test_unknown_fields_fall_back_to_the_raw_name(): void
    {
        $rows = ActivityChanges::rows(['attributes' => ['some_new_column' => 'x']], 'created');

        $this->assertSame('some_new_column', $rows[0]['field']);
    }

    public function test_html_is_escaped(): void
    {
        $html = ActivityChanges::html(['attributes' => ['note' => '<script>alert(1)</script>']], 'created');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_empty_changes_give_a_friendly_message(): void
    {
        $this->assertStringContainsString('مفيش تفاصيل', ActivityChanges::html(null, 'created'));
        $this->assertStringContainsString('مفيش تفاصيل', ActivityChanges::html([], 'updated'));
    }

    public function test_labels_for_events_and_models(): void
    {
        $this->assertSame('إضافة', ActivityChanges::eventLabel('created'));
        $this->assertSame('تعديل', ActivityChanges::eventLabel('updated'));
        $this->assertSame('حذف', ActivityChanges::eventLabel('deleted'));
        $this->assertSame('عقد', ActivityChanges::subjectLabel('App\\Models\\Contract'));
        $this->assertSame('Something', ActivityChanges::subjectLabel('App\\Models\\Something'));
        $this->assertSame('—', ActivityChanges::subjectLabel(null));
    }
}
