<?php

namespace App\Http\Controllers;

use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NewsletterController extends Controller
{
    public function index(): View { return view('admin.newsletters.index', ['newsletters' => Newsletter::latest('issued_on')->latest()->paginate(15)]); }
    public function create(): View { return view('admin.newsletters.form', ['newsletter' => new Newsletter(['issued_on' => today(), 'salutation' => 'Dear Parent/Guardian,', 'signatory_title' => 'HEADTEACHER'])]); }
    public function edit(Newsletter $newsletter): View { return view('admin.newsletters.form', compact('newsletter')); }

    public function store(Request $request): RedirectResponse
    {
        $newsletter = Newsletter::create([...$this->payload($request), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        return redirect()->route('admin.newsletters.edit', $newsletter)->with('success', 'Newsletter saved. You can continue editing or print it.');
    }
    public function update(Request $request, Newsletter $newsletter): RedirectResponse
    {
        $newsletter->update([...$this->payload($request), 'updated_by' => $request->user()->id]);
        return back()->with('success', 'Newsletter saved.');
    }
    public function destroy(Newsletter $newsletter): RedirectResponse { $newsletter->delete(); return redirect()->route('admin.newsletters.index')->with('success', 'Newsletter deleted.'); }
    public function print(Newsletter $newsletter): View { return view('admin.newsletters.print', compact('newsletter')); }
    public function publish(Newsletter $newsletter): RedirectResponse { $newsletter->update(['is_published' => true, 'published_at' => now(), 'published_by' => auth()->id()]); return back()->with('success', 'Newsletter published to teachers, parents and learners.'); }
    public function unpublish(Newsletter $newsletter): RedirectResponse { $newsletter->update(['is_published' => false]); return back()->with('success', 'Newsletter removed from portal audiences.'); }

    private function payload(Request $request): array
    {
        $data = $request->validate(['reference' => ['nullable','string','max:120'], 'issued_on' => ['required','date'], 'salutation' => ['required','string','max:160'], 'subject' => ['required','string','max:255'], 'body' => ['required','string','max:20000'], 'closing' => ['nullable','string','max:255'], 'signatory_name' => ['nullable','string','max:255'], 'signatory_title' => ['nullable','string','max:120'], 'copies_text' => ['nullable','string','max:3000']]);
        $data['copies'] = collect(preg_split('/\r\n|\r|\n/', $data['copies_text'] ?? ''))->map(fn ($copy) => trim($copy))->filter()->values()->all();
        unset($data['copies_text']);
        return $data;
    }
}
