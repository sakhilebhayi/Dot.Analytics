// The only script on a public page: it reads a chart point aloud as the pointer or the keyboard reaches it.
// Without it every figure is still printed in full in the table under "Show working".
document.addEventListener('pointerover', (event) => {
    const hit = event.target.closest?.('.chart .hit');

    if (hit) announce(hit.closest('.chart'), hit.dataset.readout);
});

// A chart is one stop for the keyboard. Left and right arrows move along the points, Home and End jump to the ends.
document.addEventListener('focusin', (event) => {
    const svg = event.target.closest?.('.chart svg');

    if (svg) move(svg, Number(svg.dataset.i ?? -1));
});

document.addEventListener('keydown', (event) => {
    const svg = event.target.closest?.('.chart svg');
    const count = svg ? svg.querySelectorAll('.hit').length : 0;

    if (!svg || count === 0) return;

    const at = Number(svg.dataset.i ?? count - 1);
    const next = { ArrowLeft: at - 1, ArrowRight: at + 1, Home: 0, End: count - 1 }[event.key];

    if (next === undefined) return;

    event.preventDefault();
    move(svg, next);
});

function move(svg, index) {
    const hits = [...svg.querySelectorAll('.hit')];

    if (hits.length === 0) return;

    const i = Math.min(hits.length - 1, Math.max(0, index < 0 ? hits.length - 1 : index));
    svg.dataset.i = String(i);
    svg.querySelectorAll('.dot').forEach((dot, n) => dot.classList.toggle('on', n === i));
    announce(svg.closest('.chart'), hits[i].dataset.readout);
}

function announce(chart, text) {
    const read = chart?.querySelector('.chart-read');

    if (read && text) read.textContent = text;
}
