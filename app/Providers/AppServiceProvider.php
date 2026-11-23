<?php

namespace App\Providers;

use App\Adapters\Auth\AuthAdapter;
use App\Adapters\Auth\LocalAuthAdapter;
use App\Adapters\Notification\EmailInAppNotifier;
use App\Adapters\Notification\Notifier;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Adapter dapat diganti tanpa mengubah modul lain (Brief 9.3).
        $this->app->bind(AuthAdapter::class, fn () => match (config('blj.auth.driver')) {
            default => new LocalAuthAdapter,
        });
        $this->app->bind(Notifier::class, EmailInAppNotifier::class);
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Paginator::defaultView('partials.pagination');
    }
}
