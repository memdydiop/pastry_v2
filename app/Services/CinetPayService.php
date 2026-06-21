<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CinetPayService
{
    private string $apiKey;
    private string $siteId;
    private string $secretKey;
    private bool $sandbox;

    public function __construct()
    {
        $this->apiKey = config('cinetpay.api_key');
        $this->siteId = config('cinetpay.site_id');
        $this->secretKey = config('cinetpay.secret_key');
        $this->sandbox = config('cinetpay.sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://api-sandbox.cinetpay.com/v1'
            : 'https://api.cinetpay.com/v1';
    }

    public function generatePaymentLink(array $data): array
    {
        $payload = [
            'apikey' => $this->apiKey,
            'site_id' => $this->siteId,
            'transaction_id' => $data['transaction_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'XOF',
            'description' => $data['description'] ?? 'Abonnement Pastry SaaS',
            'customer_name' => $data['customer_name'] ?? '',
            'customer_surname' => $data['customer_surname'] ?? '',
            'customer_email' => $data['customer_email'] ?? '',
            'customer_phone_number' => $data['customer_phone_number'] ?? '',
            'customer_address' => $data['customer_address'] ?? '',
            'customer_city' => $data['customer_city'] ?? '',
            'customer_country' => $data['customer_country'] ?? 'CI',
            'customer_state' => $data['customer_state'] ?? 'CI',
            'customer_zip_code' => $data['customer_zip_code'] ?? '',
            'notify_url' => route('webhooks.cinetpay'),
            'return_url' => $data['return_url'] ?? route('onboarding.success'),
            'channels' => 'ALL',
            'language' => 'fr',
            'invoice_data' => $data['invoice_data'] ?? null,
        ];

        $response = Http::post("{$this->baseUrl()}/payment", $payload);

        if (!$response->successful()) {
            Log::error('CinetPay payment request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Échec de la génération du lien de paiement CinetPay.');
        }

        return $response->json();
    }

    public function verifyPayment(string $transactionId): array
    {
        $response = Http::post("{$this->baseUrl()}/verification", [
            'apikey' => $this->apiKey,
            'site_id' => $this->siteId,
            'transaction_id' => $transactionId,
        ]);

        if (!$response->successful()) {
            Log::error('CinetPay verification failed', [
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Échec de la vérification du paiement CinetPay.');
        }

        return $response->json();
    }

    public function isPaymentSuccessful(array $response): bool
    {
        return ($response['code'] ?? '') === '00'
            && ($response['message'] ?? '') === 'Transaction effectuée avec succès'
            && ($response['data']['status'] ?? '') === 'ACCEPTED';
    }
}
