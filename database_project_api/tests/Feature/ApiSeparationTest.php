<?php

namespace Tests\Feature;

use App\Models\Affiliation;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\CompleteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CompleteSeeder::class);
    }

    public function test_login_returns_current_user_payload(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'admin',
        ])
            ->assertOk()
            ->assertJsonPath('message', '登入成功')
            ->assertJsonPath('user.email', 'admin@example.com')
            ->assertJsonPath('user.roles.0.role_type', '管理員');
    }

    public function test_student_can_create_reservation_on_open_access_room(): void
    {
        $student = User::query()->where('email', 'KaBuo@example.com')->firstOrFail();
        $room = Room::query()->where('room_name', 'B1 多功能教室')->firstOrFail();
        $timeSlot = TimeSlot::query()
            ->where('room_id', $room->id)
            ->where('period', 8)
            ->firstOrFail();

        $date = now()->addDays(10)->toDateString();

        $this->actingAs($student)
            ->postJson('/api/reservations', [
                'room_id' => $room->id,
                'date' => $date,
                'time_slot_id' => $timeSlot->id,
            ])
            ->assertCreated()
            ->assertJsonPath('message', '預約成功！')
            ->assertJsonPath('data.0.reservation_status', 'success');

        $reservation = Reservation::query()
            ->where('user_id', $student->id)
            ->where('room_id', $room->id)
            ->where('time_slot_id', $timeSlot->id)
            ->where('reservation_status', 'success')
            ->firstOrFail();

        $this->assertSame($date, $reservation->reservation_date->toDateString());
    }

    public function test_admin_can_create_room_through_api(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $affiliation = Affiliation::query()->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/api/admin/rooms', [
                'name' => 'T900 測試教室',
                'type' => '教室',
                'capacity' => 24,
                'building' => '測試大樓',
                'affiliation_id' => $affiliation->id,
                'information' => 'Feature test room.',
                'need_approval' => false,
                'is_open_access' => true,
                'open_access_all' => true,
                'open_access_affiliations' => [],
            ])
            ->assertOk()
            ->assertJsonPath('message', '空間已建立')
            ->assertJsonPath('room.name', 'T900 測試教室');

        $this->assertDatabaseHas('Room', [
            'room_name' => 'T900 測試教室',
            'room_type' => '教室',
            'capacity' => 24,
        ]);
    }

    public function test_staff_can_update_payment_status(): void
    {
        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $payment = Payment::query()->whereHas('reservation', function ($query): void {
            $query->where('payment_status', 'unpaid');
        })->firstOrFail();

        $this->actingAs($staff)
            ->patchJson("/api/admin/payments/{$payment->id}/status", [
                'payment_status' => 'paid',
            ])
            ->assertOk()
            ->assertJsonPath('message', '付款狀態已更新');

        $this->assertDatabaseHas('Reservation', [
            'id' => $payment->reservation_id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_staff_can_approve_pending_reservation(): void
    {
        $staff = User::query()->where('email', 'staff@example.com')->firstOrFail();
        $reservation = Reservation::query()
            ->where('reservation_status', 'pending')
            ->firstOrFail();

        $this->actingAs($staff)
            ->patchJson('/api/approvals/approve', [
                'reservation_id' => $reservation->id,
                'decision' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('message', '審核已核准');

        $this->assertDatabaseHas('Reservation', [
            'id' => $reservation->id,
            'reservation_status' => 'success',
        ]);

        $this->assertDatabaseHas('Approve', [
            'reservation_id' => $reservation->id,
            'approver_id' => $staff->id,
            'decision' => 'approved',
        ]);
    }
}
