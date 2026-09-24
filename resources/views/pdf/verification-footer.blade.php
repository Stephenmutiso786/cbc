@php($performanceGraph = $performanceGraph ?? (isset($learnerResults) ? $learnerResults->map(fn ($result) => ['label' => $result->exam?->learningArea?->name ?? 'Learning area', 'value' => $result->percentage])->all() : []))
@php($hasOfficialStamp = is_string(config('school.official_stamp_data')) && str_starts_with(config('school.official_stamp_data'), 'data:image/'))
@php($graphBars = collect($performanceGraph)->values())
@php($graphCount = max(1, $graphBars->count()))
@php($graphWidth = 640)
@php($graphBaseline = 142)
@php($graphPlotHeight = 108)
@php($graphColumnWidth = $graphWidth / $graphCount)
@php($graphBarWidth = min(58, max(24, $graphColumnWidth * 0.52)))
<div class="verification-footer">
    @if($graphBars->isNotEmpty())
        <div style="display:inline-block;width:{{ $hasOfficialStamp ? 'calc(100% - 95px)' : '86%' }};min-width:420px;max-width:680px;padding:8px 12px 6px;border:1px solid #bbd8c4;border-radius:4px;text-align:left">
            <div style="margin-bottom:7px;color:#166534;font-size:11px;font-weight:bold;text-transform:uppercase">Performance profile by learning area</div>
            <svg viewBox="0 0 640 184" width="100%" height="184" role="img" aria-label="Performance profile by learning area" xmlns="http://www.w3.org/2000/svg">
                <line x1="12" y1="{{ $graphBaseline }}" x2="628" y2="{{ $graphBaseline }}" stroke="#4b5563" stroke-width="2"/>
                <line x1="12" y1="34" x2="12" y2="{{ $graphBaseline }}" stroke="#9ca3af" stroke-width="1"/>
                <text x="16" y="43" fill="#6b7280" font-size="10">100%</text>
                <text x="16" y="96" fill="#6b7280" font-size="10">50%</text>
                @foreach($graphBars as $index => $bar)
                    @php($value = max(0, min(100, (float) ($bar['value'] ?? 0))) )
                    @php($height = max(4, $graphPlotHeight * ($value / 100)))
                    @php($x = ($graphColumnWidth * $index) + (($graphColumnWidth - $graphBarWidth) / 2))
                    @php($y = $graphBaseline - $height)
                    @php($label = \Illuminate\Support\Str::limit((string) ($bar['label'] ?? 'Area'), 13, '…'))
                    <rect x="{{ round($x, 1) }}" y="{{ round($y, 1) }}" width="{{ round($graphBarWidth, 1) }}" height="{{ round($height, 1) }}" rx="3" fill="#168447" stroke="#0f5f31" stroke-width="1"/>
                    <text x="{{ round($x + ($graphBarWidth / 2), 1) }}" y="{{ round($y - 5, 1) }}" text-anchor="middle" fill="#14532d" font-size="11" font-weight="bold">{{ number_format($value, 0) }}%</text>
                    <text x="{{ round($x + ($graphBarWidth / 2), 1) }}" y="160" text-anchor="middle" fill="#374151" font-size="9">{{ $label }}</text>
                @endforeach
            </svg>
            <div style="margin-top:2px;color:#4b5563;font-size:8px">Each bar represents one learning area score.</div>
        </div>
    @endif
    @if($hasOfficialStamp)
        <img class="verification-stamp" src="{{ config('school.official_stamp_data') }}" alt="Official school stamp">
    @endif
</div>
