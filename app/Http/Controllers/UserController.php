<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|string|in:super_admin,admin,property_officer,accounting_staff,maintenance_staff,landlord,tenant',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json($user);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:6',
            'role' => 'sometimes|required|string|in:super_admin,admin,property_officer,accounting_staff,maintenance_staff,landlord,tenant',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        if ($request->has('role')) {
            $user->role = $request->role;
        }

        $user->save();

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy($id)
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Prevent deleting self
        if ($user->id === Auth::id()) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Get user statistics.
     */
    public function stats()
    {
        // Check if user is super admin
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_users' => User::count(),
            'by_role' => [
                'super_admin' => User::where('role', 'super_admin')->count(),
                'admin' => User::where('role', 'admin')->count(),
                'property_officer' => User::where('role', 'property_officer')->count(),
                'accounting_staff' => User::where('role', 'accounting_staff')->count(),
                'maintenance_staff' => User::where('role', 'maintenance_staff')->count(),
                'landlord' => User::where('role', 'landlord')->count(),
                'tenant' => User::where('role', 'tenant')->count(),
            ],
            'recent_users' => User::orderBy('created_at', 'desc')->limit(5)->get(),
        ];

        return response()->json($stats);
    }
}
