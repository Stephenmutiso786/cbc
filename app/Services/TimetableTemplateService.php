<?php

namespace App\Services;

use App\Models\SchoolClass;
use Illuminate\Support\Arr;

class TimetableTemplateService
{
    /**
     * Return the built-in timetable templates grouped by CBC band.
     *
     * @return array<string, array<string, mixed>>
     */
    public function templates(): array
    {
        return [
            'cbc-lower-primary-30' => [
                'label' => 'CBC Lower Primary block (Grade 1-3, 30 minutes)',
                'band' => 'lower_primary',
                'duration' => 30,
                'periods' => [
                    ['08:20', '08:50'],
                    ['08:50', '09:20'],
                    ['09:20', '09:50'],
                    ['09:50', '10:20'],
                    ['10:50', '11:20'],
                    ['11:20', '11:50'],
                    ['11:50', '12:20'],
                ],
                'notes' => 'Morning lessons only, with a mid-morning break and pastoral time before dismissal.',
            ],
            'cbc-upper-primary-40' => [
                'label' => 'CBC Upper Primary block (Grade 4-6, 40 minutes)',
                'band' => 'upper_primary',
                'duration' => 40,
                'periods' => [
                    ['08:20', '09:00'],
                    ['09:00', '09:40'],
                    ['09:40', '10:20'],
                    ['10:55', '11:35'],
                    ['11:35', '12:15'],
                    ['12:15', '12:55'],
                    ['13:55', '14:35'],
                    ['14:35', '15:15'],
                ],
                'notes' => 'Eight 40-minute lessons with tea, lunch, and co-curricular breaks.',
            ],
            'cbc-junior-secondary-40' => [
                'label' => 'CBC Junior School block (Grade 7-9, 40 minutes)',
                'band' => 'junior_secondary',
                'duration' => 40,
                'periods' => [
                    ['08:20', '09:00'],
                    ['09:00', '09:40'],
                    ['09:40', '10:20'],
                    ['10:55', '11:35'],
                    ['11:35', '12:15'],
                    ['12:15', '12:55'],
                    ['13:55', '14:35'],
                    ['14:35', '15:15'],
                ],
                'notes' => 'Eight 40-minute lessons with tea, lunch, and co-curricular breaks.',
            ],
        ];
    }

    /**
     * @return array<int, array{value:string,label:string,description:string}> 
     */
    public function optionsForBand(string $band): array
    {
        return collect($this->templates())
            ->filter(fn (array $template) => ($template['band'] ?? null) === $band)
            ->map(fn (array $template, string $key) => [
                'value' => $key,
                'label' => $template['label'],
                'description' => $template['notes'],
            ])
            ->values()
            ->all();
    }

    public function defaultTemplateKeyForBand(string $band): string
    {
        return match ($band) {
            'lower_primary' => 'cbc-lower-primary-30',
            'upper_primary' => 'cbc-upper-primary-40',
            'junior_secondary' => 'cbc-junior-secondary-40',
            default => 'cbc-upper-primary-40',
        };
    }

    public function selectedTemplateKeyForBand(string $band): string
    {
        $settingKey = match ($band) {
            'lower_primary' => 'timetable_template_lower_primary',
            'upper_primary' => 'timetable_template_upper_primary',
            'junior_secondary' => 'timetable_template_junior_secondary',
            default => 'timetable_template_upper_primary',
        };

        $selected = (string) config('school.' . $settingKey, '');

        if ($selected === 'custom') {
            return 'custom';
        }

        return array_key_exists($selected, $this->templates())
            ? $selected
            : $this->defaultTemplateKeyForBand($band);
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public function periodsForClass(SchoolClass $class): array
    {
        return $this->periodsForBand($this->bandForGradeLevel((string) $class->grade_level));
    }

    public function templateForClass(SchoolClass $class): array
    {
        return $this->templateForBand($this->bandForGradeLevel((string) $class->grade_level));
    }

    /**
     * @return array{key:string,label:string,band:string,duration:int,periods:array<int, array{0:string,1:string}>,notes:string}
     */
    public function templateForBand(string $band): array
    {
        $key = $this->selectedTemplateKeyForBand($band);
        $templates = $this->templates();

        return [
            'key' => $key,
            'band' => $band,
            'label' => $templates[$key]['label'] ?? $key,
            'duration' => (int) ($templates[$key]['duration'] ?? 0),
            'periods' => $this->periodsForBand($band),
            'notes' => (string) ($templates[$key]['notes'] ?? ''),
        ];
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public function periodsForBand(string $band): array
    {
        $key = $this->selectedTemplateKeyForBand($band);
        if ($key === 'custom') {
            $customKey = 'timetable_template_' . $band . '_custom';
            $raw = (string) config('school.' . $customKey, '');
            $parsed = $this->parseCustomPeriods($raw);
            return $parsed ?: array_values(Arr::get($this->templates()[$this->defaultTemplateKeyForBand($band)], 'periods', []));
        }

        $template = $this->templates()[$key] ?? $this->templates()[$this->defaultTemplateKeyForBand($band)];

        return array_values(Arr::get($template, 'periods', []));
    }

    /**
     * Parse a comma separated list of start-end times into an array of 2-tuples.
     * Example input: "08:20-08:50,08:50-09:20"
     *
     * @return array<int, array{0:string,1:string}>
     */
    private function parseCustomPeriods(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $pairs = array_filter(array_map('trim', explode(',', $raw)));
        $result = [];
        foreach ($pairs as $pair) {
            if (! preg_match('/^\d{1,2}:\d{2}-\d{1,2}:\d{2}$/', $pair)) {
                return [];
            }
            [$start, $end] = explode('-', $pair, 2);
            $start = str_pad($start, 5, '0', STR_PAD_LEFT);
            $end = str_pad($end, 5, '0', STR_PAD_LEFT);
            $result[] = [$start, $end];
        }

        return $result;
    }

    public function bandForGradeLevel(string $gradeLevel): string
    {
        $gradeLevel = trim($gradeLevel);

        return match (true) {
            in_array($gradeLevel, ['Grade 1', 'Grade 2', 'Grade 3'], true) => 'lower_primary',
            in_array($gradeLevel, ['Grade 4', 'Grade 5', 'Grade 6'], true) => 'upper_primary',
            in_array($gradeLevel, ['Grade 7', 'Grade 8', 'Grade 9'], true) => 'junior_secondary',
            default => 'upper_primary',
        };
    }
}