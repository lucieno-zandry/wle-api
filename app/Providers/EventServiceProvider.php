<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\SettingObserver;
use App\Observers\ShipmentObserver;
use App\Observers\TransactionObserver;
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
        Transaction::observe(TransactionObserver::class);
        Shipment::observe(ShipmentObserver::class);
    }
}
