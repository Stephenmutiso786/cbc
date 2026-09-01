<?php
use Illuminate\Support\Facades\Route;
use App\Livewire\Students\StudentList;
use App\Livewire\Assessment\BulkAssessmentEntry;
use App\Livewire\Notifications\SendNotification;
use App\Livewire\Exams\ExamManager;
use App\Livewire\Inventory\InventoryList;
use App\Livewire\Notes\LearningNotesList;
use App\Livewire\Admin\AcademicSetup;
use App\Livewire\Admin\StaffManager;
use App\Livewire\Admin\SubjectManager;
use App\Models\FeeInvoice;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AnalyticsController;

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
Route::get('/staff', StaffManager::class)->name('staff.index');
Route::get('/timetable', fn() => view('admin.timetable.index'))->name('timetable.index');
Route::get('/reports', [AnalyticsController::class, 'index'])->name('reports.index');
Route::get('/reports/student/{learner}', [AnalyticsController::class, 'student'])->name('reports.student');
Route::get('/reports/export', [AnalyticsController::class, 'export'])->name('reports.export');
Route::get('/settings', fn() => view('admin.settings.index'))->name('settings.index');
Route::get('/classes', AcademicSetup::class)->name('classes.index');
Route::get('/subjects', SubjectManager::class)->name('subjects.index');
Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
Route::get('/kemis', fn() => view('admin.kemis.index'))->name('kemis.index');
