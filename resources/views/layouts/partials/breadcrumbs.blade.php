@php
    $routeName = request()->route()?->getName() ?? '';
    $parts = array_values(array_filter(explode('.', $routeName)));
    $portal = match ($parts[0] ?? '') {
        'admin' => auth()->user()?->hasRole('super-admin') ? 'Platform Administration' : 'School Administration',
        'teacher' => 'Teacher Portal', 'student' => 'Learner Portal', 'parent' => 'Parent Portal',
        'finance' => 'Finance Portal', 'it' => 'IT Support Portal', default => 'Portal',
    };
    $labels = [
        'user-accounts' => 'User Accounts & Passwords', 'platform-settings' => 'Global Settings & APIs',
        'system-logs' => 'Audit Logs', 'support' => 'Support Tickets', 'impersonate' => 'Support Access',
        'platform-dashboard' => 'Command Centre', 'dashboard' => 'Dashboard', 'index' => null,
    ];
    $sectionKey = $parts[1] ?? '';
    $section = $labels[$sectionKey] ?? ($sectionKey !== '' ? ucwords(str_replace(['-', '_'], ' ', $sectionKey)) : null);
    $page = $labels[$parts[2] ?? ''] ?? (($parts[2] ?? '') === 'index' ? null : ucwords(str_replace(['-', '_'], ' ', $parts[2] ?? '')));
@endphp
@if($routeName && $section)
    <nav aria-label="Breadcrumb" class="mb-4 flex flex-wrap items-center gap-2 text-xs text-gray-500">
        <span>{{ $portal }}</span><span aria-hidden="true">/</span><span>{{ $section }}</span>
        @if($page)<span aria-hidden="true">/</span><span class="font-medium text-gray-700">{{ $page }}</span>@endif
    </nav>
@endif
