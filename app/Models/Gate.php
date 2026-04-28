<?php

namespace App\Models;

use App\Enums\AssetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class Gate extends Model
{
    private const int NATIVE_DECIMALS = 18;

    protected $fillable = [
        'name',
        'rpc_url',
        'chain_id',
        'confirmations_required',
        'parent_gate_id',
        'asset_type',
        'token_contract',
        'token_decimals',
    ];

    #[Override]
    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'chain_id' => 'integer',
            'confirmations_required' => 'integer',
            'token_decimals' => 'integer',
        ];
    }

    public function parentGate(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_gate_id');
    }

    public function childGates(): HasMany
    {
        return $this->hasMany(self::class, 'parent_gate_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function isNative(): bool
    {
        return $this->asset_type === AssetType::Native;
    }

    public function isErc20(): bool
    {
        return $this->asset_type === AssetType::Erc20;
    }

    /**
     * BASE-шлюз: сам объект для NATIVE (parent_gate_id = null), иначе родитель.
     */
    public function getBaseGate(): self
    {
        return $this->parent_gate_id ? $this->parentGate : $this;
    }

    public function getDecimals(): int
    {
        return $this->isNative() ? self::NATIVE_DECIMALS : ($this->token_decimals ?? self::NATIVE_DECIMALS);
    }

    public function getRpcUrl(): string
    {
        return $this->getBaseGate()->rpc_url;
    }

    /**
     * Имя шлюза для Go wallet-service (например, "ethereum"): берётся из конфига, не из модели.
     */
    public function getWalletGateName(): string
    {
        return config('services.wallet.gate_name');
    }
}
