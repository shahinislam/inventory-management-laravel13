<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Brand palette from Settings → Theme. Emitted after the bundle so it
     overrides the compile-time fallbacks in app.css. --}}
<style>{!! App\Support\ThemeColors::css() !!}</style>

@fluxAppearance

{{-- Keep search boxes free of the browser's "Saved info" popup.

     Edge (and Chrome) ignore every autocomplete="…" value for that popup, but
     they never offer it on a read-only field. So each search input stays
     read-only while it is not focused, and is unlocked a tick after it gains
     focus — after the browser has already decided not to show the popup.

     Done in script rather than markup: a `readonly` attribute in the Blade
     would be put back by every Livewire re-render while the user is typing.
     The observer re-locks inputs that Livewire adds, or unlocks by morphing. --}}
<script>
    (() => {
        const SEARCH = 'input[data-form-type="other"]';

        const lock = (el) => {
            if (el !== document.activeElement && !el.readOnly) el.readOnly = true;
        };
        const lockWithin = (root) => root.querySelectorAll?.(SEARCH).forEach(lock);

        document.addEventListener('focusin', (e) => {
            if (e.target.matches?.(SEARCH)) setTimeout(() => { e.target.readOnly = false; }, 0);
        });
        document.addEventListener('focusout', (e) => {
            if (e.target.matches?.(SEARCH)) e.target.readOnly = true;
        });

        const start = () => {
            lockWithin(document);

            new MutationObserver((records) => {
                for (const record of records) {
                    if (record.type === 'attributes') {
                        if (record.target.matches?.(SEARCH)) lock(record.target);
                        continue;
                    }
                    for (const node of record.addedNodes) {
                        if (node.nodeType !== 1) continue;
                        node.matches(SEARCH) ? lock(node) : lockWithin(node);
                    }
                }
            }).observe(document.body, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['readonly'],
            });
        };

        document.readyState === 'loading'
            ? document.addEventListener('DOMContentLoaded', start)
            : start();
    })();
</script>
