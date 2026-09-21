<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceDocumentController extends Controller
{
    /**
     * Render a platform subscription invoice for its school, or for a
     * super-admin reviewing platform billing.  Do not use implicit binding
     * here: a super-admin has no school tenant and must be able to select an
     * invoice across all schools, while school users must remain scoped.
     */
    public function preview(int $invoice): SymfonyResponse
    {
        return $this->document($invoice, false);
    }

    public function download(int $invoice): SymfonyResponse
    {
        return $this->document($invoice, true);
    }

    private function document(int $invoiceId, bool $download): SymfonyResponse
    {
        $user = auth()->user();

        abort_unless($user, 403);

        if ($user->hasRole('super-admin')) {
            $invoice = Invoice::withoutSchoolScope()->with(['items', 'school', 'package'])->findOrFail($invoiceId);
        } else {
            abort_unless($user->school_id, 403);
            $invoice = Invoice::with(['items', 'school', 'package'])->findOrFail($invoiceId);
            abort_unless((int) $invoice->school_id === (int) $user->school_id, 403);
        }

        $pdf = Pdf::loadView('pdf.platform-invoice', compact('invoice'))
            ->setPaper('a4');
        $filename = $invoice->invoice_number . '.pdf';

        return $download ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
