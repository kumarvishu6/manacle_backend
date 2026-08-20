<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Chair;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(protected QueueService $queueService)
    {
    }

    /**
     * Customer joins the queue for a salon's service.
     */
    public function store(Request $request, Salon $salon)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'service_id' => 'required|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::where('id', $request->service_id)
            ->where('salon_id', $salon->id)
            ->where('is_active', true)
            ->first();

        if (! $service) {
            return response()->json(['message' => 'This service is not available at this salon.'], 422);
        }

        $existing = Booking::where('customer_id', $user->id)
            ->where('salon_id', $salon->id)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You already have an active booking at this salon.'], 422);
        }

        // Estimate BEFORE creating, since this booking isn't in the queue yet
        $estimate = $this->queueService->estimateWait($salon, $service->avg_duration_minutes);

        $booking = Booking::create([
            'customer_id' => $user->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'waiting',
        ]);

        return response()->json([
            'booking' => $booking,
            'estimated_wait_minutes' => $estimate['estimated_wait_minutes'],
            'expected_start_at' => $estimate['expected_start_at'],
            'position_in_queue' => $estimate['position_in_queue'],
        ], 201);
    }

    /**
     * Staff/owner view of the live queue for a salon.
     */
    public function index(Request $request, Salon $salon)
    {
        $this->authorizeQueueAccess($request->user(), $salon);

        $bookings = Booking::where('salon_id', $salon->id)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->with(['customer:id,name,phone', 'service:id,name,avg_duration_minutes', 'chair:id,label'])
            ->orderBy('created_at')
            ->get();

        return response()->json($bookings);
    }

    /**
     * Staff assigns a waiting booking to a chair and starts the service.
     */
    public function start(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        if ($booking->status !== 'waiting') {
            return response()->json(['message' => 'This booking is not waiting.'], 422);
        }

        $validator = Validator::make($request->all(), [
            'chair_id' => 'required|exists:chairs,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chair = Chair::where('id', $request->chair_id)
            ->where('salon_id', $booking->salon_id)
            ->first();

        if (! $chair) {
            return response()->json(['message' => 'Chair not found at this salon.'], 422);
        }

        if ($chair->status !== 'idle') {
            return response()->json(['message' => 'This chair is currently occupied.'], 422);
        }

        $booking->update([
            'status' => 'in_progress',
            'chair_id' => $chair->id,
            'started_at' => now(),
        ]);

        $chair->update([
            'status' => 'occupied',
            'current_booking_id' => $booking->id,
        ]);

        return response()->json($booking->fresh());
    }

    /**
     * Staff marks a booking done, frees the chair, refines the service's avg duration.
     */
    public function complete(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        if ($booking->status !== 'in_progress') {
            return response()->json(['message' => 'This booking is not in progress.'], 422);
        }

        $booking->update([
            'status' => 'done',
            'ended_at' => now(),
        ]);

        if ($booking->chair) {
            $booking->chair->update([
                'status' => 'idle',
                'current_booking_id' => null,
            ]);
        }

        // Self-correcting average: blend the old estimate with what actually happened
        $actualMinutes = $booking->started_at->diffInMinutes($booking->ended_at);
        $service = $booking->service;
        $newAvg = (int) round(($service->avg_duration_minutes + $actualMinutes) / 2);
        $service->update(['avg_duration_minutes' => max(5, $newAvg)]);

        return response()->json($booking->fresh());
    }

    /**
     * Staff marks a customer as a no-show.
     */
    public function noShow(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        if ($booking->status !== 'waiting') {
            return response()->json(['message' => 'This booking is not waiting.'], 422);
        }

        $booking->update(['status' => 'no_show']);

        return response()->json($booking->fresh());
    }

    /**
     * Customer cancels their own booking.
     */
    public function cancel(Request $request, Booking $booking)
    {
        $user = $request->user();

        if ($booking->customer_id !== $user->id) {
            return response()->json(['message' => 'This is not your booking.'], 403);
        }

        if ($booking->status !== 'waiting') {
            return response()->json(['message' => 'This booking can no longer be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json($booking->fresh());
    }

    private function authorizeQueueAccess($user, Salon $salon): void
    {
        $isOwner = $salon->owner_id === $user->id;
        $isStaff = Staff::where('user_id', $user->id)->where('salon_id', $salon->id)->exists();
        $isSuperAdmin = $user->role === 'super_admin';

        if (! $isOwner && ! $isStaff && ! $isSuperAdmin) {
            abort(403, 'You do not have access to this salon\'s queue.');
        }
    }
}