<?php

namespace App\Http\Controllers;

use App\Enums\TransactionStatus;
use App\Events\FailedPayment;
use App\Events\Payment;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\VanillaPayService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function vanillapay(Request $request, VanillaPayService $vanillaPay)
    {
        $signature = $request->header('VPI-Signature');
        $rawPayload = $request->getContent(); // Get raw string for hash verification 

        if (!$signature || !$vanillaPay->verifyWebhookSignature($rawPayload, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $status = $request->input('etat'); // SUCCESS, PENDING, or FAILED 
        $transaction_uuid = $request->input('reference');

        if ($transaction = Transaction::where('uuid', $transaction_uuid)->first()) {
            $transaction->update('informations', $request->all());

            Payment::dispatchIf($status === TransactionStatus::SUCCESS->value, $transaction->order, $transaction);
            FailedPayment::dispatchIf($status === TransactionStatus::FAILED->value, $transaction->order, $transaction);
        }

        return response()->json([
            'message' => 'Notification processed successfully'
        ]);
    }
}
