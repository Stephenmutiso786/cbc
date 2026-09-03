<?php

namespace App\Livewire\Admin;

use App\Models\SchoolSetting;
use Illuminate\Support\Facades\Config;
use Livewire\Component;

class ReportCardTemplates extends Component
{
    public string $selected = 'cbc-classic';
    public string $saved = '';

    public array $templates = [
        'cbc-classic' => ['name' => 'CBC Classic', 'description' => 'Matches the supplied form with learning areas, expected outcomes, comments and signatures.'],
        'cbc-modern' => ['name' => 'CBC Modern', 'description' => 'A clean colour report with learner details, rubric badges and attendance summary.'],
        'cbc-compact' => ['name' => 'CBC Compact', 'description' => 'A printer-friendly condensed form for schools that need a shorter report.'],
    ];

    public function mount(): void
    {
        $value = SchoolSetting::where('key', 'report_card_template')->value('value');
        $this->selected = array_key_exists($value, $this->templates) ? $value : 'cbc-classic';
    }

    public function choose(string $template): void
    {
        abort_unless(array_key_exists($template, $this->templates), 422);
        $this->selected = $template;
        $this->saved = '';
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('generate report cards'), 403);
        SchoolSetting::updateOrCreate(['key' => 'report_card_template'], ['value' => $this->selected]);
        Config::set('school.report_card_template', $this->selected);
        $this->saved = 'Default report form saved. New report cards will use ' . $this->templates[$this->selected]['name'] . '.';
    }

    public function render()
    {
        return view('livewire.admin.report-card-templates')->layout('layouts.admin');
    }
}
