<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\SettingObserver;
use App\Observers\UserObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;


class EventServiceProvider extends ServiceProvider
{
    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Setting::observe(SettingObserver::class);
        Order::observe(OrderObserver::class);
        User::observe(UserObserver::class);
    }
}
