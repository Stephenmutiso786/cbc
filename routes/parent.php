<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Support\SupportTicketCenter;
use App\Livewire\Notifications\NotificationInbox;

Route::get('/dashboard', fn() => view('parent.dashboard'))->middleware('permission:view report cards')->name('dashboard');
Route::get('/progress', fn() => view('parent.progress.index'))->middleware('permission:view report cards')->name('progress.index');
Route::get('/fees', fn() => view('parent.fees.index'))->middleware('permission:view fees')->name('fees.index');
Route::get('/notes', fn() => view('parent.notes.index'))->middleware('permission:view notes')->name('notes.index');
Route::get('/support', SupportTicketCenter::class)->middleware('permission:submit support tickets')->name('support.index');
Route::get('/notifications', NotificationInbox::class)->name('notifications.index');
