<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Gate;
use App\Services\WalletService;
use Illuminate\Console\Command;

class AddressCreate extends Command
{
    protected $signature = 'address:create
        {--base_gate= : BASE gate name}
        {--account=0 : HD account}
        {--change=0 : HD change}
        {--address_index= : HD address index (auto-picks next available if omitted)}';

    protected $description = 'Create an address via the wallet service';

    public function handle(WalletService $wallet): int
    {
        $name = $this->option('base_gate');
        if (! $name) {
            $this->error('--base_gate is required');
            return self::FAILURE;
        }

        $baseGate = Gate::where('name', $name)->first();
        if (! $baseGate || $baseGate->parent_gate_id !== null) {
            $this->error("BASE gate '{$name}' not found");
            return self::FAILURE;
        }

        $indexOpt = $this->option('address_index');
        $address = Address::deriveFor(
            $baseGate,
            $wallet,
            account: (int) $this->option('account'),
            change: (int) $this->option('change'),
            addressIndex: $indexOpt === null ? null : (int) $indexOpt,
        );

        $this->info("{$address->address} (m/44'/60'/{$address->account}'/{$address->change}/{$address->address_index})");

        return self::SUCCESS;
    }
}
