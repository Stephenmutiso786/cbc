<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Fees\FeePayment;
use App\Livewire\Support\SupportTicketCenter;
use App\Livewire\Notifications\NotificationInbox;

Route::get('/dashboard', fn() => view('finance.dashboard'))->middleware(['permission:view fees', 'feature:fees'])->name('dashboard');
Route::get('/payments', FeePayment::class)->middleware(['permission:record payments', 'feature:fees'])->name('payments.index');
Route::get('/invoices', fn() => view('finance.invoices.index'))->middleware(['permission:view fees', 'feature:fees'])->name('invoices.index');
Route::get('/inventory', fn() => view('finance.inventory.index'))->middleware('permission:view inventory')->name('inventory.index');
Route::get('/reports', fn() => view('finance.reports.index'))->middleware(['permission:view finance reports', 'feature:fees'])->name('reports.index');
Route::get('/support', SupportTicketCenter::class)->middleware('permission:submit support tickets')->name('support.index');
Route::get('/notifications', NotificationInbox::class)->name('notifications.index');
