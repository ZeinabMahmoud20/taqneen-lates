<?php

namespace App\Filament\Pages;

use App\Models\Contract;
use App\Services\Excel\ImportFormatException;
use App\Services\Excel\ImportReportWriter;
use App\Services\Excel\OpenSpoutTempFolder;
use App\Services\InstallmentAdditionImporter;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use Throwable;

/**
 * شاشة مستقلة لتسجيل سداد الأقساط وتاريخ الأذن من ملف Excel
 * على عقود موجودة بالفعل. نفس صلاحية إنشاء/تعديل العقود.
 */
class AddInstallments extends Page
{
    use WithFileUploads;

    protected static ?string $navigationLabel = 'إضافة أقساط';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-plus';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'إضافة أقساط';

    protected string $view = 'filament.pages.add-installments';

    /** @var mixed ملف Excel المرفوع */
    public $file = null;

    public ?string $fatalError = null;

    public ?array $lastResult = null;

    /** @var list<array{row:int,code:string,message:string}> */
    public array $lastErrors = [];

    /** @var list<array{row:int,code:string,message:string}> */
    public array $lastWarnings = [];

    public ?string $reportPath = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('create', Contract::class) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'ارفع تقرير الرسوم (العناوين في صف 9 والبيانات من صف 10) لتسجيل سداد الأقساط وتاريخ الأذن على العقود الموجودة.';
    }

    public function run(bool $dryRun = true): void
    {
        abort_unless(static::canAccess(), 403);

        $this->resetResult();

        $this->validate([
            'file' => ['required', 'file', 'max:10240'],
        ], [
            'file.required' => 'اختار ملف Excel الأول.',
            'file.file' => 'الملف المرفوع غير صالح.',
            'file.max' => 'حجم الملف أكبر من 10 ميجا.',
        ], [
            'file' => 'الملف',
        ]);

        $file = $this->file;

        if (! $file || strtolower((string) $file->getClientOriginalExtension()) !== 'xlsx') {
            $this->addError('file', 'الملف لازم يكون بصيغة xlsx. لو الملف xls قديم، افتحه في Excel واعمله Save As بصيغة xlsx.');

            return;
        }

        // الاستيراد الكبير ممكن ياخد وقت أطول من حد PHP الافتراضي
        @set_time_limit(0);

        $path = $file->getRealPath();

        try {
            $result = app(InstallmentAdditionImporter::class)->import($path, $dryRun, auth()->id());
        } catch (ImportFormatException $e) {
            $this->fatalError = $e->getMessage();

            Notification::make()
                ->danger()
                ->title('الملف غير متوافق مع الصيغة المطلوبة')
                ->body(e($e->getMessage()))
                ->persistent()
                ->send();

            return;
        } catch (Throwable $e) {
            report($e);

            $this->fatalError = 'حصل خطأ غير متوقع أثناء قراءة الملف: '.$e->getMessage();

            Notification::make()
                ->danger()
                ->title('لم يتم تنفيذ العملية')
                ->body(e($this->fatalError))
                ->persistent()
                ->send();

            return;
        } finally {
            $this->file = null;
        }

        $this->lastErrors = $result->errors;
        $this->lastWarnings = $result->warnings;
        $this->lastResult = [
            'dry_run' => $result->dryRun,
            'total' => $result->total,
            'applied' => $result->applied,
            'skipped' => $result->skipped,
            'paid_installments' => $result->paidInstallments,
            'permits_saved' => $result->permitsSaved,
            'paid_total' => $result->paidTotal,
            'summary' => $result->summary(),
        ];

        $this->buildReport();

        $this->notifyResult();
    }

    public function downloadReport()
    {
        if ($this->reportPath === null || ! is_file($this->reportPath)) {
            Notification::make()
                ->danger()
                ->title('لا يوجد تقرير جاهز')
                ->body('قم بتنفيذ العملية مرة آخرى حتى يتم توليد تقرير الأخطاء والتنبيهات.')
                ->send();

            return null;
        }

        $path = $this->reportPath;
        $this->reportPath = null;

        return response()
            ->download($path, 'installment-additions-report-'.now()->format('Y-m-d_H-i').'.xlsx')
            ->deleteFileAfterSend();
    }

    private function resetResult(): void
    {
        $this->fatalError = null;
        $this->lastResult = null;
        $this->lastErrors = [];
        $this->lastWarnings = [];

        if ($this->reportPath !== null && is_file($this->reportPath)) {
            @unlink($this->reportPath);
        }

        $this->reportPath = null;
        $this->resetValidation();
    }

    private function buildReport(): void
    {
        if ($this->lastErrors === [] && $this->lastWarnings === []) {
            return;
        }

        try {
            $path = OpenSpoutTempFolder::file('installment-additions-report-');
            (new ImportReportWriter)->write($this->lastErrors, $path, $this->lastWarnings);
            $this->reportPath = $path;
        } catch (ImportFormatException $e) {
            Notification::make()
                ->danger()
                ->title('تمت العملية لكن تعذّر إنشاء تقرير الأخطاء')
                ->body(e($e->getMessage()))
                ->persistent()
                ->send();
        }
    }

    private function notifyResult(): void
    {
        $result = $this->lastResult;
        $lines = [(string) ($result['summary'] ?? '')];

        foreach (array_slice($this->lastErrors, 0, 5) as $error) {
            $lines[] = "✗ صف {$error['row']} ({$error['code']}): {$error['message']}";
        }

        foreach (array_slice($this->lastWarnings, 0, 5) as $warning) {
            $lines[] = "⚠ صف {$warning['row']} ({$warning['code']}): {$warning['message']}";
        }

        $more = max(0, count($this->lastErrors) - 5) + max(0, count($this->lastWarnings) - 5);

        if ($more > 0) {
            $lines[] = "... و{$more} تفصيلة تانية في ملف التقرير اللي هيتحمّل.";
        }

        $notification = Notification::make()
            ->title(($result['dry_run'] ?? false) ? 'نتيجة التحقق ( لم يتم حفظ البيانات)' : 'نتيجة تسجيل السداد')
            ->body(implode('<br>', array_map(fn (string $line): string => e($line), $lines)))
            ->persistent();

        ($this->lastErrors === [] && $this->lastWarnings === [])
            ? $notification->success()
            : $notification->warning();

        $notification->send();
    }
}
