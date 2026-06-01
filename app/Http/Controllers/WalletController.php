<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    // Add funds to a tenant's wallet (Admin only)
    public function addFunds(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $user = User::find($request->user_id);
        $admin = Auth::user();

        // Check if admin or accounting staff
        if (!in_array($admin->role, ['admin', 'super_admin', 'accounting_staff'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Update wallet balance
            $user->wallet_balance += $request->amount;
            $user->save();

            // Create transaction record
            WalletTransaction::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'type' => 'credit',
                'description' => $request->description ?? 'Funds added by admin',
                'admin_id' => $admin->id,
            ]);

            // Notify the tenant
            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'wallet_funded',
                'title' => 'Wallet Funded',
                'message' => 'Your wallet has been credited with ₦' . number_format($request->amount, 2) . ' by ' . $admin->name,
                'read' => false,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Funds added successfully',
                'new_balance' => $user->wallet_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add funds'], 500);
        }
    }

    // Get wallet transactions for a user
    public function getTransactions(Request $request, $userId = null)
    {
        $user = Auth::user();
        
        // If tenant, only get their own transactions
        if ($user->role === 'tenant') {
            $userId = $user->id;
        } elseif (!$userId) {
            return response()->json(['message' => 'User ID required'], 400);
        }

        $transactions = WalletTransaction::where('user_id', $userId)
            ->with('admin:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($transactions);
    }

    // Get wallet balance
    public function getBalance($userId = null)
    {
        $user = Auth::user();
        
        // If tenant, only get their own balance
        if ($user->role === 'tenant') {
            $userId = $user->id;
        } elseif (!$userId) {
            return response()->json(['message' => 'User ID required'], 400);
        }

        $targetUser = User::find($userId);
        
        if (!$targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        return response()->json([
            'balance' => $targetUser->wallet_balance,
        ]);
    }
}
