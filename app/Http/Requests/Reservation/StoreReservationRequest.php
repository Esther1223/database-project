<?php

namespace App\Http\Requests\Reservation;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required_without:selected_slots', 'exists:Room,id'],
            'date' => ['required_without_all:dates,selected_slots', 'date_format:Y-m-d'],
            'dates' => ['required_without_all:date,selected_slots', 'array', 'min:1'],
            'dates.*' => ['date_format:Y-m-d'],
            'time_slot_id' => ['required_without_all:time_slot_ids,selected_slots', 'integer', 'exists:Time_slot,id'],
            'time_slot_ids' => ['required_without_all:time_slot_id,selected_slots', 'array', 'min:1'],
            'time_slot_ids.*' => ['integer', 'distinct', 'exists:Time_slot,id'],
            'selected_slots' => ['required_without_all:time_slot_id,time_slot_ids', 'array', 'min:1'],
            'selected_slots.*.room_id' => ['required_with:selected_slots', 'integer', 'exists:Room,id'],
            'selected_slots.*.date' => ['required_with:selected_slots', 'date_format:Y-m-d'],
            'selected_slots.*.time_slot_id' => ['required_with:selected_slots', 'integer', 'exists:Time_slot,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $now = now();

            if ($this->filled('selected_slots')) {
                foreach ($this->selectedSlotsForValidation() as $slot) {
                    $room = Room::find($slot['room_id']);

                    if ($room === null) {
                        return;
                    }

                    if ($user !== null && ! $room->isBookableBy($user)) {
                        $validator->errors()->add('selected_slots', '此角色無法借用所選空間類型，或該空間未開放給所屬 Affiliation。');

                        return;
                    }

                    $timeSlot = TimeSlot::query()
                        ->whereKey($slot['time_slot_id'])
                        ->where('room_id', $room->id)
                        ->first();

                    if ($timeSlot === null) {
                        $validator->errors()->add('selected_slots', '選擇的時段不屬於該教室。');

                        return;
                    }

                    if ($this->slotStart($slot['date'], $timeSlot)->lessThanOrEqualTo($now)) {
                        $validator->errors()->add('selected_slots', '不可預約現在以前的時段。');

                        return;
                    }
                }

                return;
            }

            $room = Room::find($this->integer('room_id'));
            if ($user !== null && $room !== null) {
                if (! $room->isBookableBy($user)) {
                    $validator->errors()->add('room_id', '此角色無法借用該空間類型，或該空間未開放給所屬 Affiliation。');

                    return;
                }
            }

            if ($room === null) {
                return;
            }

            if ($this->filled('time_slot_ids')) {
                foreach ($this->selectedSlotsForValidation() as $slot) {
                    $timeSlot = TimeSlot::query()
                        ->whereKey($slot['time_slot_id'])
                        ->where('room_id', $room->id)
                        ->first();

                    if ($timeSlot === null) {
                        $validator->errors()->add('time_slot_ids', '選擇的時段不屬於該教室。');

                        return;
                    }

                    if ($this->slotStart($slot['date'], $timeSlot)->lessThanOrEqualTo($now)) {
                        $validator->errors()->add('time_slot_ids', '不可預約現在以前的時段。');

                        return;
                    }
                }

                return;
            }

            $date = (string) $this->input('date');
            $timeSlot = TimeSlot::query()
                ->whereKey($this->integer('time_slot_id'))
                ->where('room_id', $room->id)
                ->first();

            if ($timeSlot === null) {
                $validator->errors()->add('time_slot_id', '該時段不屬於該教室。');

                return;
            }

            if ($this->slotStart($date, $timeSlot)->lessThanOrEqualTo($now)) {
                $validator->errors()->add('time_slot_id', '不可預約現在以前的時段。');
            }
        });
    }

    /**
     * @return array<int, array{room_id: int, date: string, time_slot_id: int}>
     */
    private function selectedSlotsForValidation(): array
    {
        if ($this->filled('selected_slots')) {
            return collect($this->input('selected_slots', []))
                ->map(fn (array $slot): array => [
                    'room_id' => (int) ($slot['room_id'] ?? 0),
                    'date' => (string) ($slot['date'] ?? ''),
                    'time_slot_id' => (int) ($slot['time_slot_id'] ?? 0),
                ])
                ->unique(fn (array $slot): string => $slot['room_id'].'|'.$slot['date'].'|'.$slot['time_slot_id'])
                ->values()
                ->all();
        }

        $roomId = $this->integer('room_id');
        $dates = $this->input('dates') ?? [(string) $this->input('date')];
        $timeSlotIds = array_values(array_unique(array_map('intval', $this->input('time_slot_ids', []))));

        return collect($dates)
            ->flatMap(fn (string $date): array => collect($timeSlotIds)
                ->map(fn (int $timeSlotId): array => [
                    'room_id' => $roomId,
                    'date' => $date,
                    'time_slot_id' => $timeSlotId,
                ])
                ->all())
            ->unique(fn (array $slot): string => $slot['room_id'].'|'.$slot['date'].'|'.$slot['time_slot_id'])
            ->values()
            ->all();
    }

    private function slotStart(string $date, TimeSlot $timeSlot): Carbon
    {
        return Carbon::parse(sprintf('%s %02d:00:00', $date, $timeSlot->period));
    }
}
