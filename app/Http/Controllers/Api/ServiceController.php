<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * List all services for a given salon.
     */
    public function index(Salon $salon)
    {
        return response()->json($salon->services);
    }

    /**
     * Add a new service to a salon. Only the salon's owner (or super_admin) can do this.
     */
    public function store(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'avg_duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::create([
            'salon_id' => $salon->id,
            'name' => $request->name,
            'avg_duration_minutes' => $request->avg_duration_minutes,
            'price' => $request->price,
            'is_active' => true,
        ]);

        return response()->json($service, 201);
    }

    /**
     * Update a service.
     */
    public function update(Request $request, Service $service)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $service->salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:100',
            'avg_duration_minutes' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service->update($request->only(['name', 'avg_duration_minutes', 'price', 'is_active']));

        return response()->json($service);
    }

    /**
     * Delete a service.
     */
    public function destroy(Request $request, Service $service)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $service->salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $service->delete();

        return response()->json(['message' => 'Service deleted successfully']);
    }
}