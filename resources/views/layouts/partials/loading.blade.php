<style>
    #global-loading { opacity: 0; pointer-events: none; transition: opacity .18s ease; }
    #global-loading.is-visible { opacity: 1; pointer-events: auto; }
</style>
<div id="global-loading" class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-950/45 backdrop-blur-[2px]" role="status" aria-live="polite" aria-label="Loading">
    <div class="rounded-2xl bg-white/95 px-7 py-6 text-center shadow-2xl">
        <video class="mx-auto h-16 w-16 object-contain" autoplay loop muted playsinline preload="auto">
            <source src="{{ asset('processing-loader.mp4') }}" type="video/mp4">
        </video>
        <p class="mt-2 text-sm font-semibold text-gray-700">Loading...</p>
    </div>
</div>
<script>
    (() => {
        const loader = document.getElementById('global-loading');
        let timer;
        const show = () => {
            clearTimeout(timer);
            timer = setTimeout(() => loader?.classList.add('is-visible'), 180);
        };
        const hide = () => {
            clearTimeout(timer);
            loader?.classList.remove('is-visible');
        };
        const marksPreviewUpdate = () => {
            const active = document.activeElement;
            const binding = active?.getAttribute('wire:model') || active?.getAttribute('wire:model.live') || '';
            return active?.matches('input[type="number"]') && binding.startsWith('marks.');
        };
        const refreshRubrics = (input) => {
            const bandsElement = document.getElementById('marks-grading-bands');
            if (!bandsElement) return;
            let bands = [];
            try { bands = JSON.parse(bandsElement.dataset.bands || '[]'); } catch (_) { return; }
            const update = (field) => {
                const raw = field.value.trim();
                const cell = field.closest('tr')?.querySelector('td:last-child');
                if (!cell) return;
                if (raw === '' || !/^\d+(?:\.\d+)?$/.test(raw)) { cell.textContent = '-'; return; }
                const examTotal = Number(field.dataset.totalMarks || 100);
                const marks = Number(raw);
                if (!Number.isFinite(marks) || marks < 0 || marks > 100 || marks > examTotal || examTotal <= 0) { cell.textContent = '-'; return; }
                const percent = Math.max(0, Math.min(100, (marks / examTotal) * 100));
                const band = bands.find((item) => percent >= Number(item.min ?? 0) - 0.000001 && percent <= Number(item.max ?? 100) + 0.000001);
                if (band?.code) {
                    cell.textContent = band.code;
                    return;
                }
                // Keep the preview useful even when a class has no custom scale yet.
                cell.textContent = percent >= 75 ? 'EE' : percent >= 50 ? 'ME' : percent >= 30 ? 'AE' : 'BE';
            };
            if (input) update(input);
            else document.querySelectorAll('input[type="number"][wire\\:model^="marks."]').forEach(update);
        };
        document.addEventListener('input', (event) => {
            const input = event.target;
            const binding = input?.getAttribute?.('wire:model') || input?.getAttribute?.('wire:model.live') || '';
            if (input?.matches?.('input[type="number"]') && binding.startsWith('marks.')) refreshRubrics(input);
        }, true);
        document.addEventListener('input', (event) => {
            const input = event.target;
            const binding = input?.getAttribute?.('wire:model') || input?.getAttribute?.('wire:model.live') || '';
            if (!input?.matches?.('input[type="number"]') || !binding.startsWith('marks.') || input.value === '') return;
            const value = Number(input.value);
            if (Number.isFinite(value) && value > 100) input.value = '100';
            if (Number.isFinite(value) && value < 0) input.value = '0';
        }, true);
        document.addEventListener('submit', (event) => {
            if (!event.target.hasAttribute('data-no-loading')) show();
        });
        document.addEventListener('click', (event) => {
            const link = event.target.closest('a');
            if (!link || link.hasAttribute('data-no-loading') || link.target === '_blank' || link.hasAttribute('download')) return;
            if (link.origin === window.location.origin && link.href !== window.location.href) show();
        });
        window.addEventListener('pageshow', hide);
        window.addEventListener('beforeunload', show);
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ succeed, fail }) => {
                if (marksPreviewUpdate()) {
                    succeed(() => hide());
                    fail(() => hide());
                    return;
                }
                show();
                succeed(() => { hide(); refreshRubrics(); });
                fail(() => hide());
            });
        });
    })();
</script>
