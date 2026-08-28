<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salon;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StaffController extends Controller
{
    /**
     * List staff members for a salon. Owner/super_admin only.
     */
    public function index(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $staff = Staff::where('salon_id', $salon->id)
            ->with(['user:id,name,phone', 'chair:id,label'])
            ->get();

        return response()->json($staff);
    }

    /**
     * Add a staff member to a salon by phone number. If that phone doesn't
     * have an account yet, one is created. The staff member then logs into
     * the dashboard using the same OTP flow as everyone else.
     */
    public function store(Request $request, Salon $salon)
    {
        $user = $request->user();

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $staffUser = User::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => $request->name, 'role' => 'staff']
        );

        // Only promote to staff if they're currently a plain customer —
        // never downgrade an owner/super_admin/existing staff account.
        if ($staffUser->role === 'customer') {
            $staffUser->role = 'staff';
            $staffUser->save();
        }

        $existing = Staff::where('user_id', $staffUser->id)
            ->where('salon_id', $salon->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'This person is already staff at this salon.'], 422);
        }

        $staff = Staff::create([
            'user_id' => $staffUser->id,
            'salon_id' => $salon->id,
        ]);

        return response()->json([
            'staff' => $staff,
            'user' => $staffUser,
        ], 201);
    }

    /**
     * Remove a staff member from a salon.
     */
    public function destroy(Request $request, Staff $staff)
    {
        $user = $request->user();
        $salon = Salon::find($staff->salon_id);

        if ($user->role !== 'super_admin' && $salon->owner_id !== $user->id) {
            return response()->json(['message' => 'You do not own this salon.'], 403);
        }

        $staff->delete();

        return response()->json(['message' => 'Staff member removed successfully']);
    }
}