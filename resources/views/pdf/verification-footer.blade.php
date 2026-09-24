@php($performanceGraph = $performanceGraph ?? (isset($learnerResults) ? $learnerResults->map(fn ($result) => ['label' => $result->exam?->learningArea?->name ?? 'Learning area', 'value' => $result->percentage])->all() : []))
<div class="verification-footer">
    @if(!empty($performanceGraph))
        <div style="display:inline-block;min-width:180px;text-align:left">
            <div style="margin-bottom:3px;color:#166534;font-size:8px;font-weight:bold;text-transform:uppercase">Performance profile</div>
            <div style="display:flex;height:34px;align-items:flex-end;gap:3px;border-bottom:1px solid #9ca3af">
                @foreach($performanceGraph as $bar)
                    <div title="{{ $bar['label'] }}: {{ $bar['value'] }}%" style="width:12px;height:{{ max(4, min(100, (float) $bar['value'])) }}%;background:#16a34a"></div>
                @endforeach
            </div>
            <div style="margin-top:2px;color:#6b7280;font-size:7px">Each bar represents a learning area score.</div>
        </div>
    @endif
    @if(is_string(config('school.official_stamp_data')) && str_starts_with(config('school.official_stamp_data'), 'data:image/'))
        <img class="verification-stamp" src="{{ config('school.official_stamp_data') }}" alt="Official school stamp">
    @endif
</div>
