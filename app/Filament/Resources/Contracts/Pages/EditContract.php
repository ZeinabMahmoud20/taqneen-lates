<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Services\ContractService;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    /**
     * بعد الحفظ: سجل دفع المقدم بيتظبط، ولو المستخدم أضاف تاريخ المقدم (كان فاضي)
     * بنحسب مواعيد الاستحقاق للأقساط اللي موعدها فاضي.
     * الأقساط اللي ليها موعد بالفعل مابتتغيرش.
     */
    protected function afterSave(): void
    {
        $service = app(ContractService::class);

        // سجل دفع المقدم يطابق بيانات العقد (لو المبلغ والتاريخ موجودين)
        $service->syncDownPayment($this->record, auth()->id());

        $filled = $service->fillMissingDueDates($this->record);

        if ($filled > 0) {
            Notification::make()
                ->success()
                ->title("اتحسبت مواعيد استحقاق {$filled} قسط من تاريخ المقدم")
                ->send();
        }
    }
}
