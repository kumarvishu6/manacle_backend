<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalonController extends Controller
{
    /**
     * List salons. Customers see only active ones.
     * Salon owners see only their own (any status).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'salon_owner') {
            $salons = Salon::where('owner_id', $user->id)->get();
        } else {
            $salons = Salon::where('status', 'active')->get();
        }

        return response()->json($salons);
    }

    /**
     * Create a new salon. Only salon_owner or super_admin can do this.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'type' => 'required|in:own_franchise,partner_salon',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $salon = Salon::create([
            'owner_id' => $request->user()->id,
            'name' => $request->name,
            'address' => $request->address,
            'type' => $request->type,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'phone' => $request->phone,
            'status' => 'pending', // super_admin approves later
        ]);

        return response()->json($salon, 201);
    }

    /**
     * Show a single salon with its chairs and services.
     */
    public function show(Salon $salon)
    {
        $salon->load(['chairs', 'services']);

        return response()->json($salon);
    }

    /**
     * Update a salon. Only the owner (or super_admin) can update it.
     */
    public function update(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'phone' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $salon->update($request->only(['name', 'address', 'latitude', 'longitude', 'phone']));

        return response()->json($salon);
    }

    /**
     * Delete a salon. Only the owner (or super_admin) can delete it.
     */
    public function destroy(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $salon->delete();

        return response()->json(['message' => 'Salon deleted successfully']);
    }
}