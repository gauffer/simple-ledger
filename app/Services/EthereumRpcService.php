<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EthereumRpcService
{
    private int $requestId = 1;

    private function call(string $rpcUrl, string $method, array $params = []): mixed
    {
        $response = Http::timeout(30)->retry(3, 1000)->post($rpcUrl, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->requestId++,
        ]);

        if ($response->failed()) {
            throw new RuntimeException("RPC request failed: {$response->body()}");
        }

        $body = $response->json();

        if (isset($body['error'])) {
            throw new RuntimeException("RPC error: {$body['error']['message']} (code: {$body['error']['code']})");
        }

        return $body['result'];
    }

    public function getBlockNumber(string $rpcUrl): string
    {
        $hex = $this->call($rpcUrl, 'eth_blockNumber');

        return gmp_strval(gmp_init($hex, 16));
    }

    public function getBlockByNumber(string $rpcUrl, string $blockNumber): ?array
    {
        $hex = '0x' . gmp_strval(gmp_init($blockNumber, 10), 16);

        return $this->call($rpcUrl, 'eth_getBlockByNumber', [$hex, true]);
    }

    public function getTransactionReceipt(string $rpcUrl, string $txHash): ?array
    {
        return $this->call($rpcUrl, 'eth_getTransactionReceipt', [$txHash]);
    }

    public function sendRawTransaction(string $rpcUrl, string $signedTx): string
    {
        return $this->call($rpcUrl, 'eth_sendRawTransaction', [$signedTx]);
    }

    public function getTransactionCount(string $rpcUrl, string $address): string
    {
        $hex = $this->call($rpcUrl, 'eth_getTransactionCount', [$address, 'pending']);

        return gmp_strval(gmp_init($hex, 16));
    }

    public function getLogs(string $rpcUrl, string $blockNumber, array $filter = []): array
    {
        $hex = '0x' . gmp_strval(gmp_init($blockNumber, 10), 16);

        $params = array_merge($filter, [
            'fromBlock' => $hex,
            'toBlock' => $hex,
        ]);

        return $this->call($rpcUrl, 'eth_getLogs', [$params]) ?? [];
    }

    /**
     * Параметры газа EIP-1559: maxFeePerGas = 2 * baseFee + priorityFee.
     *
     * @return array{max_fee_per_gas_wei: string, max_priority_fee_per_gas_wei: string}
     */
    public function getGasParams(string $rpcUrl): array
    {
        $latestBlock = $this->call($rpcUrl, 'eth_getBlockByNumber', ['latest', false]);
        $baseFeeHex = $latestBlock['baseFeePerGas'] ?? '0x0';

        $priorityFeeHex = $this->call($rpcUrl, 'eth_maxPriorityFeePerGas');

        $baseFee = gmp_init($baseFeeHex, 16);
        $priorityFee = gmp_init($priorityFeeHex, 16);

        $maxFee = gmp_add(gmp_mul($baseFee, 2), $priorityFee);

        return [
            'max_fee_per_gas_wei' => gmp_strval($maxFee),
            'max_priority_fee_per_gas_wei' => gmp_strval($priorityFee),
        ];
    }
}
