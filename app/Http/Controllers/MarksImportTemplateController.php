<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Learner;
use App\Models\TeacherSubjectAllocation;
use Illuminate\Http\Response;

class MarksImportTemplateController extends Controller
{
    public function download(string $exam): Response
    {
        abort_unless(auth()->user()->can('enter marks'), 403);
        $exam = Exam::with('schoolClass')->findOrFail($exam);
        abort_unless(in_array($exam->marks_status, ['draft', 'returned'], true) && ! $exam->isLocked(), 422, 'This exam is already submitted or locked.');

        if (! auth()->user()->hasRole('super-admin')) {
            abort_unless(TeacherSubjectAllocation::where('teacher_id', auth()->user()->staffMember?->id)
                ->where('class_id', $exam->class_id)
                ->where('learning_area_id', $exam->learning_area_id)
                ->where('academic_year', $exam->academic_year)
                ->where('term', (int) $exam->term)
                ->where('is_active', true)
                ->exists(), 403, 'You are not allocated to this exam subject.');
        }

        $learners = Learner::where('class_id', $exam->class_id)
            ->where('grade_level', $exam->grade_level)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $filename = 'marks-template-' . str($exam->schoolClass?->name ?? $exam->grade_level)->slug() . '-' . $exam->id . '.csv';

        return response()->streamDownload(function () use ($learners): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['admission_number', 'learner_name', 'marks']);
            foreach ($learners as $learner) {
                fputcsv($handle, [$learner->admission_number, $learner->full_name, '']);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
