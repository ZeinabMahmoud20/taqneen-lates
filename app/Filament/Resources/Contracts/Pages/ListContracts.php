<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Models\Contract;
use App\Services\ArrearsImporter;
use App\Services\Excel\ArrearsTemplateWriter;
use App\Services\Excel\ImportFormatException;
use App\Services\Excel\ImportReportWriter;
use App\Services\ImportResult;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->templateAction(),
            $this->importAction(),
            CreateAction::make(),
        ];
    }

    private function templateAction(): Action
    {
        return Action::make('template')
            ->label('تحميل القالب')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('create', Contract::class) ?? false)
            ->action(function () {
                $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arrears-template-' . uniqid() . '.xlsx';
                (new ArrearsTemplateWriter())->write($path);

                return response()->download($path, 'arrears-import-template.xlsx')->deleteFileAfterSend();
            });
    }

    private function importAction(): Action
    {
        return Action::make('import')
            ->label('استيراد Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('create', Contract::class) ?? false)
            ->modalHeading('استيراد عقود وأقساط من Excel')
            ->modalDescription('استخدم القالب الرسمي (زرار «تحميل القالب»). ابدأ بـ «تحقق فقط» علشان تشوف النتيجة من غير ما يتحفظ حاجة، وبعدين استورد فعلاً.')
            ->modalSubmitActionLabel('ابدأ')
            ->schema([
                FileUpload::make('file')
                    ->label('ملف Excel (xlsx)')
                    ->disk('local')
                    ->directory('imports')
                    ->visibility('private')
                    // بعض أجهزة ويندوز بتعرّف xlsx كـ zip، فبنقبل الاتنين والـ parser هو اللي بيتأكد إنه xlsx
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',
                        'application/x-zip-compressed',
                    ])
                    ->maxSize(10240)
                    ->required(),

                Toggle::make('dry_run')
                    ->label('تحقق فقط (من غير حفظ)')
                    ->helperText('شغّال افتراضياً للأمان. أوقفه علشان يتحفظ الاستيراد فعلاً.')
                    ->default(true),
            ])
            ->action(function (array $data) {
                abort_unless(auth()->user()?->can('create', Contract::class), 403);

                // الاستيراد الكبير ممكن ياخد وقت أطول من حد PHP الافتراضي
                @set_time_limit(0);

                $file = is_array($data['file']) ? (string) reset($data['file']) : (string) $data['file'];
                $disk = Storage::disk('local');
                $dryRun = (bool) ($data['dry_run'] ?? true);

                try {
                    $result = app(ArrearsImporter::class)->import($disk->path($file), $dryRun, auth()->id());
                } catch (ImportFormatException $e) {
                    Notification::make()
                        ->danger()
                        ->title('الملف مش مظبوط')
                        ->body(e($e->getMessage()))
                        ->persistent()
                        ->send();

                    return null;
                } finally {
                    $disk->delete($file);   // الملف المرفوع مايفضلش على السيرفر
                }

                $this->notifyResult($result);

                if ($result->errors === [] && $result->warnings === []) {
                    return null;
                }

                // تقرير بالأخطاء والتنبيهات بيتحمّل تلقائياً
                $report = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-report-' . uniqid() . '.xlsx';
                (new ImportReportWriter())->write($result->errors, $report, $result->warnings);

                return response()
                    ->download($report, 'import-report-' . now()->format('Y-m-d_H-i') . '.xlsx')
                    ->deleteFileAfterSend();
            });
    }

    private function notifyResult(ImportResult $result): void
    {
        $lines = [$result->summary()];

        foreach (array_slice($result->errors, 0, 5) as $error) {
            $lines[] = "✗ صف {$error['row']} ({$error['code']}): {$error['message']}";
        }
        foreach (array_slice($result->warnings, 0, 5) as $warning) {
            $lines[] = "⚠ صف {$warning['row']} ({$warning['code']}): {$warning['message']}";
        }

        $more = max(0, count($result->errors) - 5) + max(0, count($result->warnings) - 5);
        if ($more > 0) {
            $lines[] = "... و{$more} تفصيلة تانية في ملف التقرير اللي هيتحمّل.";
        }

        // الجسم بيتعرض كـ HTML متنضف، فكل سطر بيتعمله escape وبنفصل بينهم بـ <br>
        $notification = Notification::make()
            ->title($result->dryRun ? 'نتيجة التحقق (ماتحفظش حاجة)' : 'نتيجة الاستيراد')
            ->body(implode('<br>', array_map(fn (string $line): string => e($line), $lines)))
            ->persistent();

        ($result->errors === [] && $result->warnings === [])
            ? $notification->success()
            : $notification->warning();

        $notification->send();
    }
}
