@extends('layouts.admin')

@section('header', 'Google Drive Store')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="rounded-xl bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">School documents</h2>
                <p class="mt-1 text-sm text-gray-500">Generated result cards and merit lists stored in the connected Google Drive folder.</p>
            </div>
            @can('manage system settings')
                <a href="{{ route('admin.settings.index') }}" class="rounded-lg bg-green-700 px-4 py-2 text-sm font-medium text-white hover:bg-green-800">Drive settings</a>
            @endcan
        </div>
    </div>

    @if($error)
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $error }}</div>
    @elseif(count($files) === 0)
        <div class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">No generated documents have been stored in Google Drive yet.</div>
    @else
        <div class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr><th class="px-6 py-3">Document</th><th class="px-6 py-3">Type</th><th class="px-6 py-3">Size</th><th class="px-6 py-3">Updated</th><th class="px-6 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($files as $file)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $file['name'] }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $file['mime_type'] }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ number_format($file['size'] / 1024, 1) }} KB</td>
                                <td class="px-6 py-4 text-gray-500">{{ $file['modified_at'] ?: $file['created_at'] }}</td>
                                <td class="px-6 py-4 text-right"><a href="{{ $file['url'] }}" target="_blank" rel="noopener" class="font-medium text-green-700 hover:text-green-900">Open in Drive</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
