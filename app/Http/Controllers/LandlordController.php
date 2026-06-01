<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LandlordController extends Controller
{
    /**
     * Display a listing of landlords.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Only super admin and admin can view all landlords
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $landlords = User::where('role', 'landlord')
            ->withCount('properties')
            ->with('properties')
            ->get();
        
        return response()->json($landlords);
    }

    /**
     * Store a newly created landlord.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            // Property validation
            'property_name' => 'nullable|string|max:255',
            'property_address' => 'nullable|required_with:property_name|string|max:255',
            'property_type' => 'nullable|string',
            'property_description' => 'nullable|string',
            'property_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            // Unit validation
            'unit_number' => 'nullable|required_with:property_name|string',
            'unit_rent' => 'nullable|required_with:property_name|numeric|min:0',
            'unit_type' => 'nullable|string',
            'unit_floor' => 'nullable|integer',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $landlord = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'landlord',
            'phone' => $request->phone,
            'company_name' => $request->company_name,
        ]);

        // Create Property if provided
        if ($request->filled('property_name')) {
            $imagePaths = [];
            if ($request->hasFile('property_images')) {
                $images = $request->file('property_images');
                foreach ($images as $image) {
                    $path = $image->store('properties', 'public');
                    $imagePaths[] = $path;
                }
            }

            $property = Property::create([
                'name' => $request->property_name,
                'address' => $request->property_address,
                'type' => $request->property_type ?? 'Residential',
                'status' => 'Active',
                'units_count' => 1, // Start with 1 unit
                'landlord_id' => $landlord->id,
                'description' => $request->property_description,
                'images' => $imagePaths, // Cast will handle json_encode
            ]);

            // Create Unit if provided and property was created
            if ($request->filled('unit_number')) {
                $property->units()->create([
                    'unit_number' => $request->unit_number,
                    'rent_amount' => $request->unit_rent,
                    'type' => $request->unit_type ?? 'Standard',
                    'floor' => $request->unit_floor,
                    'status' => 'Vacant',
                ]);
            }
        }
        
        return response()->json($landlord, 201);
    }

    /**
     * Display the specified landlord.
     */
    public function show($id)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $landlord = User::where('role', 'landlord')
            ->where('id', $id)
            ->withCount('properties')
            ->with(['properties.units'])
            ->firstOrFail();
        
        return response()->json($landlord);
    }

    /**
     * Update the specified landlord.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $landlord = User::where('role', 'landlord')->findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $data = $request->only(['name', 'email', 'phone', 'company_name']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        
        $landlord->update($data);
        
        return response()->json($landlord);
    }

    /**
     * Remove the specified landlord.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        
        if ($user->role !== 'super_admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $landlord = User::where('role', 'landlord')->findOrFail($id);
        
        // Check if landlord has properties
        if ($landlord->properties()->count() > 0) {
            return response()->json([
                'error' => 'Cannot delete landlord with assigned properties. Please reassign or remove properties first.'
            ], 422);
        }
        
        $landlord->delete();
        
        return response()->json(['message' => 'Landlord deleted successfully']);
    }

    /**
     * Assign a property to a landlord.
     */
    public function assignProperty(Request $request, $id)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['super_admin', 'admin'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $landlord = User::where('role', 'landlord')->findOrFail($id);
        $property = Property::findOrFail($request->property_id);
        
        $property->update(['landlord_id' => $landlord->id]);
        
        return response()->json([
            'message' => 'Property assigned successfully',
            'property' => $property,
        ]);
    }

    /**
     * Get properties for a specific landlord.
     */
    public function properties($id)
    {
        $user = Auth::user();
        
        if (!in_array($user->role, ['super_admin', 'admin']) && $user->id != $id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $properties = Property::where('landlord_id', $id)
            ->withCount(['units', 'units as occupied_units_count' => function ($query) {
                $query->where('status', 'Occupied');
            }])
            ->get();
        
        return response()->json($properties);
    }
}
