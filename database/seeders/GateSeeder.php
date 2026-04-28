<?php

namespace Database\Seeders;

use App\Enums\AssetType;
use App\Models\Gate;
use Illuminate\Database\Seeder;

class GateSeeder extends Seeder
{
    public function run(): void
    {
        $eth = Gate::updateOrCreate(
            ['name' => 'eth_sepolia'],
            [
                'rpc_url' => env('ETH_SEPOLIA_RPC_URL'),
                'chain_id' => 11155111,
                'confirmations_required' => 12,
                'parent_gate_id' => null,
                'asset_type' => AssetType::Native,
                'token_contract' => null,
                'token_decimals' => null,
            ],
        );

        Gate::updateOrCreate(
            ['name' => 'usdc_sepolia'],
            [
                'rpc_url' => null,
                'chain_id' => 11155111,
                'confirmations_required' => 12,
                'parent_gate_id' => $eth->id,
                'asset_type' => AssetType::Erc20,
                'token_contract' => env('USDC_SEPOLIA_CONTRACT', '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238'),
                'token_decimals' => 6,
            ],
        );
    }
}
