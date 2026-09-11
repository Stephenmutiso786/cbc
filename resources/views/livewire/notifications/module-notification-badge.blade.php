<span class="contents" aria-live="polite">
    @if($count > 0)
        <span class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white" title="{{ $count }} new or pending item(s)" aria-label="{{ $count }} new or pending item(s)">
            {{ $count > 99 ? '99+' : $count }}
        </span>
    @endif
</span>
