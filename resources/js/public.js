// The only script on a public page: it reads a chart point aloud as the pointer or the keyboard reaches it.
// Without it every figure is still printed in full in the table under "Show working".
document.addEventListener('pointerover', show);
document.addEventListener('focusin', show);

function show(event) {
    const hit = event.target.closest?.('.chart .hit');

    if (!hit) return;

    const read = hit.closest('.chart').querySelector('.chart-read');

    if (read) read.textContent = hit.dataset.readout;
}
