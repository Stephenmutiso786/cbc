<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Upgrade required - {{ config('school.name') }}</title>
    @include('layouts.partials.theme')
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased">
    <main class="mx-auto flex min-h-screen max-w-3xl items-center p-4 sm:p-8">
        <section class="w-full rounded-2xl bg-white p-6 shadow-xl sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-700">Plan upgrade required</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ $featureLabel }} is not included in this school's current plan.</h1>
            <p class="mt-3 text-gray-600">{{ $school->name }} is currently on <strong>{{ $school->package?->name ?? 'no active plan' }}</strong>. Choose a plan that includes {{ $featureLabel }} to unlock this service for the school.</p>

            @if($packages->isNotEmpty())
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    @foreach($packages as $package)
                        <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                            <p class="font-bold text-green-900">{{ $package->name }}</p>
                            <p class="mt-1 text-sm text-green-800">KSh {{ number_format($package->price, 0) }} per student / {{ rtrim($package->billing_cycle, 'ly') === 'term' ? 'term' : $package->billing_cycle }}</p>
                            <p class="mt-2 text-xs text-gray-600">{{ $package->description }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">There is no active plan containing this module yet. Please contact the platform administrator.</p>
            @endif

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('billing.index') }}" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">View plans and upgrade</a>
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('admin.dashboard') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Go back</a>
            </div>
        </section>
    </main>
</body>
</html>
