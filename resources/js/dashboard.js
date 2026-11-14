/*
 * The overview's two enhancements. The page is complete without them: the ledger is already ordered by the server and
 * the chart's every value is in the "Values as a table" list. Built by Vite and served from this site, so it runs under
 * the page's script policy with no inline script. Text from the page reaches the tooltip only through textContent.
 */
function enhanceSort() {
    const table = document.querySelector('.ledger table');
    const body = table?.tBodies[0];
    if (!table || !body) return;

    const heads = Array.from(table.querySelectorAll('th[data-sort]'));
    heads.forEach((th) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = th.textContent.trim();
        th.textContent = '';
        th.appendChild(button);

        button.addEventListener('click', () => {
            const key = th.dataset.sort;
            const ascending = th.getAttribute('aria-sort') !== 'ascending';
            heads.forEach((other) => other.removeAttribute('aria-sort'));
            th.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');

            const rows = Array.from(body.rows);
            rows.sort((a, b) => {
                const p = a.dataset[`s${key}`] ?? '';
                const q = b.dataset[`s${key}`] ?? '';
                const difference = key === 'Val' ? parseFloat(p) - parseFloat(q) : p.localeCompare(q);
                return ascending ? difference : -difference;
            });
            rows.forEach((row) => body.appendChild(row));
        });
    });
}

function enhanceChart() {
    const plot = document.querySelector('[data-chart]');
    const hit = plot?.querySelector('.hit');
    const cross = plot?.querySelector('.cross');
    const tip = plot?.querySelector('.tip');
    const svg = plot?.querySelector('svg');
    if (!hit || !cross || !tip || !svg) return;

    let points = [];
    try {
        points = JSON.parse(hit.dataset.points || '[]');
    } catch (e) {
        return;
    }
    if (points.length === 0) return;

    const box = svg.viewBox.baseVal;
    let current = points.length - 1;

    const hide = () => {
        tip.hidden = true;
        cross.setAttribute('visibility', 'hidden');
    };

    const show = (index) => {
        current = Math.max(0, Math.min(points.length - 1, index));
        const point = points[current];
        cross.setAttribute('x1', point.x);
        cross.setAttribute('x2', point.x);
        cross.setAttribute('visibility', 'visible');

        tip.textContent = '';
        const value = document.createElement('b');
        value.textContent = point.v;
        tip.appendChild(value);
        tip.appendChild(document.createTextNode(point.t));

        const rect = svg.getBoundingClientRect();
        tip.style.left = `${(point.x / box.width) * rect.width}px`;
        tip.style.top = `${Math.max(0, (point.y / box.height) * rect.height - 12)}px`;
        tip.hidden = false;
    };

    hit.addEventListener('pointermove', (event) => {
        const rect = svg.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * box.width;
        let nearest = 0;
        points.forEach((point, i) => {
            if (Math.abs(point.x - x) < Math.abs(points[nearest].x - x)) nearest = i;
        });
        show(nearest);
    });
    hit.addEventListener('pointerleave', hide);
    hit.addEventListener('blur', hide);
    hit.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') {
            show(current - 1);
            event.preventDefault();
        } else if (event.key === 'ArrowRight') {
            show(current + 1);
            event.preventDefault();
        }
    });
}

enhanceSort();
enhanceChart();
