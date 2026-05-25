<?php

namespace App\Http\Requests\Reservation;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Reservation::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'exists:rooms,id'],
            'section_id' => ['nullable', 'exists:room_sections,id'],
            'date' => ['required_without:section_id', 'date_format:Y-m-d'],
            'time_slot_id' => ['required_without_all:section_id,time_slot_ids', 'exists:time_slots,time_slot_id'],
            'time_slot_ids' => ['required_without_all:section_id,time_slot_id', 'array', 'min:1'],
            'time_slot_ids.*' => ['string', 'distinct', 'exists:time_slots,time_slot_id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $room = Room::find($this->integer('room_id'));

            if ($user !== null && $room !== null && (
                $user->hasRole('學生') || $user->hasRole('教授') || $user->hasRole('行政人員')
            )) {
                if ($room->type === '實驗室') {
                    $validator->errors()->add('room_id', '學生無法借用實驗室空間。');
                    return;
                }

                if ($room->department_id !== null && !$room->is_open_access) {
                    if ((int) $user->department_id !== (int) $room->department_id) {
                        $validator->errors()->add('room_id', '僅可借用所屬系所的空間。');
                        return;
                    }
                }
            }

            if ($this->filled('time_slot_ids')) {
                $timeSlotIds = array_values(array_unique($this->input('time_slot_ids', [])));
                $date = (string) $this->input('date');

                foreach ($timeSlotIds as $timeSlotId) {
                    $timeSlot = TimeSlot::where('time_slot_id', $timeSlotId)->first();

                    if ($timeSlot?->status === 'disable') {
                        $validator->errors()->add('time_slot_ids', '選擇的時段包含已停用時段。');
                        return;
                    }

                    $timeRange = $this->timeRangeForSlot($timeSlotId);

                    if ($timeRange === null) {
                        $validator->errors()->add('time_slot_ids', '預約時段格式錯誤。');
                        return;
                    }

                    $startTime = Carbon::parse("{$date} {$timeRange[0]}");
                    $endTime = Carbon::parse("{$date} {$timeRange[1]}");

                    if ($endTime->lessThanOrEqualTo($startTime)) {
                        $endTime->addDay();
                    }

                    if ($startTime->diffInMinutes($endTime, false) < 60) {
                        $validator->errors()->add('time_slot_ids', '預約時間最少需要 1 小時。');
                        return;
                    }
                }

                return;
            }

            if ($this->filled('section_id')) {
                $section = RoomSection::with('timeSlot')->find($this->integer('section_id'));

                if ($section === null) {
                    return;
                }

                if ($section->room_id !== $this->integer('room_id')) {
                    $validator->errors()->add('section_id', '選擇的時段不屬於該教室。');
                }

                if ($section->date === null) {
                    $validator->errors()->add('section_id', '預約日期不存在。');
                }

                if ($section->timeSlot?->status === 'disable') {
                    $validator->errors()->add('section_id', '該時段已停用。');
                }

                $timeSlotId = $section->time_slot_id;
                $date = Carbon::parse($section->date)->format('Y-m-d');
            } else {
                $timeSlotId = (string) $this->input('time_slot_id');
                $date = (string) $this->input('date');
                $timeSlot = TimeSlot::where('time_slot_id', $timeSlotId)->first();

                if ($timeSlot?->status === 'disable') {
                    $validator->errors()->add('time_slot_id', '該時段已停用。');
                }
            }

            $timeRange = $this->timeRangeForSlot($timeSlotId);

            if ($timeRange === null) {
                $validator->errors()->add('time_slot_id', '預約時段格式錯誤。');
                return;
            }

            $startTime = Carbon::parse("{$date} {$timeRange[0]}");
            $endTime = Carbon::parse("{$date} {$timeRange[1]}");

            if ($endTime->lessThanOrEqualTo($startTime)) {
                $endTime->addDay();
            }

            if ($startTime->diffInMinutes($endTime, false) < 60) {
                $validator->errors()->add('section_id', '預約時間最少需要 1 小時。');
            }
        });
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function timeRangeForSlot(string $timeSlotId): ?array
    {
        if (ctype_digit($timeSlotId)) {
            $startHour = (int) $timeSlotId;
            $endHour = $startHour + 1;

            return [
                sprintf('%02d:00:00', $startHour % 24),
                sprintf('%02d:00:00', $endHour % 24),
            ];
        }

        $timeMap = [
            'TS_0000' => ['08:00:00', '09:00:00'],
            'TS_0100' => ['09:00:00', '10:00:00'],
            'TS_0200' => ['10:00:00', '11:00:00'],
            'TS_0300' => ['11:00:00', '12:00:00'],
            'TS_0400' => ['12:00:00', '13:00:00'],
            'TS_0500' => ['13:00:00', '14:00:00'],
            'TS_0600' => ['14:00:00', '15:00:00'],
            'TS_0700' => ['15:00:00', '16:00:00'],
            'TS_0800' => ['16:00:00', '17:00:00'],
            'TS_0900' => ['17:00:00', '18:00:00'],
            'TS_1000' => ['18:00:00', '19:00:00'],
            'TS_1100' => ['19:00:00', '20:00:00'],
            'TS_1200' => ['20:00:00', '21:00:00'],
            'TS_1300' => ['21:00:00', '22:00:00'],
            'TS_1400' => ['22:00:00', '23:00:00'],
            'TS_1500' => ['23:00:00', '00:00:00'],
            'TS_1600' => ['00:00:00', '01:00:00'],
        ];

        return $timeMap[$timeSlotId] ?? null;
    }
}
