<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System maintenance - {{ config('school.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-green-950 via-green-900 to-slate-950 p-6 text-white">
    <main class="w-full max-w-lg rounded-3xl border border-white/10 bg-white/10 p-8 text-center shadow-2xl backdrop-blur sm:p-12">
        <video class="mx-auto h-40 w-40 object-contain" autoplay loop muted playsinline preload="auto" aria-label="Maintenance in progress">
            <source src="{{ asset('maintenance-animation.mp4') }}" type="video/mp4">
        </video>
        <p class="mt-4 text-xs font-bold uppercase tracking-[0.3em] text-green-200">{{ config('school.name') }}</p>
        <h1 class="mt-3 text-3xl font-bold">System under maintenance</h1>
        <p class="mx-auto mt-4 max-w-md text-sm leading-6 text-green-100">{{ $message }}</p>
        <form method="GET" action="{{ route('maintenance.login') }}" class="mt-7">
            <button type="submit" class="inline-flex rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-green-900 shadow-sm transition hover:bg-green-50">Back to login</button>
        </form>
        <p class="mt-8 text-xs text-green-200">Please refresh this page after the maintenance is complete.</p>
    </main>
</body>
</html>
