<div data-cbe-chatbot class="fixed bottom-4 right-4 z-[70] flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
    <section id="cbe-chatbot-panel" data-cbe-chatbot-panel hidden class="w-[calc(100vw-2rem)] max-w-[390px] overflow-hidden rounded-2xl border border-indigo-100 bg-white shadow-2xl sm:w-[390px]" aria-label="Ruth, CBE LMS chatbot">
        <div class="flex items-center justify-between bg-indigo-700 px-4 py-3 text-white">
            <div><p class="font-semibold">Ruth</p><p class="text-xs text-indigo-100">CBE LMS Assistant</p></div>
            <button type="button" data-cbe-chatbot-close class="rounded-lg p-2 hover:bg-indigo-600" aria-label="Close chatbot">&times;</button>
        </div>
        <iframe data-cbe-chatbot-frame title="Ruth: CBE LMS Chatbot" allow="fullscreen" src="about:blank" data-src="https://agent.jotform.com/01a0c7327580700281cf63dc3422df12ee6d?embedMode=iframe&amp;autofocus=0&amp;background=1&amp;shadow=1" class="h-[min(688px,70vh)] w-full border-0" scrolling="no"></iframe>
    </section>
    <button type="button" data-cbe-chatbot-toggle class="inline-flex items-center gap-2 rounded-full bg-indigo-700 px-4 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-indigo-800 focus:outline-none focus:ring-4 focus:ring-indigo-200" aria-expanded="false" aria-controls="cbe-chatbot-panel">
        <span aria-hidden="true">💬</span><span>Ask Ruth</span>
    </button>
</div>
<script src="https://cdn.jotfor.ms/s/umd/801f3bce7eb/for-form-embed-handler.js" defer></script>
<script>
(() => {
    const root = document.querySelector('[data-cbe-chatbot]');
    if (!root) return;
    const panel = root.querySelector('[data-cbe-chatbot-panel]');
    const toggle = root.querySelector('[data-cbe-chatbot-toggle]');
    const close = root.querySelector('[data-cbe-chatbot-close]');
    const frame = root.querySelector('[data-cbe-chatbot-frame]');
    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open && frame.src === 'about:blank') frame.src = frame.dataset.src;
        if (open) close.focus(); else toggle.focus();
    };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !panel.hidden) setOpen(false); });
    window.addEventListener('load', () => {
        if (window.jotformEmbedHandler) window.jotformEmbedHandler("iframe[data-cbe-chatbot-frame]", 'https://www.jotform.com');
    });
})();
</script>
