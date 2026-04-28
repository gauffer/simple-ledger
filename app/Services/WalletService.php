<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class WalletService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wallet.url'), '/');
    }

    public function createAddress(string $gate, int $account, int $change, int $addressIndex): string
    {
        $response = Http::post("{$this->baseUrl}/api/v1/createaddress", [
            'gate' => $gate,
            'account' => $account,
            'change' => $change,
            'address_index' => $addressIndex,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("createaddress failed: {$response->body()}");
        }

        return $response->json('address');
    }

    public function validateAddress(string $gate, string $address): bool
    {
        $response = Http::post("{$this->baseUrl}/api/v1/validateaddress", [
            'gate' => $gate,
            'address' => $address,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("validateaddress failed: {$response->body()}");
        }

        return $response->json('valid');
    }

    /**
     * @return array{signed_tx: string, tx_hash: string}
     */
    public function signTransaction(string $gate, int $account, int $change, int $addressIndex, array $txParams): array
    {
        $response = Http::post("{$this->baseUrl}/api/v1/tx", [
            'gate' => $gate,
            'account' => $account,
            'change' => $change,
            'address_index' => $addressIndex,
            'tx_params' => $txParams,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("tx signing failed: {$response->body()}");
        }

        return [
            'signed_tx' => $response->json('signed_tx'),
            'tx_hash' => $response->json('tx_hash'),
        ];
    }
}
