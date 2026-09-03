<?php
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', fn() => view('parent.dashboard'))->middleware('permission:view report cards')->name('dashboard');
Route::get('/progress', fn() => view('parent.progress.index'))->middleware('permission:view report cards')->name('progress.index');
Route::get('/fees', fn() => view('parent.fees.index'))->middleware('permission:view fees')->name('fees.index');
Route::get('/notes', fn() => view('parent.notes.index'))->middleware('permission:view notes')->name('notes.index');
