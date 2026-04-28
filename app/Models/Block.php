<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Services\EthereumRpcService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Block extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'gate_id',
        'block_number',
        'block_hash',
        'parent_hash',
    ];

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    /**
     * Индексирует блоки от last_indexed+1 до latest для BASE-шлюза.
     * При обнаружении реорга откатывает невалидные блоки, затем записывает native- и ERC-20-депозиты.
     *
     * @param  Closure(string $message): void|null  $log
     */
    public static function indexFor(Gate $baseGate, EthereumRpcService $rpc, ?Closure $log = null): void
    {
        $log ??= fn (string $_) => null;

        $rpcUrl = $baseGate->rpc_url;
        $addresses = Address::where('gate_id', $baseGate->id)
            ->get()
            ->keyBy(fn (Address $a): string => strtolower($a->address));

        if ($addresses->isEmpty()) {
            $log('No addresses to monitor. Create addresses first.');
            return;
        }

        $erc20Gates = Gate::where('parent_gate_id', $baseGate->id)
            ->where('asset_type', AssetType::Erc20)
            ->get()
            ->keyBy(fn (Gate $g): string => strtolower($g->token_contract));

        $lastBlock = self::where('gate_id', $baseGate->id)->orderByDesc('block_number')->first();
        $startBlock = $lastBlock ? bcadd($lastBlock->block_number, '1') : $rpc->getBlockNumber($rpcUrl);
        $latestBlock = $rpc->getBlockNumber($rpcUrl);

        if (bccomp($startBlock, $latestBlock) > 0) {
            $log('Already up to date.');
            return;
        }

        $log("Indexing blocks {$startBlock} → {$latestBlock}");

        for ($num = $startBlock; bccomp($num, $latestBlock) <= 0; $num = bcadd($num, '1')) {
            $blockData = $rpc->getBlockByNumber($rpcUrl, $num);
            if (! $blockData) {
                $log("Block {$num} not found, skipping");
                continue;
            }

            if ($lastBlock && strtolower($blockData['parentHash']) !== strtolower($lastBlock->block_hash)) {
                $log("Reorg detected at block {$num}");
                $num = self::rollbackReorg($baseGate, $rpc, $num, $log);
                $lastBlock = self::where('gate_id', $baseGate->id)->orderByDesc('block_number')->first();
                continue;
            }

            $block = self::updateOrCreate(
                ['gate_id' => $baseGate->id, 'block_number' => $num],
                ['block_hash' => $blockData['hash'], 'parent_hash' => $blockData['parentHash']],
            );

            Transaction::recordNativeDeposits($block, $blockData, $addresses, $baseGate);
            if ($erc20Gates->isNotEmpty()) {
                Transaction::recordErc20Deposits($block, $rpc, $rpcUrl, $blockData, $addresses, $erc20Gates);
            }

            $lastBlock = $block;
        }
    }

    /**
     * Идёт назад от $forkBlock-1, удаляя блоки, чей хэш расходится с канонической цепью.
     * Возвращает номер последнего валидного блока, с которого можно продолжить.
     *
     * @param  Closure(string): void  $log
     */
    private static function rollbackReorg(Gate $baseGate, EthereumRpcService $rpc, string $forkBlock, Closure $log): string
    {
        $current = bcsub($forkBlock, '1');

        while (bccomp($current, '0') > 0) {
            $savedBlock = self::where('gate_id', $baseGate->id)->where('block_number', $current)->first();
            if (! $savedBlock) {
                break;
            }

            $chainBlock = $rpc->getBlockByNumber($baseGate->rpc_url, $current);
            if ($chainBlock && strtolower($chainBlock['hash']) === strtolower($savedBlock->block_hash)) {
                break;
            }

            $log("  Removing invalid block {$current}");

            $gateIds = Gate::where('id', $baseGate->id)
                ->orWhere('parent_gate_id', $baseGate->id)
                ->pluck('id');

            Transaction::whereIn('gate_id', $gateIds)->where('block_number', $current)->delete();
            $savedBlock->delete();

            $current = bcsub($current, '1');
        }

        $log('  Reorg resolved, resuming from block ' . bcadd($current, '1'));
        return $current;
    }
}
