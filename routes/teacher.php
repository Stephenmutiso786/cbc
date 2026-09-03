<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Teacher\SignatureSettings;
use App\Livewire\Assessment\BulkAssessmentEntry;
use App\Livewire\Exams\ExamManager;
use App\Livewire\Notifications\SendNotification;
use App\Livewire\Teacher\LearnerList;
use App\Livewire\Teacher\ViewResults;
use App\Http\Controllers\ExamReportsController;
use App\Http\Controllers\MarksImportTemplateController;

Route::get('/dashboard', fn() => view('teacher.dashboard'))->name('dashboard');
Route::get('/learners', LearnerList::class)->middleware('permission:view students')->name('learners.index');
Route::get('/assessment', BulkAssessmentEntry::class)->middleware('permission:view assessments')->name('assessment.index');
Route::get('/exams', ExamManager::class)->middleware('permission:view exams|enter marks')->name('exams.index');
Route::get('/exams/{exam}/marks-template', [MarksImportTemplateController::class, 'download'])->middleware('permission:enter marks')->name('exams.marks-template');
Route::get('/results', ViewResults::class)->middleware('permission:view results')->name('results.index');
Route::get('/exams/{exam}/report-cards', [ExamReportsController::class, 'resultCards'])->middleware('permission:view report cards')->name('exams.report-cards');
Route::get('/exams/report-cards/export/{export}', [ExamReportsController::class, 'downloadExport'])->middleware('permission:view report cards')->name('exams.report-cards.export');
Route::get('/exams/{exam}/merit-list', [ExamReportsController::class, 'meritList'])->middleware('permission:view report cards')->name('exams.merit-list');
Route::get('/notifications', SendNotification::class)->middleware('permission:send notifications')->name('notifications.index');
Route::get('/signature', SignatureSettings::class)->middleware('permission:enter marks')->name('signature.index');
Route::get('/notes', fn() => view('teacher.notes.index'))->middleware('permission:view notes')->name('notes.index');
Route::get('/timetable', fn() => view('teacher.timetable.index'))->middleware('permission:view timetable')->name('timetable.index');
Route::get('/attendance', fn() => view('teacher.attendance.index'))->middleware('permission:view attendance|mark attendance')->name('attendance.index');
