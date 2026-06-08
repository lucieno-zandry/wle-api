<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\PendingRequest;
use Exception;

class VanillaPayService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $keySecret;
    private string $apiVersion;

    public function __construct()
    {
        $environment = Config::get('vanillapay.environment');
        $this->baseUrl = Config::get("vanillapay.base_urls.{$environment}");
        $this->clientId = Config::get('vanillapay.client_id');
        $this->clientSecret = Config::get('vanillapay.client_secret');
        $this->keySecret = Config::get('vanillapay.key_secret');
        $this->apiVersion = Config::get('vanillapay.version');
    }

    /**
     * Set up the base HTTP client with standard headers.
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Accept' => '*/*',
                'VPI-Version' => $this->apiVersion,
            ]);
    }

    /**
     * Retrieve and cache the authentication token.
     */
    public function getToken(): string
    {
        // Cache the token for 15 minutes (validity is 20 minutes) 
        return Cache::remember('vpi_auth_token', 60 * 15, function () {
            $response = $this->client()
                ->withHeaders([
                    'Client-Id' => $this->clientId,
                    'Client-Secret' => $this->clientSecret,
                ])
                ->get('/webpayment/token');

            if ($response->failed()) {
                throw new Exception('Failed to retrieve Vanilla Pay token: ' . $response->body());
            }

            // The API returns the token already prefixed with "Bearer " 
            return $response->json('Data.Token');
        });
    }

    /**
     * Initiate a payment and return the payment link.
     */
    public function initiatePayment(array $paymentData): string
    {
        $response = $this->client()
            ->withHeaders([
                'Authorization' => $this->getToken(),
            ])
            ->post('/api/webpayment/v2/initiate', [
                'montant' => $paymentData['montant'],
                'reference' => $paymentData['reference'],
                'panier' => $paymentData['panier'],
                'notif_url' => $paymentData['notif_url'],
                'redirect_url' => $paymentData['redirect_url'],
                'devise' => $paymentData['devise'] ?? 'MGA',
                'mode_paiement' => $paymentData['mode_paiement'] ?? 'international',
            ]);

        if ($response->failed()) {
            throw new Exception('Payment initiation failed: ' . $response->body());
        }

        return $response->json('Data.url');
    }

    /**
     * Check the status of a specific transaction.
     */
    public function getTransactionStatus(string $transactionId, string $modePaiement = 'international'): array
    {
        $response = $this->client()
            ->withHeaders([
                'Authorization' => $this->getToken(),
            ])
            ->get("/api/webpayment/v2/status/{$transactionId}", [
                'mode_paiement' => $modePaiement
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to fetch transaction status: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Verify the HMAC SHA256 signature sent by the webhook.
     */
    public function verifyWebhookSignature(string $payloadBody, string $signatureHeader): bool
    {
        // Hash the raw body using HMAC SHA256 and the keySecret 
        $expectedSignature = hash_hmac('sha256', $payloadBody, $this->keySecret);

        // Compare securely to prevent timing attacks
        return hash_equals(strtoupper($expectedSignature), strtoupper($signatureHeader));
    }
}
