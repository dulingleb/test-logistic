<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Notifications\Providers\EmailProviderStub;
use App\Services\Notifications\Providers\ProviderRegistry;
use App\Services\Notifications\Providers\SmsProviderStub;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry([
            $app->make(EmailProviderStub::class),
            $app->make(SmsProviderStub::class),
        ]));
    }

    public function boot(): void
    {
        //
    }
}
