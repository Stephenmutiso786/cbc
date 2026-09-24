@php($performanceGraph = $performanceGraph ?? (isset($learnerResults) ? $learnerResults->map(fn ($result) => ['label' => $result->exam?->learningArea?->name ?? 'Learning area', 'value' => $result->percentage])->all() : []))
@php($hasOfficialStamp = is_string(config('school.official_stamp_data')) && str_starts_with(config('school.official_stamp_data'), 'data:image/'))
<div class="verification-footer">
    @if(!empty($performanceGraph))
        <div style="display:inline-block;width:{{ $hasOfficialStamp ? 'calc(100% - 95px)' : '86%' }};min-width:420px;max-width:620px;padding:8px 12px 6px;border:1px solid #bbd8c4;border-radius:4px;background:#f7fcf8;text-align:left">
            <div style="margin-bottom:7px;color:#166534;font-size:11px;font-weight:bold;text-transform:uppercase">Performance profile by learning area</div>
            <div style="display:flex;height:115px;align-items:flex-end;gap:8px;padding:0 6px;border-bottom:2px solid #6b7280">
                @foreach($performanceGraph as $bar)
                    <div title="{{ $bar['label'] }}: {{ $bar['value'] }}%" style="flex:1;min-width:22px;height:{{ max(5, min(100, (float) $bar['value'])) }}%;border-radius:3px 3px 0 0;background:#168447"></div>
                @endforeach
            </div>
            <div style="margin-top:5px;color:#4b5563;font-size:8px">Each bar represents one learning area score. Open the report on screen to see the subject and exact percentage for each bar.</div>
        </div>
    @endif
    @if($hasOfficialStamp)
        <img class="verification-stamp" src="{{ config('school.official_stamp_data') }}" alt="Official school stamp">
    @endif
</div>
