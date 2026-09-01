<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Teacher\SignatureSettings;
use App\Livewire\Assessment\BulkAssessmentEntry;
use App\Livewire\Exams\ExamManager;
use App\Livewire\Notifications\SendNotification;
use App\Livewire\Teacher\LearnerList;

Route::get('/dashboard', fn() => view('teacher.dashboard'))->name('dashboard');
Route::get('/learners', LearnerList::class)->name('learners.index');
Route::get('/assessment', BulkAssessmentEntry::class)->name('assessment.index');
Route::get('/exams', ExamManager::class)->name('exams.index');
Route::get('/notifications', SendNotification::class)->name('notifications.index');
Route::get('/signature', SignatureSettings::class)->name('signature.index');
Route::get('/notes', fn() => view('teacher.notes.index'))->name('notes.index');
Route::get('/timetable', fn() => view('teacher.timetable.index'))->name('timetable.index');
Route::get('/attendance', fn() => view('teacher.attendance.index'))->name('attendance.index');
