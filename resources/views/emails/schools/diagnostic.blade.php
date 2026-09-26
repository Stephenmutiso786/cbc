@component('mail::message')
# System Health Report for {{ $school->name }}

Here is the latest automated system diagnostic assessment:

- **Predicted Primary Issue:** {{ $diagnosticData['prediction'] ?? 'N/A' }}
- **Confidence Score:** {{ $diagnosticData['confidence'] ?? 'N/A' }}%
- **Recommended Action:** {{ $diagnosticData['recommended_action'] ?? 'No immediate action required.' }}

@component('mail::button', ['url' => config('app.url') . '/dashboard'])
View School Dashboard
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
