<?php

namespace App\Providers;

use App\Notifications\Channels\WebhookChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::resolved(function (ChannelManager $service) {
            $service->extend('webhook', function ($app) {
                return new WebhookChannel;
            });
        });
    }
}
