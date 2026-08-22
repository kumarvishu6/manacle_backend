<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Salon;
use Carbon\Carbon;

class QueueService
{
    /**
     * Simulates the queue to estimate when a NEW booking would actually start.
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

        $freeTimes = $this->computeChairFreeTimes($chairs, $now);

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

        $minIndex = $this->earliestFreeChairIndex($freeTimes);
        $expectedStart = $freeTimes[$minIndex];
        $waitMinutes = max(0, $now->diffInMinutes($expectedStart, false));

        return [
            'estimated_wait_minutes' => (int) round($waitMinutes),
            'expected_start_at' => $expectedStart->toIso8601String(),
            'position_in_queue' => $waitingBookings->count() + 1,
        ];
    }

    /**
     * Estimates wait for an EXISTING booking — only counts people genuinely
     * ahead of it, not itself or people behind it. Also flags whether the
     * estimate is "anchored" to a real running chair (trustworthy, tickable)
     * or purely projected from assumed durations (should not tick per-second).
     */
    public function estimateWaitForBooking(Booking $booking): array
    {
        $salon = $booking->salon;
        $chairs = $salon->chairs;
        $now = now();

        if ($chairs->isEmpty()) {
            return [
                'estimated_wait_minutes' => null,
                'expected_start_at' => null,
                'is_anchored' => false,
            ];
        }

        $isAnchored = $chairs->contains(function ($chair) {
            return $chair->status === 'occupied' && $chair->currentBooking && $chair->currentBooking->started_at;
        });

        $freeTimes = $this->computeChairFreeTimes($chairs, $now);

        $bookingsAhead = Booking::where('salon_id', $salon->id)
            ->where('status', 'waiting')
            ->where('created_at', '<', $booking->created_at)
            ->orderBy('created_at')
            ->with('service')
            ->get();

        foreach ($bookingsAhead as $ahead) {
            $minIndex = $this->earliestFreeChairIndex($freeTimes);
            $duration = $ahead->service->avg_duration_minutes ?? 20;
            $freeTimes[$minIndex] = $freeTimes[$minIndex]->copy()->addMinutes($duration);
        }

        $minIndex = $this->earliestFreeChairIndex($freeTimes);
        $expectedStart = $freeTimes[$minIndex];
        $waitMinutes = max(0, $now->diffInMinutes($expectedStart, false));

        return [
            'estimated_wait_minutes' => (int) round($waitMinutes),
            'expected_start_at' => $expectedStart->toIso8601String(),
            'is_anchored' => $isAnchored,
        ];
    }

    private function computeChairFreeTimes($chairs, Carbon $now): array
    {
        return $chairs->map(function ($chair) use ($now) {
            if ($chair->status === 'occupied' && $chair->currentBooking && $chair->currentBooking->started_at) {
                $booking = $chair->currentBooking;
                $duration = $booking->service->avg_duration_minutes ?? 20;
                $startedAt = Carbon::parse($booking->started_at);
                $expectedFreeAt = $startedAt->copy()->addMinutes($duration);

                if ($expectedFreeAt->greaterThan($now)) {
                    return $expectedFreeAt;
                }

                $overdueMinutes = $now->diffInMinutes($expectedFreeAt);
                $buffer = min(10, max(3, (int) ($overdueMinutes * 0.5)));
                return $now->copy()->addMinutes($buffer);
            }
            return $now->copy();
        })->values()->all();
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