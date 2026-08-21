<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use Carbon\Carbon;

class QueueService
{
    /**
     * Simulates the queue to estimate when a NEW booking would actually start.
     * Logic: figure out when each chair frees up, then "walk" everyone
     * currently waiting into whichever chair is free soonest — same idea
     * as how kitchen/delivery apps estimate prep time across multiple stations.
     */
    public function estimateWait(Salon $salon, int $serviceDurationMinutes): array
    {
        $chairs = $salon->chairs;
        $now = now();

        if ($chairs->isEmpty()) {
            return [
                'estimated_wait_minutes' => null,
                'expected_start_at' => null,
                'position_in_queue' => null,
            ];
        }

        // Step 1: when does each chair become free?
        $freeTimes = $chairs->map(function ($chair) use ($now) {
            if ($chair->status === 'occupied' && $chair->currentBooking && $chair->currentBooking->started_at) {
                $booking = $chair->currentBooking;
                $duration = $booking->service->avg_duration_minutes ?? 20;
                $startedAt = Carbon::parse($booking->started_at);
                $expectedFreeAt = $startedAt->copy()->addMinutes($duration);

                if ($expectedFreeAt->greaterThan($now)) {
                    return $expectedFreeAt;
                }

                // Barber is running over. Instead of assuming they'll finish
                // instantly, add a grace buffer that grows the longer they're overdue.
                $overdueMinutes = $now->diffInMinutes($expectedFreeAt);
                $buffer = min(10, max(3, (int) ($overdueMinutes * 0.5)));
                return $now->copy()->addMinutes($buffer);
            }
            return $now->copy();
        })->values()->all();

        // Step 2: simulate everyone currently waiting ahead of this new customer
        $waitingBookings = Booking::where('salon_id', $salon->id)
            ->where('status', 'waiting')
            ->orderBy('created_at')
            ->with('service')
            ->get();

        foreach ($waitingBookings as $waiting) {
            $minIndex = $this->earliestFreeChairIndex($freeTimes);
            $duration = $waiting->service->avg_duration_minutes ?? 20;
            $freeTimes[$minIndex] = $freeTimes[$minIndex]->copy()->addMinutes($duration);
        }

        // Step 3: place the NEW booking into whichever chair is free soonest
        $minIndex = $this->earliestFreeChairIndex($freeTimes);
        $expectedStart = $freeTimes[$minIndex];
        $waitMinutes = max(0, $now->diffInMinutes($expectedStart, false));

        return [
            'estimated_wait_minutes' => (int) round($waitMinutes),
            'expected_start_at' => $expectedStart->toDateTimeString(),
            'position_in_queue' => $waitingBookings->count() + 1,
        ];
    }

    private function earliestFreeChairIndex(array $freeTimes): int
    {
        $minIndex = 0;
        foreach ($freeTimes as $i => $time) {
            if ($time->lt($freeTimes[$minIndex])) {
                $minIndex = $i;
            }
        }
        return $minIndex;
    }
}