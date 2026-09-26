<?php

namespace App\Services;

use App\Models\TeacherSubjectAllocation;
use App\Models\TimetableAvailability;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;

class TimetableValidationService
{
    /** @return array{valid:bool,errors:array<int,array<string,mixed>>} */
    public function validate(TimetableVersion $version): array
    {
        $slots = $version->slots()->with(['schoolClass', 'learningArea', 'teacher'])->orderBy('day_of_week')->orderBy('start_time')->get();
        $errors = [];
        $seen = [];
        $required = TeacherSubjectAllocation::query()->where('academic_year', $version->academic_year)->where('term', (int) $version->term)->where('is_active', true)->get()->keyBy(fn ($row) => $row->class_id.'|'.$row->learning_area_id);
        $actual = [];

        foreach ($slots as $slot) {
            $key = $slot->class_id.'|'.$slot->learning_area_id;
            $actual[$key] = ($actual[$key] ?? 0) + 1;
            if (! isset($required[$key]) || (int) $required[$key]->teacher_id !== (int) $slot->teacher_id) {
                $errors[] = $this->error('missing_assignment', 'A timetable lesson does not match a current teacher-subject allocation.', $slot);
            }
            foreach ([['class', (string) $slot->class_id], ['teacher', (string) $slot->teacher_id], ['venue', (string) $slot->venue]] as [$type, $resource]) {
                if ($resource === '') continue;
                $busyKey = $type.'|'.$resource.'|'.$slot->day_of_week;
                foreach ($seen[$busyKey] ?? [] as $other) {
                    if ($this->overlaps($slot->start_time, $slot->end_time, $other->start_time, $other->end_time)) {
                        $label = $type === 'class' ? 'Class' : ucfirst($type);
                        $errors[] = $this->error($type.'_double_booking', "{$label} double booking detected.", $slot, $other->id);
                    }
                }
                $seen[$busyKey][] = $slot;
            }
            if ($this->isBlocked($version, 'teacher', (string) $slot->teacher_id, $slot) || $this->isBlocked($version, 'class', (string) $slot->class_id, $slot) || ($slot->venue && $this->isBlocked($version, 'venue', $slot->venue, $slot))) {
                $errors[] = $this->error('unavailable_resource', 'A lesson is in a blocked teacher, class, or room period.', $slot);
            }
        }

        foreach ($required as $key => $allocation) {
            $expected = (int) ($allocation->schoolClass?->learningAreas()->whereKey($allocation->learning_area_id)->first()?->pivot?->lessons_per_week
                ?: $allocation->learningArea?->weekly_lessons ?: 1);
            if (($actual[$key] ?? 0) !== $expected) {
                $errors[] = ['code' => 'insufficient_periods', 'message' => "Class {$allocation->class_id}, subject {$allocation->learning_area_id} has ".($actual[$key] ?? 0)." of {$expected} required weekly periods."];
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    private function isBlocked(TimetableVersion $version, string $type, string $resource, TimetableSlot $slot): bool
    {
        return TimetableAvailability::query()->where('resource_type', $type)->where('resource_key', $resource)
            ->where('day_of_week', $slot->day_of_week)->where('is_available', false)
            ->get()->contains(fn ($block) => $this->overlaps($slot->start_time, $slot->end_time, $block->start_time, $block->end_time));
    }

    private function overlaps(string $start, string $end, string $otherStart, string $otherEnd): bool
    {
        return substr($start, 0, 5) < substr($otherEnd, 0, 5) && substr($end, 0, 5) > substr($otherStart, 0, 5);
    }

    private function error(string $code, string $message, TimetableSlot $slot, ?int $otherSlot = null): array
    {
        return ['code' => $code, 'message' => $message, 'slot_id' => $slot->id, 'other_slot_id' => $otherSlot, 'class' => $slot->schoolClass?->name, 'teacher' => $slot->teacher?->full_name, 'subject' => $slot->learningArea?->name, 'day' => $slot->day_of_week, 'time' => substr($slot->start_time, 0, 5)];
    }
}
