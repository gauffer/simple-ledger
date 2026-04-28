<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\EthereumRpcService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class WithdrawalController extends Controller
{
    public function store(Request $request, WalletService $wallet, EthereumRpcService $rpc): JsonResponse
    {
        $data = $request->validate([
            'asset_gate' => 'required|string',
            'to_address' => 'required|string',
            'amount' => 'required|string|regex:/^\d+(\.\d+)?$/',
        ]);

        try {
            $withdrawal = Withdrawal::createFromRequest($data, $wallet);
            $withdrawal->broadcast($wallet, $rpc);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['error' => "Broadcast failed: {$e->getMessage()}"], 500);
        }

        return response()->json($withdrawal->toResponse());
    }
}
