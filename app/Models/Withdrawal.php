<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use App\Services\EthereumRpcService;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;
use Override;
use RuntimeException;

class Withdrawal extends Model
{
    private const string ERC20_TRANSFER_SELECTOR = 'a9059cbb';
    private const int HOT_WALLET_ACCOUNT = 0;
    private const int HOT_WALLET_CHANGE = 0;
    private const int HOT_WALLET_INDEX = 0;
    private const int NATIVE_GAS_LIMIT = 21_000;
    private const int ERC20_GAS_LIMIT = 90_000;

    protected $fillable = [
        'gate_id',
        'to_address',
        'amount',
        'amount_raw',
        'status',
        'signed_tx',
        'tx_hash',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'status' => WithdrawalStatus::class,
        ];
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /**
     * Создаёт вывод из валидированного запроса: проверяет адрес через wallet-service,
     * переводит сумму в базовые единицы, сохраняет строку в статусе CREATED.
     * Следующий шаг: broadcast().
     *
     * @param  array{asset_gate: string, to_address: string, amount: string}  $data
     */
    public static function createFromRequest(array $data, WalletService $wallet): self
    {
        $gate = Gate::where('name', $data['asset_gate'])->first();
        if (! $gate) {
            throw new InvalidArgumentException("Gate '{$data['asset_gate']}' not found");
        }

        if (! $wallet->validateAddress($gate->getWalletGateName(), $data['to_address'])) {
            throw new InvalidArgumentException('Invalid to_address');
        }

        $amountRaw = bcmul($data['amount'], bcpow('10', (string) $gate->getDecimals(), 0), 0);

        return self::create([
            'gate_id' => $gate->id,
            'to_address' => $data['to_address'],
            'amount' => $data['amount'],
            'amount_raw' => $amountRaw,
            'status' => WithdrawalStatus::Created,
        ]);
    }

    /**
     * Подписывает и отправляет транзакцию. Статус → BROADCASTED при успехе, FAILED при ошибке.
     */
    public function broadcast(WalletService $wallet, EthereumRpcService $rpc): void
    {
        try {
            $signed = $this->signWith($wallet, $rpc);
            $txHash = $rpc->sendRawTransaction($this->gate->getRpcUrl(), $signed['signed_tx']);

            $this->update([
                'signed_tx' => $signed['signed_tx'],
                'tx_hash' => $txHash,
                'status' => WithdrawalStatus::Broadcasted,
            ]);
        } catch (RuntimeException $e) {
            $this->update(['status' => WithdrawalStatus::Failed]);
            throw $e;
        }
    }

    public function toResponse(): array
    {
        return [
            'id' => $this->id,
            'asset_gate' => $this->gate->name,
            'amount' => $this->amount,
            'amount_base_units' => $this->amount_raw,
            'status' => $this->status->value,
            'tx_hash' => $this->tx_hash,
        ];
    }

    /**
     * @return array{signed_tx: string, tx_hash: string}
     */
    private function signWith(WalletService $wallet, EthereumRpcService $rpc): array
    {
        $gate = $this->gate;
        $baseGate = $gate->getBaseGate();
        $walletGate = $gate->getWalletGateName();
        $rpcUrl = $baseGate->rpc_url;

        $fromAddress = $wallet->createAddress(
            $walletGate,
            self::HOT_WALLET_ACCOUNT,
            self::HOT_WALLET_CHANGE,
            self::HOT_WALLET_INDEX,
        );
        $nonce = $rpc->getTransactionCount($rpcUrl, $fromAddress);
        $gas = $rpc->getGasParams($rpcUrl);

        $txParams = match (true) {
            $gate->isNative() => [
                'to' => $this->to_address,
                'value_wei' => $this->amount_raw,
                'data' => '0x',
                'gas_limit' => self::NATIVE_GAS_LIMIT,
            ],
            $gate->isErc20() => [
                'to' => $gate->token_contract,
                'value_wei' => '0',
                'data' => self::erc20TransferCalldata($this->to_address, $this->amount_raw),
                'gas_limit' => self::ERC20_GAS_LIMIT,
            ],
        };

        return $wallet->signTransaction(
            $walletGate,
            self::HOT_WALLET_ACCOUNT,
            self::HOT_WALLET_CHANGE,
            self::HOT_WALLET_INDEX,
            [
                ...$txParams,
                'nonce' => (int) $nonce,
                'chain_id' => (int) $baseGate->chain_id,
                'max_fee_per_gas_wei' => $gas['max_fee_per_gas_wei'],
                'max_priority_fee_per_gas_wei' => $gas['max_priority_fee_per_gas_wei'],
            ],
        );
    }

    private static function erc20TransferCalldata(string $toAddress, string $amountRaw): string
    {
        return '0x' . self::ERC20_TRANSFER_SELECTOR
            . str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT)
            . str_pad(gmp_strval(gmp_init($amountRaw, 10), 16), 64, '0', STR_PAD_LEFT);
    }
}
