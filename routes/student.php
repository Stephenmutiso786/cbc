<?php

use App\Http\Controllers\StudentPortalController;
use App\Livewire\Notifications\NotificationInbox;
use App\Livewire\Support\SupportTicketCenter;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [StudentPortalController::class, 'dashboard'])->name('dashboard');
Route::get('/results', [StudentPortalController::class, 'results'])->middleware('permission:view results')->name('results');
Route::get('/notes', [StudentPortalController::class, 'notes'])->middleware('permission:view notes')->name('notes');
Route::get('/support', SupportTicketCenter::class)->middleware('permission:submit support tickets')->name('support');
Route::get('/notifications', NotificationInbox::class)->name('notifications');
