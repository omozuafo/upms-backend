<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;

// Health check endpoint for Render deployment monitoring
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
});

Route::get('/auth/debug', function () {
    $results = [];
    $admins = [
        'superadmin@upms.com' => 'asdfghj69.',
        'admin@example.com' => 'password',
    ];

    foreach ($admins as $email => $plainPassword) {
        $user = \Illuminate\Support\Facades\DB::table('users')->where('email', $email)->first();
        if ($user) {
            $matchBefore = \Illuminate\Support\Facades\Hash::check($plainPassword, $user->password);
            
            // Force reset if it does not match
            if (!$matchBefore) {
                $newHash = \Illuminate\Support\Facades\Hash::make($plainPassword);
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $user->id)
                    ->update(['password' => $newHash]);
                
                $updatedUser = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->first();
                $matchAfter = \Illuminate\Support\Facades\Hash::check($plainPassword, $updatedUser->password);
            } else {
                $matchAfter = true;
            }

            $results[$email] = [
                'exists' => true,
                'role' => $user->role ?? null,
                'password_hash_prefix' => substr($user->password, 0, 10) . '...',
                'hash_length' => strlen($user->password),
                'matched_initially' => $matchBefore,
                'matched_currently' => $matchAfter,
                'action_taken' => !$matchBefore ? 'Reset password' : 'None',
            ];
        } else {
            // Try to create the user
            $newHash = \Illuminate\Support\Facades\Hash::make($plainPassword);
            \Illuminate\Support\Facades\DB::table('users')->insert([
                'name' => $email === 'superadmin@upms.com' ? 'Super Admin' : 'Admin User',
                'email' => $email,
                'password' => $newHash,
                'role' => $email === 'superadmin@upms.com' ? 'super_admin' : 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $results[$email] = [
                'exists' => false,
                'action_taken' => 'Created user',
                'matched_currently' => true,
            ];
        }
    }

    return response()->json($results);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
    Route::post('check-duplicate', [AuthController::class, 'checkDuplicate']);
});

// Broadcast Auth Route
Broadcast::routes(['middleware' => ['auth:api']]);

// Dashboard stats (requires authentication)
Route::middleware('auth:api')->group(function () {
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);
});


Route::middleware(['auth:api', 'session.active'])->group(function () {
    Route::apiResource('properties', \App\Http\Controllers\PropertyController::class);
    Route::apiResource('units', \App\Http\Controllers\UnitController::class);
    Route::get('tenants/active-context', [\App\Http\Controllers\TenantController::class, 'activeContext']);
    Route::apiResource('tenants', \App\Http\Controllers\TenantController::class);
    Route::apiResource('leases', \App\Http\Controllers\LeaseController::class);
    Route::apiResource('payments', \App\Http\Controllers\PaymentController::class);
    Route::apiResource('expenses', \App\Http\Controllers\ExpenseController::class);
    
    // Issue stats must be defined before apiResource to avoid route conflict
    Route::get('issues/stats', [\App\Http\Controllers\IssueController::class, 'stats']);
    Route::post('issues/{id}/budget', [\App\Http\Controllers\IssueController::class, 'budgetIssue']);
    Route::post('issues/{id}/reject', [\App\Http\Controllers\IssueController::class, 'rejectIssue']);
    Route::post('issues/{id}/account-action', [\App\Http\Controllers\IssueController::class, 'accountAction']);
    Route::apiResource('issues', \App\Http\Controllers\IssueController::class);

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-type', [\App\Http\Controllers\NotificationController::class, 'markTypeAsRead']);
    Route::put('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    
    // Messages
    Route::apiResource('messages', \App\Http\Controllers\MessageController::class);
    
    // Wallet routes
    Route::post('/wallet/add-funds', [\App\Http\Controllers\WalletController::class, 'addFunds']);
    Route::get('/wallet/transactions/{userId?}', [\App\Http\Controllers\WalletController::class, 'getTransactions']);
    Route::get('/wallet/balance/{userId?}', [\App\Http\Controllers\WalletController::class, 'getBalance']);
    
    // User management routes (Super Admin only)
    Route::get('users/stats', [\App\Http\Controllers\UserController::class, 'stats']);
    Route::apiResource('users', \App\Http\Controllers\UserController::class);

    // Landlord management routes
    Route::get('landlords/{id}/properties', [\App\Http\Controllers\LandlordController::class, 'properties']);
    Route::post('landlords/{id}/assign-property', [\App\Http\Controllers\LandlordController::class, 'assignProperty']);
    Route::apiResource('landlords', \App\Http\Controllers\LandlordController::class);

    // Audit Trail
    Route::get('audit-trail', [\App\Http\Controllers\PaymentController::class, 'auditTrail']);
});
