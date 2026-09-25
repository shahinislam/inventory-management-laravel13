/*
 * Barcode scanner support — plug and play.
 *
 * USB/Bluetooth scanners act as keyboards: they "type" the code very fast and
 * finish with Enter (some send Tab). A human cannot type that fast, so a burst
 * of keys arriving under MAX_GAP ms apart, ending in Enter/Tab, is a scan.
 * It works anywhere on the page, focused input or not.
 *
 * A scan is sent to the page's Livewire component(s) as the `barcode-scanned`
 * event; components using the HandlesBarcodeScans trait answer with
 * `scan-result`, which drives the beep and the not-found toast.
 */

const MAX_GAP = 50; // ms between keystrokes — scanners are ~5–20ms, people 100ms+
const MIN_LENGTH = 3;

let buffer = '';
let lastKeyAt = 0;
let target = null;

document.addEventListener(
    'keydown',
    (e) => {
        if (e.ctrlKey || e.altKey || e.metaKey) {
            return;
        }

        const now = performance.now();

        // A pause means whatever came before was not part of this scan.
        if (now - lastKeyAt > MAX_GAP) {
            buffer = '';
            target = e.target;
        }
        lastKeyAt = now;

        if (e.key === 'Enter' || e.key === 'Tab') {
            const code = buffer;
            buffer = '';

            if (code.length >= MIN_LENGTH) {
                // Keep the Enter away from forms, search boxes and buttons.
                e.preventDefault();
                e.stopImmediatePropagation();
                removeTypedCode(target, code);
                window.Livewire?.dispatch('barcode-scanned', { code });
            }

            return;
        }

        if (e.key.length === 1) {
            buffer += e.key;
        }
    },
    true, // capture phase: runs before Alpine/Livewire key handlers
);

/** The scanner typed the code into whatever was focused — take it back out. */
function removeTypedCode(el, code) {
    if (!el || !('value' in el) || typeof el.value !== 'string' || !el.value.endsWith(code)) {
        return;
    }

    el.value = el.value.slice(0, -code.length);
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

// ============ FEEDBACK ============

let audio = null;

function beep(found) {
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)();
        const osc = audio.createOscillator();
        const gain = audio.createGain();

        osc.type = 'square';
        osc.frequency.value = found ? 1800 : 300;
        gain.gain.value = 0.05;
        osc.connect(gain).connect(audio.destination);
        osc.start();
        osc.stop(audio.currentTime + (found ? 0.08 : 0.3));
    } catch {
        // No audio available — the scan still works.
    }
}

function toast(message) {
    const el = document.createElement('div');
    el.textContent = message;
    el.className =
        'fixed bottom-6 left-1/2 z-[100] -translate-x-1/2 rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-medium text-white shadow-lg print:hidden';
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}

window.addEventListener('scan-result', (e) => {
    const { found, message } = e.detail ?? {};

    beep(found);

    if (!found && message) {
        toast(message);
    }
});
