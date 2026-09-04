<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Chair;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    public function __construct(protected QueueService $queueService)
    {
    }

    public function store(Request $request, Salon $salon)
    {
        $user = $request->user();

        if (! $salon->isCurrentlyOpen()) {
            return response()->json([
                'message' => 'This salon is currently closed. Please check back during opening hours.',
            ], 422);
        }

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
     * Staff adds a walk-in customer directly to the queue — someone who
     * showed up without booking through the app. Reuses the same queue
     * math as everyone else; no special treatment, just a different entry point.
     */
    public function walkIn(Request $request, Salon $salon)
    {
        $this->authorizeQueueAccess($request->user(), $salon);

        // Staff physically at a closed salon adding a walk-in is a real,
        // legitimate edge case (e.g. finishing up after hours) — so this
        // check is intentionally on the customer-facing store() path only,
        // not here. Staff already know their own salon's real status.

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|min:10|max:15',
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

        if ($request->filled('phone')) {
            $customer = User::firstOrCreate(
                ['phone' => $request->phone],
                ['name' => $request->name, 'role' => 'customer']
            );

            $existing = Booking::where('customer_id', $customer->id)
                ->where('salon_id', $salon->id)
                ->whereIn('status', ['waiting', 'in_progress'])
                ->first();

            if ($existing) {
                return response()->json(['message' => 'This customer already has an active booking here.'], 422);
            }
        } else {
            $customer = User::create([
                'name' => $request->name,
                'role' => 'customer',
            ]);
        }

        $estimate = $this->queueService->estimateWait($salon, $service->avg_duration_minutes);

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'waiting',
        ]);

        return response()->json([
            'booking' => $booking,
            'customer' => $customer,
            'estimated_wait_minutes' => $estimate['estimated_wait_minutes'],
            'expected_start_at' => $estimate['expected_start_at'],
            'position_in_queue' => $estimate['position_in_queue'],
        ], 201);
    }

    /**
     * The current customer's active booking(s) — lets the app show a
     * persistent "you have an active booking" banner and route back into
     * tracking, instead of leaving them with no way back once they've
     * navigated away from the tracking screen.
     */
    public function myActive(Request $request)
    {
        $user = $request->user();

        $bookings = Booking::where('customer_id', $user->id)
            ->whereIn('status', ['waiting', 'in_progress'])
            ->with(['salon:id,name', 'service:id,name'])
            ->orderBy('created_at')
            ->get();

        return response()->json($bookings);
    }

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
     * Live status of a single booking — used by the customer app's tracking screen.
     */
    public function show(Request $request, Booking $booking)
    {
        $user = $request->user();

        $isCustomer = $booking->customer_id === $user->id;
        $isOwner = $booking->salon->owner_id === $user->id;
        $isStaff = Staff::where('user_id', $user->id)->where('salon_id', $booking->salon_id)->exists();
        $isSuperAdmin = $user->role === 'super_admin';

        if (! $isCustomer && ! $isOwner && ! $isStaff && ! $isSuperAdmin) {
            return response()->json(['message' => 'You do not have access to this booking.'], 403);
        }

        $booking->load(['service', 'chair', 'salon']);

        $positionInQueue = null;
        $estimatedWaitMinutes = null;
        $expectedStartAt = null;
        $isAnchored = false;

        if ($booking->status === 'waiting') {
            $peopleAhead = Booking::where('salon_id', $booking->salon_id)
                ->where('status', 'waiting')
                ->where(function ($query) use ($booking) {
                    $query->where('created_at', '<', $booking->created_at)
                        ->orWhere(function ($query) use ($booking) {
                            $query->where('created_at', $booking->created_at)
                                ->where('id', '<', $booking->id);
                        });
                })
                ->count();

            $positionInQueue = $peopleAhead + 1;

            $estimate = $this->queueService->estimateWaitForBooking($booking);
            $estimatedWaitMinutes = $estimate['estimated_wait_minutes'];
            $expectedStartAt = $estimate['expected_start_at'];
            $isAnchored = $estimate['is_anchored'];
        }

        return response()->json([
            'booking' => $booking,
            'position_in_queue' => $positionInQueue,
            'estimated_wait_minutes' => $estimatedWaitMinutes,
            'expected_start_at' => $expectedStartAt,
            'is_anchored' => $isAnchored,
        ]);
    }

    /**
     * Assigns a waiting booking to a chair. Wrapped in a locked transaction
     * so two near-simultaneous requests can't both grab the same chair.
     */
    public function start(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        $validator = Validator::make($request->all(), [
            'chair_id' => 'required|exists:chairs,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = DB::transaction(function () use ($request, $booking) {
                $freshBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

                if ($freshBooking->status !== 'waiting') {
                    throw new \RuntimeException('This booking is not waiting.');
                }

                $chair = Chair::where('id', $request->chair_id)
                    ->where('salon_id', $freshBooking->salon_id)
                    ->lockForUpdate()
                    ->first();

                if (! $chair) {
                    throw new \RuntimeException('Chair not found at this salon.');
                }

                if ($chair->status !== 'idle') {
                    throw new \RuntimeException('This chair is currently occupied.');
                }

                $freshBooking->update([
                    'status' => 'in_progress',
                    'chair_id' => $chair->id,
                    'started_at' => now(),
                ]);

                $chair->update([
                    'status' => 'occupied',
                    'current_booking_id' => $freshBooking->id,
                ]);

                return $freshBooking->fresh();
            });

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function complete(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        try {
            $result = DB::transaction(function () use ($booking) {
                $freshBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

                if ($freshBooking->status !== 'in_progress') {
                    throw new \RuntimeException('This booking is not in progress.');
                }

                $freshBooking->update([
                    'status' => 'done',
                    'ended_at' => now(),
                ]);

                if ($freshBooking->chair_id) {
                    $chair = Chair::where('id', $freshBooking->chair_id)->lockForUpdate()->first();
                    $chair->update([
                        'status' => 'idle',
                        'current_booking_id' => null,
                    ]);
                }

                $actualMinutes = $freshBooking->started_at->diffInMinutes($freshBooking->ended_at);
                $service = $freshBooking->service;
                $newAvg = (int) round(($service->avg_duration_minutes + $actualMinutes) / 2);
                $service->update(['avg_duration_minutes' => max(5, $newAvg)]);

                return $freshBooking->fresh();
            });

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function noShow(Request $request, Booking $booking)
    {
        $this->authorizeQueueAccess($request->user(), $booking->salon);

        try {
            $result = DB::transaction(function () use ($booking) {
                $freshBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

                if ($freshBooking->status !== 'waiting') {
                    throw new \RuntimeException('This booking is not waiting.');
                }

                $freshBooking->update(['status' => 'no_show']);

                return $freshBooking->fresh();
            });

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        $user = $request->user();

        if ($booking->customer_id !== $user->id) {
            return response()->json(['message' => 'This is not your booking.'], 403);
        }

        try {
            $result = DB::transaction(function () use ($booking) {
                $freshBooking = Booking::where('id', $booking->id)->lockForUpdate()->first();

                if ($freshBooking->status !== 'waiting') {
                    throw new \RuntimeException('This booking can no longer be cancelled.');
                }

                $freshBooking->update(['status' => 'cancelled']);

                return $freshBooking->fresh();
            });

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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