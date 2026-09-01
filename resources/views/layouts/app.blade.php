<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} - {{ $title ?? 'Administration' }}</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="overflow-x-hidden bg-gray-100 font-sans antialiased">
<div class="min-h-screen">
    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-black/50 md:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-green-800 text-white shadow-lg transition-transform duration-300 md:translate-x-0">
        <div class="flex h-16 items-center bg-green-900 px-5"><span class="truncate font-bold">{{ config('school.name') }}</span><button type="button" data-sidebar-close class="ml-auto rounded p-2 text-green-100 hover:bg-green-700 md:hidden" aria-label="Close menu">&times;</button></div>
        <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-4 text-sm">
            @foreach([
                'Overview' => [['admin.dashboard', 'Dashboard']],
                'People' => [['admin.students.index', 'Learners'], ['admin.students.import', 'Import Learners'], ['admin.staff.index', 'Staff'], ['admin.staff.import', 'Import Staff']],
                'Academics' => [['admin.classes.index', 'Classes'], ['admin.subjects.index', 'Subjects'], ['admin.grades.index', 'Grade Management'], ['admin.assessment.index', 'Assessments'], ['admin.exams.index', 'Exams'], ['admin.notes.index', 'Learning Notes'], ['admin.timetable.index', 'Timetable']],
                'Finance' => [['finance.payments.index', 'Fees and Payments'], ['finance.invoices.index', 'Invoices'], ['finance.reports.index', 'Finance Reports']],
                'Operations' => [['admin.inventory.index', 'Inventory'], ['admin.sms.index', 'SMS Center'], ['admin.notifications.index', 'Notifications'], ['admin.reports.index', 'Analytics and Reports']],
                'Configuration' => [['admin.settings.index', 'School Settings'], ['admin.report-forms.index', 'Report Forms'], ['admin.kemis.index', 'KEMIS Integration']],
            ] as $section => $links)
                <div><p class="mb-1 px-4 text-[10px] font-bold uppercase tracking-widest text-green-300">{{ $section }}</p>@foreach($links as [$route, $label])<a href="{{ route($route) }}" class="block rounded-lg px-4 py-2.5 text-green-100 hover:bg-green-700">{{ $label }}</a>@endforeach</div>
            @endforeach
        </nav>
        <div class="border-t border-green-700 px-4 py-3"><p class="truncate text-xs text-green-200">{{ auth()->user()->name }}</p><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="mt-1 text-xs text-green-300 hover:text-white">Sign out</button></form></div>
    </aside>
    <div class="md:pl-64">
        <header class="flex h-16 items-center justify-between bg-white px-4 shadow-sm md:px-6"><button type="button" data-mobile-menu aria-expanded="false" class="rounded-lg p-2 text-gray-700 hover:bg-gray-100 md:hidden" aria-label="Open menu">&#9776;</button><h1 class="text-xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1><span class="text-sm text-gray-500">{{ config('school.academic_year') }}</span></header>
        <main class="min-w-0 overflow-x-hidden p-4 md:p-6">@yield('content') @isset($slot){{ $slot }}@endisset</main>
    </div>
</div>
@livewireScripts
@include('layouts.partials.loading')
</body>
</html>
