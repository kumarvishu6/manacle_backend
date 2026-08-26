<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Chair;
use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use App\Services\QueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSalonWithChairs(int $chairCount = 1): Salon
    {
        $owner = User::create(['name' => 'Owner', 'phone' => '9000000000', 'role' => 'salon_owner']);

        $salon = Salon::create([
            'owner_id' => $owner->id,
            'name' => 'Test Salon',
            'address' => 'Test Address',
            'type' => 'own_franchise',
            'status' => 'active',
        ]);

        for ($i = 1; $i <= $chairCount; $i++) {
            Chair::create(['salon_id' => $salon->id, 'label' => "Chair $i", 'status' => 'idle']);
        }

        return $salon->fresh(['chairs']);
    }

    protected function makeService(Salon $salon, int $duration = 20): Service
    {
        return Service::create([
            'salon_id' => $salon->id,
            'name' => 'Haircut',
            'avg_duration_minutes' => $duration,
            'price' => 100,
            'is_active' => true,
        ]);
    }

    protected function makeCustomer(string $phone): User
    {
        return User::create(['name' => 'Customer', 'phone' => $phone, 'role' => 'customer']);
    }

    public function test_empty_queue_and_idle_chair_gives_zero_wait(): void
    {
        $salon = $this->makeSalonWithChairs(1);
        $service = $this->makeService($salon, 20);

        $estimate = app(QueueService::class)->estimateWait($salon, $service->avg_duration_minutes);

        $this->assertEquals(0, $estimate['estimated_wait_minutes']);
        $this->assertEquals(1, $estimate['position_in_queue']);
    }

    public function test_occupied_chair_pushes_wait_to_remaining_service_time(): void
    {
        $salon = $this->makeSalonWithChairs(1);
        $service = $this->makeService($salon, 20);
        $chair = $salon->chairs->first();
        $customer = $this->makeCustomer('9111111111');

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
            'chair_id' => $chair->id,
            'started_at' => now(),
        ]);
        $chair->update(['status' => 'occupied', 'current_booking_id' => $booking->id]);

        $estimate = app(QueueService::class)->estimateWait($salon->fresh(['chairs']), $service->avg_duration_minutes);

        $this->assertGreaterThanOrEqual(18, $estimate['estimated_wait_minutes']);
        $this->assertLessThanOrEqual(20, $estimate['estimated_wait_minutes']);
    }

    public function test_overdue_booking_gets_grace_buffer_not_zero(): void
    {
        $salon = $this->makeSalonWithChairs(1);
        $service = $this->makeService($salon, 5);
        $chair = $salon->chairs->first();
        $customer = $this->makeCustomer('9222222222');

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
            'chair_id' => $chair->id,
            'started_at' => now()->subMinutes(20),
        ]);
        $chair->update(['status' => 'occupied', 'current_booking_id' => $booking->id]);

        $estimate = app(QueueService::class)->estimateWait($salon->fresh(['chairs']), $service->avg_duration_minutes);

        $this->assertGreaterThanOrEqual(3, $estimate['estimated_wait_minutes']);
        $this->assertLessThanOrEqual(10, $estimate['estimated_wait_minutes']);
    }

    public function test_second_chair_is_used_when_first_is_busy(): void
    {
        $salon = $this->makeSalonWithChairs(2);
        $service = $this->makeService($salon, 20);
        $chairs = $salon->chairs;
        $customer = $this->makeCustomer('9333333333');

        $booking = Booking::create([
            'customer_id' => $customer->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'in_progress',
            'chair_id' => $chairs[0]->id,
            'started_at' => now(),
        ]);
        $chairs[0]->update(['status' => 'occupied', 'current_booking_id' => $booking->id]);

        $estimate = app(QueueService::class)->estimateWait($salon->fresh(['chairs']), $service->avg_duration_minutes);

        $this->assertEquals(0, $estimate['estimated_wait_minutes']);
    }

    public function test_estimate_for_booking_does_not_double_count_itself(): void
    {
        // Regression test for the double-counting bug we fixed.
        $salon = $this->makeSalonWithChairs(1);
        $service = $this->makeService($salon, 5);

        $customerA = $this->makeCustomer('9444444444');
        $customerB = $this->makeCustomer('9555555555');

        $bookingA = Booking::create([
            'customer_id' => $customerA->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'waiting',
        ]);

        $bookingB = Booking::create([
            'customer_id' => $customerB->id,
            'salon_id' => $salon->id,
            'service_id' => $service->id,
            'status' => 'waiting',
        ]);

        $estimateA = app(QueueService::class)->estimateWaitForBooking($bookingA->fresh(['salon.chairs', 'service']));
        $estimateB = app(QueueService::class)->estimateWaitForBooking($bookingB->fresh(['salon.chairs', 'service']));

        $this->assertEquals(0, $estimateA['estimated_wait_minutes']);
        $this->assertEquals(5, $estimateB['estimated_wait_minutes']);
    }
}