<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Notifications\Providers\EmailProvider;
use App\Services\Notifications\Providers\ProviderRegistry;
use App\Services\Notifications\Providers\SmsProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry([
            $app->make(EmailProvider::class),
            $app->make(SmsProvider::class),
        ]));
    }

    public function boot(): void
    {
        //
    }
}
