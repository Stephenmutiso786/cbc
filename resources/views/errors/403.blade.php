<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access not allowed - {{ config('school.name') }}</title>
    @include('layouts.partials.theme')
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 font-sans antialiased">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center p-4 sm:p-8">
        <section class="w-full rounded-2xl bg-white p-6 text-center shadow-xl sm:p-10">
            <p class="text-sm font-semibold uppercase tracking-wide text-red-700">Access restricted</p>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">You are not allowed to open this page.</h1>
            <p class="mt-3 text-gray-600">Your account does not have the permission required for this service. Ask your school administrator if you believe you should have access.</p>
            <div class="mt-8 flex justify-center gap-3">
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="rounded-lg bg-green-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-green-800">Go back</a>
            </div>
        </section>
    </main>
</body>
</html>
