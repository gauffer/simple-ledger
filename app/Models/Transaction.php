<?php

namespace App\Models;

use App\Services\EthereumRpcService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    private const string TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    private const int NATIVE_DECIMALS = 18;

    protected $fillable = [
        'gate_id',
        'address_id',
        'tx_hash',
        'block_number',
        'block_hash',
        'log_index',
        'from_address',
        'to_address',
        'amount',
        'amount_raw',
        'confirmations',
    ];

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }


    public static function recordNativeDeposits(Block $block, array $blockData, mixed $addresses, Gate $nativeGate): void
    {
        foreach ($blockData['transactions'] as $tx) {
            $to = $tx['to'] ?? null;
            if (! $to) {
                continue;
            }

            $address = $addresses->get(strtolower($to));
            if (! $address) {
                continue;
            }

            $amountRaw = gmp_strval(gmp_init($tx['value'], 16));

            self::firstOrCreate(
                ['gate_id' => $nativeGate->id, 'tx_hash' => $tx['hash'], 'log_index' => 0],
                [
                    'address_id' => $address->id,
                    'block_number' => $block->block_number,
                    'block_hash' => $block->block_hash,
                    'from_address' => $tx['from'],
                    'to_address' => $to,
                    'amount' => self::fromBaseUnits($amountRaw, self::NATIVE_DECIMALS),
                    'amount_raw' => $amountRaw,
                ],
            );
        }
    }


    public static function recordErc20Deposits(
        Block $block,
        EthereumRpcService $rpc,
        string $rpcUrl,
        array $blockData,
        mixed $addresses,
        mixed $erc20Gates,
    ): void {
        $tokenContracts = $erc20Gates->keys()->all();

        $logs = $rpc->getLogs($rpcUrl, $block->block_number, [
            'address' => count($tokenContracts) === 1 ? $tokenContracts[0] : $tokenContracts,
            'topics' => [self::TRANSFER_TOPIC],
        ]);

        foreach ($logs as $log) {
            if (count($log['topics']) < 3) {
                continue;
            }

            $toHex = '0x' . substr($log['topics'][2], 26);
            $address = $addresses->get(strtolower($toHex));
            if (! $address) {
                continue;
            }

            $gate = $erc20Gates->get(strtolower($log['address']));
            if (! $gate) {
                continue;
            }

            $amountRaw = gmp_strval(gmp_init($log['data'], 16));

            self::firstOrCreate(
                ['gate_id' => $gate->id, 'tx_hash' => $log['transactionHash'], 'log_index' => hexdec($log['logIndex'])],
                [
                    'address_id' => $address->id,
                    'block_number' => $block->block_number,
                    'block_hash' => $block->block_hash,
                    'from_address' => '0x' . substr($log['topics'][1], 26),
                    'to_address' => $toHex,
                    'amount' => self::fromBaseUnits($amountRaw, $gate->getDecimals()),
                    'amount_raw' => $amountRaw,
                ],
            );
        }
    }

    private static function fromBaseUnits(string $raw, int $decimals): string
    {
        if ($decimals === 0) {
            return $raw;
        }

        $padded = str_pad($raw, $decimals + 1, '0', STR_PAD_LEFT);
        $intPart = substr($padded, 0, -$decimals);
        $fracPart = rtrim(substr($padded, -$decimals), '0') ?: '0';

        return "{$intPart}.{$fracPart}";
    }
}
