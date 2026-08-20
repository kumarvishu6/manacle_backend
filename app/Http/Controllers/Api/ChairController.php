<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chair;
use App\Models\Salon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChairController extends Controller
{
    /**
     * List all chairs for a given salon.
     */
    public function index(Salon $salon)
    {
        return response()->json($salon->chairs);
    }

    /**
     * Add a new chair to a salon. Only the salon's owner (or super_admin) can do this.
     */
    public function store(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chair = Chair::create([
            'salon_id' => $salon->id,
            'label' => $request->label,
            'status' => 'idle',
        ]);

        return response()->json($chair, 201);
    }

    /**
     * Update a chair (e.g. rename it, or manually change status).
     */
    public function update(Request $request, Chair $chair)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $chair->salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string|max:50',
            'status' => 'sometimes|in:idle,occupied',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chair->update($request->only(['label', 'status']));

        return response()->json($chair);
    }

    /**
     * Remove a chair from a salon.
     */
    public function destroy(Request $request, Chair $chair)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $chair->salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $chair->delete();

        return response()->json(['message' => 'Chair deleted successfully']);
    }
}