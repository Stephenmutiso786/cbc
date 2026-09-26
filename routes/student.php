<?php

use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\PortalContentController;
use App\Http\Controllers\ReportCardController;
use App\Livewire\Notifications\NotificationInbox;
use App\Livewire\Support\SupportTicketCenter;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');
Route::get('/results', [StudentPortalController::class, 'results'])->middleware('permission:view results')->name('results');
Route::get('/notes', [StudentPortalController::class, 'notes'])->middleware(['permission:view notes', 'feature:lesson_plans'])->name('notes');
Route::get('/timetable', [StudentPortalController::class, 'timetable'])->middleware(['permission:view timetable', 'feature:timetable'])->name('timetable');
Route::get('/exam-timetable', [PortalContentController::class, 'studentExamTimetable'])->middleware(['permission:view timetable', 'feature:timetable'])->name('exam-timetable');
Route::get('/newsletters', [PortalContentController::class, 'newsletters'])->middleware('feature:newsletters')->name('newsletters');
Route::get('/report-card', [ReportCardController::class, 'studentDownload'])->middleware('permission:view results')->name('report-card');
Route::get('/support', SupportTicketCenter::class)->middleware('permission:submit support tickets')->name('support');
Route::get('/notifications', NotificationInbox::class)->name('notifications');
