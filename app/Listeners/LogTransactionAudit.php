<?php

namespace App\Listeners;

use App\Events\TransactionAudited;
use App\Models\TransactionAuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogTransactionAudit
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TransactionAudited $event): void
    {
        TransactionAuditLog::create($event->audit_log_data);
    }
}
