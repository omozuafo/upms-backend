<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
     * Register a new user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // 1. Sanitize inputs
        $input = $request->all();
        $sanitized = [];
        
        $fieldsToSanitize = ['name', 'email', 'phone', 'password', 'password_confirmation', 'role', 'username'];
        foreach ($fieldsToSanitize as $field) {
            if (isset($input[$field])) {
                $val = trim($input[$field]);
                if ($field !== 'password' && $field !== 'password_confirmation') {
                    $val = htmlspecialchars(strip_tags($val));
                }
                if ($field === 'email') {
                    $val = strtolower($val);
                }
                $sanitized[$field] = $val;
            }
        }
        $request->replace($sanitized);

        $messages = [
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'phone.regex' => 'Phone number must be a valid numeric format, 10–15 digits.',
            'phone.unique' => 'This phone number is already in use.',
            'username.regex' => 'Username must be alphanumeric only, 3–20 characters, no spaces.',
            'username.unique' => 'This username is already taken.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
        ];

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => ['required', 'string', 'regex:/^\d{10,15}$/', 'unique:users,phone'],
            'username' => ['required', 'string', 'regex:/^[a-zA-Z0-9]{3,20}$/', 'unique:users,username'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[^a-zA-Z0-9]/', // Special character
                'confirmed'
            ],
            'role' => 'required|string|in:tenant,landlord',
        ], $messages);

        if($validator->fails()){
            return response()->json($validator->errors(), 400); // Send specific field errors back
        }

        $user = User::create([
            'name' => $request->get('name'),
            'username' => $request->get('username'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'password' => Hash::make($request->get('password')),
            'role' => $request->get('role'),
        ]);

        $token = Auth::login($user);

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
    public function login(Request $request)
    {
        $credentials = request(['email', 'password']);

        if (! $token = Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = Auth::user();
        
        // Generate Unique Session Key
        $sessionKey = (string) \Illuminate\Support\Str::uuid();
        
        // Create Session Record
        \App\Models\UserSession::create([
            'user_id' => $user->id,
            'session_key' => $sessionKey,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addMinutes(auth('api')->factory()->getTTL()),
            'is_active' => true,
        ]);

        // Add session_key to user for JWT claim
        $user->session_key = $sessionKey;
        
        // Generate token with custom claims
        $token = Auth::claims(['session_key' => $sessionKey])->login($user);

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(Auth::user());
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

        Auth::logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
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
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => Auth::user()
        ]);
    }
}
