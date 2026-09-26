@component('mail::message')
# Learner-support insights are ready

The platform has completed a new CatBoost learner-support analysis for **{{ $school->name }}**.

- Learners analysed: **{{ number_format($learnersAnalysed) }}**
- Learners needing priority review: **{{ number_format($highRiskCount) }}**

These results are decision support only. Please review the underlying attendance, assessment and fee information with the learner and family before taking any action. Individual learner details are available only after an authorised school user signs in.

@component('mail::button', ['url' => url('/admin/at-risk-learners')])
Review learner-support insights
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
