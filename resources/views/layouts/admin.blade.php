<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('school.name') }} - {{ $title ?? 'Administration' }}</title>
    @include('layouts.partials.pwa')
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
</head>
<body class="overflow-x-hidden bg-gray-100 font-sans antialiased">
<div class="min-h-screen">
    <div data-sidebar-overlay class="fixed inset-0 z-30 hidden bg-black/50 md:hidden"></div>
    <aside data-sidebar class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-green-800 text-white transition-transform duration-300 md:translate-x-0">
        <div class="flex h-16 items-center bg-green-900 px-5">
            <span class="truncate font-bold">{{ config('school.name') }}</span>
            <button type="button" data-sidebar-close class="ml-auto rounded p-2 text-green-100 hover:bg-green-700 md:hidden" aria-label="Close menu">&times;</button>
        </div>
        <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-4 pb-28 text-sm">
            @foreach([
                'Overview' => [['admin.dashboard', 'Dashboard', null]],
                'People' => [['admin.students.index', 'Learners', 'view students'], ['admin.students.import', 'Import Learners', 'create students'], ['admin.staff.index', 'Staff', 'view staff'], ['admin.staff.import', 'Import Staff', 'manage staff'], ['admin.parents.index', 'Parent Management', 'view students']],
                'Academics' => [['admin.classes.index', 'Classes', 'manage curriculum'], ['admin.subjects.index', 'Subjects', 'manage curriculum'], ['admin.grades.index', 'Grade Management', 'manage curriculum'], ['admin.promotions.index', 'Promotions', 'view students'], ['admin.assessment.index', 'Assessments', 'view assessments'], ['admin.exams.index', 'Exams', 'view exams'], ['admin.notes.index', 'Learning Notes', 'view notes'], ['admin.timetable.index', 'Timetable', 'view timetable'], ['admin.exam-timetable.index', 'Exam Timetable', 'view timetable']],
                'Finance' => [['finance.payments.index', 'Fees and Payments', 'view fees'], ['finance.invoices.index', 'Invoices', 'view fees'], ['finance.reports.index', 'Finance Reports', 'view finance reports']],
                'Operations' => [['admin.inventory.index', 'Inventory', 'view inventory'], ['admin.sms.index', 'SMS Center', 'send notifications'], ['admin.notifications.index', 'Notifications', 'view notifications'], ['admin.reports.index', 'Analytics and Reports', 'view analytics']],
                'Configuration' => [['admin.settings.index', 'School Settings', 'manage system settings'], ['admin.academic-periods.index', 'Years and Terms', 'manage curriculum'], ['admin.roles.index', 'Roles and Permissions', 'manage roles'], ['admin.report-forms.index', 'Report Forms', 'view report cards'], ['admin.kemis.index', 'KEMIS Integration', 'sync kemis']],
            ] as $section => $links)
                <div>
                    <p class="mb-1 px-4 text-[10px] font-bold uppercase tracking-widest text-green-300">{{ $section }}</p>
                    @foreach($links as [$route, $label, $permission])
                        @if($permission === null || auth()->user()->can($permission))
                            <a href="{{ route($route) }}" class="block rounded-lg px-4 py-2.5 text-green-100 hover:bg-green-700">{{ $label }}</a>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </nav>
        <div class="shrink-0 border-t border-green-700 bg-green-900 px-4 py-3">
            <p class="truncate text-xs text-green-200">{{ auth()->user()->name }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="mt-1 text-xs text-green-300 hover:text-white">Sign out</button>
            </form>
        </div>
    </aside>
    <div class="md:pl-64">
        <header class="flex h-16 items-center justify-between bg-white px-4 shadow-sm md:px-6">
            <button type="button" data-mobile-menu aria-expanded="false" class="rounded-lg p-2 text-gray-700 hover:bg-gray-100 md:hidden" aria-label="Open menu">&#9776;</button>
            <h1 class="text-xl font-semibold text-gray-800">@yield('header', 'Dashboard')</h1>
            <div class="flex items-center gap-4"><span class="hidden text-sm text-gray-500 sm:inline">{{ config('school.academic_year') }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Log out</button></form></div>
        </header>
        <main class="min-w-0 overflow-x-hidden p-4 md:p-6">
            @yield('content')
            @isset($slot){{ $slot }}@endisset
        </main>
    </div>
</div>
@livewireScripts
@include('layouts.partials.loading')
</body>
</html>
