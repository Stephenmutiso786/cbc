<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Students\StudentList;
use App\Livewire\Assessment\BulkAssessmentEntry;
use App\Livewire\Notifications\SendNotification;
use App\Livewire\Exams\ExamManager;
use App\Livewire\Inventory\InventoryList;
use App\Livewire\Notes\LearningNotesList;
use App\Models\FeeInvoice;
use App\Http\Controllers\AdminSettingsController;

Route::get('/dashboard', fn() => view('admin.dashboard'))->name('dashboard');
Route::get('/students', StudentList::class)->name('students.index');
Route::get('/assessment', BulkAssessmentEntry::class)->name('assessment.index');
Route::get('/notifications', SendNotification::class)->name('notifications.index');
Route::get('/exams', ExamManager::class)->name('exams.index');
Route::get('/inventory', InventoryList::class)->name('inventory.index');
Route::get('/notes', LearningNotesList::class)->name('notes.index');
Route::get('/fees/receipt/{invoice}', function (FeeInvoice $invoice) {
    $payment = $invoice->payments()->latest('paid_at')->first();

    abort_unless($payment, 404, 'Receipt not found.');

    return view('pdf.fee-receipt', compact('payment'));
})->name('fees.receipt');
Route::get('/staff', fn() => view('admin.staff.index'))->name('staff.index');
Route::get('/timetable', fn() => view('admin.timetable.index'))->name('timetable.index');
Route::get('/reports', fn() => view('admin.reports.index'))->name('reports.index');
Route::get('/settings', fn() => view('admin.settings.index'))->name('settings.index');
Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
Route::get('/kemis', fn() => view('admin.kemis.index'))->name('kemis.index');
