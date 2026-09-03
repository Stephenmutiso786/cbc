<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Fees\FeePayment;

Route::get('/dashboard', fn() => view('finance.dashboard'))->middleware('permission:view fees')->name('dashboard');
Route::get('/payments', FeePayment::class)->middleware('permission:record payments')->name('payments.index');
Route::get('/invoices', fn() => view('finance.invoices.index'))->middleware('permission:view fees')->name('invoices.index');
Route::get('/inventory', fn() => view('finance.inventory.index'))->middleware('permission:view inventory')->name('inventory.index');
Route::get('/reports', fn() => view('finance.reports.index'))->middleware('permission:view finance reports')->name('reports.index');
