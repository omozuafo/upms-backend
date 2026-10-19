<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;

// Health check endpoint for Render deployment monitoring
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toISOString()]);
});

// Diagnostic DB check endpoint
Route::get('/debug-db', function () {
    try {
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbName = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        
        $hasUsersTable = \Illuminate\Support\Facades\Schema::hasTable('users');
        $migrated = false;
        if (!$hasUsersTable || request()->query('migrate') === 'true') {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            \Illuminate\Support\Facades\Artisan::call('admin:ensure');
            $hasUsersTable = \Illuminate\Support\Facades\Schema::hasTable('users');
            $migrated = true;
        }

        $userCount = $hasUsersTable ? \Illuminate\Support\Facades\DB::table('users')->count() : 0;
        $superAdmin = $hasUsersTable ? \Illuminate\Support\Facades\DB::table('users')->where('email', 'figuriad@gmail.com')->first() : null;

        return response()->json([
            'status' => 'connected',
            'driver' => $driver,
            'database' => $dbName,
            'has_users_table' => $hasUsersTable,
            'migrated' => $migrated,
            'user_count' => $userCount,
            'super_admin_exists' => (bool) $superAdmin,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'driver' => config('database.default'),
            'error_class' => get_class($e),
            'message' => $e->getMessage(),
        ], 500);
    }
});

Route::group([
    'middleware' => ['api'],
    'prefix' => 'auth'
], function ($router) {
    Route::get('registration-roles', [AuthController::class, 'getAvailableRegistrationRoles']);
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
    Route::post('check-duplicate', [AuthController::class, 'checkDuplicate'])->middleware('throttle:auth');
});

// Broadcast Auth Route
Broadcast::routes(['middleware' => ['auth:api', 'throttle:api']]);

// Dashboard stats (requires authentication)
Route::middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::get('/dashboard/stats', [\App\Http\Controllers\DashboardController::class, 'stats']);
});

Route::middleware(['auth:api', 'session.active', 'throttle:api'])->group(function () {
    Route::apiResource('properties', \App\Http\Controllers\PropertyController::class);
    Route::apiResource('units', \App\Http\Controllers\UnitController::class);
    Route::get('tenants/active-context', [\App\Http\Controllers\TenantController::class, 'activeContext']);
    Route::apiResource('tenants', \App\Http\Controllers\TenantController::class);
    Route::apiResource('leases', \App\Http\Controllers\LeaseController::class);
    Route::apiResource('payments', \App\Http\Controllers\PaymentController::class);
    Route::post('expenses/{id}/approve', [\App\Http\Controllers\ExpenseController::class, 'approve']);
    Route::post('expenses/{id}/reject', [\App\Http\Controllers\ExpenseController::class, 'reject']);
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
