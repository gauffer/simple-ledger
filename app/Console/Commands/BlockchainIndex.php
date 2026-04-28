<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\Gate;
use App\Services\EthereumRpcService;
use Illuminate\Console\Command;

class BlockchainIndex extends Command
{
    protected $signature = 'blockchain:index {--base_gate=}';

    protected $description = 'Index incoming transactions (ETH + ERC-20)';

    public function handle(EthereumRpcService $rpc): int
    {
        $name = $this->option('base_gate');
        if (! $name) {
            $this->error('--base_gate is required');
            return self::FAILURE;
        }

        $baseGate = Gate::where('name', $name)->first();
        if (! $baseGate) {
            $this->error("Gate '{$name}' not found");
            return self::FAILURE;
        }

        Block::indexFor($baseGate, $rpc, fn (string $msg) => $this->info($msg));

        return self::SUCCESS;
    }
}
