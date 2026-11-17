<?php

namespace App\Filament\Resources\UpcomingInstallments\Pages;

use App\Filament\Resources\UpcomingInstallments\UpcomingInstallmentResource;
use Filament\Resources\Pages\ListRecords;

class ListUpcomingInstallments extends ListRecords
{
    protected static string $resource = UpcomingInstallmentResource::class;
}
