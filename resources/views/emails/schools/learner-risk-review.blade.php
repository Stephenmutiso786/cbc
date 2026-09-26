@component('mail::message')
# Learner-support review

{{ $prediction->learner?->full_name ?? 'A learner' }}@if($prediction->learner?->schoolClass) ({{ $prediction->learner->schoolClass->name }}) @endif has been identified for a **{{ $prediction->risk_level }}-priority** learner-support review at **{{ $school->name }}**.

- Support score: **{{ number_format((float) $prediction->risk_score, 1) }}%**
- Data factors to review: **{{ collect($prediction->top_factors ?: [])->map(fn ($factor) => str_replace('_', ' ', $factor))->implode(', ') ?: 'attendance, assessment and fee records' }}**

This is decision support, not an automatic judgement. Please review the learner's current attendance, assessments and family context before taking any action.

@component('mail::button', ['url' => url('/admin/at-risk-learners/' . $prediction->id)])
Review learner record
@endcomponent

This email is sent only to authorised administrators of the school.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
