<?php

namespace App\Models;

use App\Services\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = [
        'gate_id',
        'account',
        'change',
        'address_index',
        'address',
    ];

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /**
     * Выводит HD-адрес для BASE-шлюза по заданным компонентам пути.
     * Если строка с таким путём уже есть, возвращает её.
     * Если $addressIndex равен null, берёт MAX(address_index) + 1 для тройки (gate, account, change).
     */
    public static function deriveFor(
        Gate $baseGate,
        WalletService $wallet,
        int $account = 0,
        int $change = 0,
        ?int $addressIndex = null,
    ): self {
        $addressIndex ??= self::nextIndex($baseGate->id, $account, $change);

        $existing = self::where('gate_id', $baseGate->id)
            ->where('account', $account)
            ->where('change', $change)
            ->where('address_index', $addressIndex)
            ->first();

        if ($existing) {
            return $existing;
        }

        $address = $wallet->createAddress($baseGate->getWalletGateName(), $account, $change, $addressIndex);

        return self::create([
            'gate_id' => $baseGate->id,
            'account' => $account,
            'change' => $change,
            'address_index' => $addressIndex,
            'address' => $address,
        ]);
    }

    /**
     * MAX(address_index) + 1 для тройки (gate, account, change), или 0 если адресов ещё нет.
     */
    public static function nextIndex(int $gateId, int $account, int $change): int
    {
        $max = self::where('gate_id', $gateId)
            ->where('account', $account)
            ->where('change', $change)
            ->max('address_index');

        return $max === null ? 0 : (int) $max + 1;
    }
}
