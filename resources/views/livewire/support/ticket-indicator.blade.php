<span wire:poll.15s class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white" title="Open support tickets" aria-label="{{ $count }} open support tickets">
    {{ $count > 99 ? '99+' : $count }}
</span>
