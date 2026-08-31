@extends('layouts.admin')
@section('header', 'KEMIS')
@section('content')
<div class="card p-6">
    @php($lastSync = \App\Models\KemisSyncLog::latest()->first())
    <h2 class="text-xl font-bold text-gray-800 mb-2">KEMIS</h2>
    <p class="text-sm text-gray-500">Learner synchronization status.</p>
    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3"><div class="rounded-lg border p-4"><p class="text-xs uppercase text-gray-400">API configuration</p><p class="mt-1 font-semibold">{{ config('services.kemis.api_key') ? 'Configured' : 'Not configured' }}</p></div><div class="rounded-lg border p-4"><p class="text-xs uppercase text-gray-400">Last status</p><p class="mt-1 font-semibold capitalize">{{ $lastSync?->status ?: 'No sync yet' }}</p></div><div class="rounded-lg border p-4"><p class="text-xs uppercase text-gray-400">Last run</p><p class="mt-1 font-semibold">{{ $lastSync?->completed_at?->format('d M Y H:i') ?: 'Never' }}</p></div></div>
</div>
@endsection
