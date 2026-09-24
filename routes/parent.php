<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Support\SupportTicketCenter;
use App\Livewire\Notifications\NotificationInbox;
use App\Http\Controllers\PortalContentController;
use App\Http\Controllers\ReportCardController;

Route::get('/dashboard', fn() => view('parent.dashboard'))->middleware('permission:view report cards')->name('dashboard');
Route::get('/progress', fn() => view('parent.progress.index'))->middleware('permission:view report cards')->name('progress.index');
Route::get('/fees', fn() => view('parent.fees.index'))->middleware('permission:view fees')->name('fees.index');
Route::get('/notes', [PortalContentController::class, 'parentNotes'])->middleware('permission:view notes')->name('notes.index');
Route::get('/exam-timetable', [PortalContentController::class, 'parentExamTimetable'])->middleware('permission:view report cards')->name('exam-timetable');
Route::get('/newsletters', [PortalContentController::class, 'newsletters'])->name('newsletters');
Route::get('/report-card/{learner}', [ReportCardController::class, 'parentDownload'])->middleware('permission:view report cards')->name('report-card');
Route::get('/support', SupportTicketCenter::class)->middleware('permission:submit support tickets')->name('support.index');
Route::get('/notifications', NotificationInbox::class)->name('notifications.index');
