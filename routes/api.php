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
    
    // 1. Test database connection
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $results['db_connection'] = 'OK';
    } catch (\Exception $e) {
        $results['db_connection'] = 'FAIL: ' . $e->getMessage();
    }

    // 2. Try simulated registration (with rollback)
    try {
        \Illuminate\Support\Facades\DB::beginTransaction();
        
        $tempEmail = 'temp_' . time() . '@example.com';
        $tempUser = \App\Models\User::create([
            'name' => 'Temp User',
            'username' => 'tempuser' . time(),
            'email' => $tempEmail,
            'phone' => '1234567890',
            'password' => \Illuminate\Support\Facades\Hash::make('Password123!'),
            'role' => 'tenant',
        ]);
        
        $results['test_registration'] = [
            'success' => true,
            'user_id' => $tempUser->id,
        ];
        
        \Illuminate\Support\Facades\DB::rollBack();
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\DB::rollBack();
        $results['test_registration'] = [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => substr($e->getTraceAsString(), 0, 500)
        ];
    }

    $admins = [
        'superadmin@upms.com' => ['password' => 'asdfghj69.', 'role' => 'super_admin', 'name' => 'Super Admin'],
        'admin@example.com' => ['password' => 'password', 'role' => 'admin', 'name' => 'Admin User'],
        'accounting@upms.com' => ['password' => 'password', 'role' => 'accounting_staff', 'name' => 'Accounting Staff'],
        'maintenance@upms.com' => ['password' => 'password', 'role' => 'maintenance_staff', 'name' => 'Maintenance Staff'],
    ];

    foreach ($admins as $email => $data) {
        $plainPassword = $data['password'];
        $role = $data['role'];
        $name = $data['name'];

        $user = \Illuminate\Support\Facades\DB::table('users')->where('email', $email)->first();
        if ($user) {
            $matchBefore = \Illuminate\Support\Facades\Hash::check($plainPassword, $user->password);
            
            // Force reset if it does not match or role mismatches
            if (!$matchBefore || $user->role !== $role) {
                $newHash = \Illuminate\Support\Facades\Hash::make($plainPassword);
                \Illuminate\Support\Facades\DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'password' => $newHash,
                        'role' => $role
                    ]);
                
                $updatedUser = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->first();
                $matchAfter = \Illuminate\Support\Facades\Hash::check($plainPassword, $updatedUser->password);
            } else {
                $matchAfter = true;
            }

            // Attempt login via the api guard
            $tokenAttempt = null;
            $attemptError = null;
            try {
                $tokenAttempt = \Illuminate\Support\Facades\Auth::guard('api')->attempt([
                    'email' => $email,
                    'password' => $plainPassword
                ]);
            } catch (\Exception $e) {
                $attemptError = $e->getMessage();
            }

            $results[$email] = [
                'exists' => true,
                'role' => $user->role ?? null,
                'password_hash_prefix' => substr($user->password, 0, 10) . '...',
                'hash_length' => strlen($user->password),
                'matched_initially' => $matchBefore,
                'matched_currently' => $matchAfter,
                'attempt_login_success' => $tokenAttempt ? true : false,
                'attempt_login_token_prefix' => $tokenAttempt ? substr($tokenAttempt, 0, 15) . '...' : null,
                'attempt_login_error' => $attemptError,
                'action_taken' => !$matchBefore ? 'Reset password' : 'None',
            ];
        } else {
            // Try to create the user
            $newHash = \Illuminate\Support\Facades\Hash::make($plainPassword);
            \Illuminate\Support\Facades\DB::table('users')->insert([
                'name' => $name,
                'email' => $email,
                'password' => $newHash,
                'role' => $role,
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
