<?php

use App\Http\Controllers\Admin\UpcomingInstallmentPrintController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('admin/upcoming-installments/print', UpcomingInstallmentPrintController::class)
    ->name('admin.upcoming-installments.print')
    ->middleware(Authenticate::class);
