<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\TeacherSubjectAllocation;
use App\Models\TimetableAvailability;
use App\Models\TimetableSlot;
use App\Models\TimetableVersion;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

class VersionedTimetableGenerator
{
    private array $tasks = [], $solution = [], $busy = ['class' => [], 'teacher' => [], 'venue' => []], $subjectDays = [], $teacherLoad = [], $classLoad = [];
    private int $nodes = 0, $maxNodes = 200000;
    private float $startedAt;

    /** @return array{success:bool,message:string,version?:TimetableVersion,conflicts?:array,nodes?:int} */
    public function generate(string $year, int $term, int $userId): array
    {
        $schoolId = Tenant::id();
        if (! $schoolId) return ['success' => false, 'message' => 'A school context is required before generating a timetable.'];
        $classes = SchoolClass::query()->where('is_active', true)->with('learningAreas')->orderBy('grade_level')->orderBy('name')->get();
        $allocations = TeacherSubjectAllocation::query()->where('academic_year', $year)->where('term', $term)->where('is_active', true)->with(['teacher', 'learningArea'])->get()->keyBy(fn ($a) => $a->class_id.'|'.$a->learning_area_id);
        $missing = [];
        foreach ($classes as $class) foreach ($class->learningAreas as $area) if (! isset($allocations[$class->id.'|'.$area->id])) $missing[] = ['class' => $class->name, 'subject' => $area->name, 'code' => 'missing_teacher_assignment'];
        if ($classes->isEmpty() || $missing) return ['success' => false, 'message' => $classes->isEmpty() ? 'No active classes are configured.' : 'Generation cannot start until every class subject has a teacher allocation.', 'conflicts' => $missing];

        $templates = app(TimetableTemplateService::class);
        foreach ($classes as $class) {
            $periods = $templates->periodsForClass($class);
            foreach ($class->learningAreas as $area) {
                $allocation = $allocations[$class->id.'|'.$area->id];
                $count = (int) ($area->pivot?->lessons_per_week ?: $area->weekly_lessons ?: 1);
                if ($count > count($periods) * 5) return ['success' => false, 'message' => "{$class->name} requires more {$area->name} lessons than its school week contains.", 'conflicts' => [['class' => $class->name, 'subject' => $area->name, 'code' => 'insufficient_periods']]];
                $double = $this->practical($area->name) && $count >= 2;
                if ($double) { $this->tasks[] = compact('class', 'area', 'allocation', 'periods') + ['length' => 2]; $count -= 2; }
                while ($count-- > 0) $this->tasks[] = compact('class', 'area', 'allocation', 'periods') + ['length' => 1];
            }
        }
        usort($this->tasks, fn ($a, $b) => $b['length'] <=> $a['length']);
        $this->startedAt = microtime(true);
        if (! $this->solve(0)) return ['success' => false, 'message' => 'No conflict-free timetable can be generated from the current teacher allocations, timetable periods, and blocked availability.', 'conflicts' => [['code' => 'generation_failure', 'message' => 'Adjust workload, teacher availability, or available lesson periods and try again.']], 'nodes' => $this->nodes];

        return DB::transaction(function () use ($schoolId, $year, $term, $userId): array {
            $number = (int) TimetableVersion::withoutSchoolScope()->where('school_id', $schoolId)->where('academic_year', $year)->where('term', (string) $term)->max('version_number') + 1;
            $version = TimetableVersion::create(['academic_year' => $year, 'term' => (string) $term, 'version_number' => $number, 'state' => 'draft', 'lesson_count' => count($this->solution), 'generated_by' => $userId, 'generated_at' => now(), 'generation_options' => ['nodes' => $this->nodes]]);
            foreach (array_chunk(array_map(fn ($slot) => $slot + ['school_id' => $schoolId, 'timetable_version_id' => $version->id, 'academic_year' => $year, 'term' => (string) $term, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()], $this->solution), 500) as $chunk) TimetableSlot::insert($chunk);
            return ['success' => true, 'message' => 'A complete timetable draft was generated. Verify it before publishing.', 'version' => $version, 'nodes' => $this->nodes];
        });
    }

    private function solve(int $index): bool
    {
        if ($index === count($this->tasks)) return true;
        if (++$this->nodes > $this->maxNodes || microtime(true) - $this->startedAt > 15) return false;
        $task = $this->tasks[$index];
        foreach ($this->candidates($task) as $candidate) {
            $this->place($task, $candidate);
            if ($this->solve($index + 1)) return true;
            $this->unplace($task, $candidate);
        }
        return false;
    }

    private function candidates(array $task): array
    {
        $out = [];
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $dayIndex => $day) foreach ($task['periods'] as $index => $period) {
            if (! isset($task['periods'][$index + $task['length'] - 1])) continue;
            [$start] = $period; $end = $task['periods'][$index + $task['length'] - 1][1];
            if ($task['length'] === 2 && $period[1] !== $task['periods'][$index + 1][0]) continue;
            $venue = $this->venue($task['area']->name);
            if ($this->blocked('class', (string) $task['class']->id, $day, $start, $end) || $this->blocked('teacher', (string) $task['allocation']->teacher_id, $day, $start, $end) || ($venue && $this->blocked('venue', $venue, $day, $start, $end)) || $this->conflict($task, $venue, $day, $start, $end)) continue;
            $key = $task['class']->id.'|'.$task['area']->id;
            $out[] = compact('day','start','end','venue','index') + ['score' => (($this->subjectDays[$key][$day] ?? 0) * 100) + ($this->teacherLoad[$task['allocation']->teacher_id][$day] ?? 0) * 10 + $dayIndex];
        }
        usort($out, fn ($a, $b) => $a['score'] <=> $b['score']); return $out;
    }

    private function place(array $task, array $slot): void { $this->record($task, $slot, true); }
    private function unplace(array $task, array $slot): void { $this->record($task, $slot, false); }
    private function record(array $task, array $slot, bool $add): void
    {
        $sign = $add ? 1 : -1; $keys = [['class',(string)$task['class']->id],['teacher',(string)$task['allocation']->teacher_id]]; if ($slot['venue']) $keys[] = ['venue',$slot['venue']];
        foreach ($keys as [$type,$id]) { $key = $id.'|'.$slot['day']; if ($add) $this->busy[$type][$key][] = [$slot['start'],$slot['end']]; else array_pop($this->busy[$type][$key]); }
        $subjectKey = $task['class']->id.'|'.$task['area']->id; $this->subjectDays[$subjectKey][$slot['day']] = ($this->subjectDays[$subjectKey][$slot['day']] ?? 0) + $sign;
        $this->teacherLoad[$task['allocation']->teacher_id][$slot['day']] = ($this->teacherLoad[$task['allocation']->teacher_id][$slot['day']] ?? 0) + $task['length'] * $sign;
        if ($add) for ($i = 0; $i < $task['length']; $i++) $this->solution[] = ['class_id'=>$task['class']->id,'learning_area_id'=>$task['area']->id,'teacher_id'=>$task['allocation']->teacher_id,'day_of_week'=>$slot['day'],'start_time'=>$task['periods'][$slot['index']+$i][0].':00','end_time'=>$task['periods'][$slot['index']+$i][1].':00','venue'=>$slot['venue'],'is_double'=>$task['length'] === 2]; else for ($i = 0; $i < $task['length']; $i++) array_pop($this->solution);
    }
    private function conflict(array $task, ?string $venue, string $day, string $start, string $end): bool { foreach ([['class',(string)$task['class']->id],['teacher',(string)$task['allocation']->teacher_id],['venue',$venue]] as [$type,$id]) foreach ($id ? $this->busy[$type][$id.'|'.$day] ?? [] : [] as [$from,$to]) if ($start < $to && $end > $from) return true; return false; }
    private function blocked(string $type, string $id, string $day, string $start, string $end): bool { return TimetableAvailability::query()->where('resource_type',$type)->where('resource_key',$id)->where('day_of_week',$day)->where('is_available',false)->get()->contains(fn($b) => $start < substr($b->end_time,0,5) && $end > substr($b->start_time,0,5)); }
    private function practical(string $name): bool { return str_contains(strtolower($name),'science') || str_contains(strtolower($name),'technical') || str_contains(strtolower($name),'creative') || str_contains(strtolower($name),'agriculture'); }
    private function venue(string $name): ?string { $name = strtolower($name); return str_contains($name,'science') ? 'Science Lab' : (str_contains($name,'technical') ? 'Computer Lab' : null); }
}
