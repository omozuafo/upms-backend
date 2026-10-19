<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware handled in routes or explicitly here
        // $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get available roles for user registration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableRegistrationRoles()
    {
        $adminExists = User::whereIn('role', ['admin', 'super_admin'])->exists();
        $maintenanceExists = User::whereIn('role', ['maintenance', 'maintenance_staff'])->exists();
        $accountantExists = User::whereIn('role', ['accountant', 'accounting_staff'])->exists();

        $roles = [];
        if (!$adminExists) {
            $roles[] = ['value' => 'admin', 'label' => 'Admin'];
        }
        if (!$maintenanceExists) {
            $roles[] = ['value' => 'maintenance', 'label' => 'Maintenance'];
        }
        if (!$accountantExists) {
            $roles[] = ['value' => 'accountant', 'label' => 'Accountant'];
        }
        $roles[] = ['value' => 'landlord', 'label' => 'Landlord'];
        $roles[] = ['value' => 'tenant', 'label' => 'Tenant'];

        return response()->json([
            'roles' => $roles,
            'summary' => [
                'admin_available' => !$adminExists,
                'maintenance_available' => !$maintenanceExists,
                'accountant_available' => !$accountantExists,
            ]
        ]);
    }

    /**
     * Register a new user.
     *
     * @param RegisterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        $rawRole = $validated['role'];
        $canonicalRole = match ($rawRole) {
            'maintenance', 'maintenance_staff' => 'maintenance_staff',
            'accountant', 'accounting_staff' => 'accounting_staff',
            default => $rawRole,
        };

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'role' => $canonicalRole,
        ]);

        $token = Auth::guard('api')->login($user);

        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    /**
     * Check if a field value already exists in the database.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDuplicate(Request $request)
    {
        $field = $request->input('field');
        $value = $request->input('value');

        if (!in_array($field, ['email', 'phone', 'username'])) {
            return response()->json(['error' => 'Invalid field'], 400);
        }

        if ($field === 'email') {
            $value = strtolower(trim($value));
        } else {
            $value = trim($value);
        }

        $exists = User::where($field, $value)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $email = strtolower($credentials['email']);
        $password = $credentials['password'];

        // Log login attempt and password check for debugging
        $user = User::where('email', $email)->first();
        if ($user) {
            $passwordMatches = Hash::check($password, $user->password);
            \Log::info("Login attempt - User found: {$email}. Password matches: " . ($passwordMatches ? 'YES' : 'NO'));
        } else {
            \Log::info("Login attempt - User NOT found: {$email}");
        }

        if (! $token = Auth::guard('api')->attempt(['email' => $email, 'password' => $password])) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::guard('api')->user();
        
        try {
            // Generate Unique Session Key
            $sessionKey = (string) \Illuminate\Support\Str::uuid();
            
            // Create Session Record
            try {
                \App\Models\UserSession::create([
                    'user_id' => $user->id,
                    'session_key' => $sessionKey,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'expires_at' => now()->addMinutes(config('jwt.ttl', 60)),
                    'is_active' => true,
                ]);
            } catch (\Exception $e) {
                \Log::warning('UserSession creation failed: ' . $e->getMessage());
            }

            // Add session_key to user for JWT claim
            $user->session_key = $sessionKey;
            
            // Generate token with custom claims
            $token = Auth::guard('api')->claims(['session_key' => $sessionKey])->login($user);

            return $this->respondWithToken($token);
        } catch (\Exception $e) {
            \Log::error('Login processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Login processing error', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        $payload = auth('api')->payload();
        $sessionKey = $payload->get('session_key');

        if ($sessionKey) {
            \App\Models\UserSession::where('session_key', $sessionKey)->update(['is_active' => false]);
        }

        Auth::guard('api')->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::guard('api')->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
            'user' => Auth::guard('api')->user()
        ]);
    }
}
