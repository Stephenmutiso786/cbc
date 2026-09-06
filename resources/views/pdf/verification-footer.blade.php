@php($verificationUrl = url('/verify'))
<div class="verification-footer">
    <div class="verification-qr">{!! \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(64)->margin(0)->generate($verificationUrl) !!}</div>
    <div class="verification-copy">Scan to verify this document<br><span>{{ $verificationUrl }}</span></div>
    @if(is_string(config('school.official_stamp_data')) && str_starts_with(config('school.official_stamp_data'), 'data:image/'))
        <img class="verification-stamp" src="{{ config('school.official_stamp_data') }}" alt="Official school stamp">
    @endif
</div>
