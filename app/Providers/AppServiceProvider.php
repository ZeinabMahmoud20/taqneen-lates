<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\{Center, Citizen, CitizenPhone, Contract, Installment, InstallmentNote,
    Payment, PaymentPlan, Representative, RepresentativePhone, User};
use App\Policies\{ResourcePolicy, UserPolicy};
use Illuminate\Support\Facades\Gate;
use App\Models\OverdueInstallment;
use App\Policies\ActivityPolicy;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        foreach ([Center::class, Citizen::class, CitizenPhone::class, Contract::class,
              Installment::class, InstallmentNote::class, Payment::class,
              PaymentPlan::class, Representative::class, RepresentativePhone::class, OverdueInstallment::class] as $model) {
        Gate::policy($model, ResourcePolicy::class);
    }

    Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        Gate::policy(Activity::class, ActivityPolicy::class);
    }
}