/**
 * pagination.js
 * Renders numbered page controls from the API's `meta` block:
 *   { current_page, per_page, total, total_pages }
 *
 * Usage:
 *   Pagination.render(containerEl, meta, { onPageChange: (page) => {...} });
 *
 * Shows every page when total_pages <= maxButtons (default 10),
 * otherwise collapses with gaps: 1 … 5 6 7 8 9 10 … 42
 */
(function (global) {
    'use strict';

    function range(start, end) {
        const out = [];
        for (let i = start; i <= end; i++) out.push(i);
        return out;
    }

    /**
     * Returns an array of page numbers and 'gap' markers.
     * Always exactly `maxButtons` items once total > maxButtons, so the
     * control doesn't jump in width as the user pages.
     */
    function getPageItems(current, total, maxButtons) {
        maxButtons = Math.max(7, maxButtons || 10);

        if (total <= maxButtons) return range(1, total);

        const edgeCount = maxButtons - 2;   // pages shown when current is near an end
        const middleCount = maxButtons - 4; // pages between the two gaps

        if (current <= edgeCount - 1) {
            return [...range(1, edgeCount), 'gap', total];
        }
        if (current >= total - edgeCount + 2) {
            return [1, 'gap', ...range(total - edgeCount + 1, total)];
        }

        const start = current - Math.floor(middleCount / 2);
        return [1, 'gap', ...range(start, start + middleCount - 1), 'gap', total];
    }

    function makeButton(label, page, opts) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-button' + (opts.extraClass ? ' ' + opts.extraClass : '');
        btn.textContent = label;
        btn.dataset.page = String(page);
        if (opts.ariaLabel) btn.setAttribute('aria-label', opts.ariaLabel);
        if (opts.current) btn.setAttribute('aria-current', 'page');
        if (opts.disabled) btn.disabled = true;
        return btn;
    }

    function render(container, meta, options) {
        if (!container) return;
        options = options || {};

        const current = Math.max(1, Number(meta && meta.current_page) || 1);
        const total = Math.max(0, Number(meta && meta.total_pages) || 0);

        // Remember whether the user was operating the control with the keyboard,
        // so focus can follow them to the new current page after re-render.
        const hadFocus = container.contains(document.activeElement);

        container.innerHTML = '';

        if (total <= 1) {
            container.hidden = true;
            return;
        }
        container.hidden = false;

        const list = document.createElement('div');
        list.className = 'pagination-list';

        list.appendChild(makeButton('Previous', current - 1, {
            extraClass: 'page-step',
            ariaLabel: 'Previous page',
            disabled: current <= 1
        }));

        getPageItems(current, total, options.maxButtons).forEach(function (item) {
            if (item === 'gap') {
                const gap = document.createElement('span');
                gap.className = 'page-gap';
                gap.setAttribute('aria-hidden', 'true');
                gap.textContent = '…';
                list.appendChild(gap);
                return;
            }
            list.appendChild(makeButton(String(item), item, {
                ariaLabel: 'Page ' + item,
                current: item === current
            }));
        });

        list.appendChild(makeButton('Next', current + 1, {
            extraClass: 'page-step',
            ariaLabel: 'Next page',
            disabled: current >= total
        }));

        container.appendChild(list);

        // Assigning onclick (not addEventListener) so repeated renders never stack handlers.
        container.onclick = function (event) {
            const btn = event.target.closest('button[data-page]');
            if (!btn || btn.disabled || btn.getAttribute('aria-current') === 'page') return;
            const page = Number(btn.dataset.page);
            if (page >= 1 && page <= total && typeof options.onPageChange === 'function') {
                options.onPageChange(page);
            }
        };

        if (hadFocus) {
            const target = container.querySelector('[aria-current="page"]');
            if (target) target.focus();
        }
    }

    function setDisabled(container, disabled) {
        if (!container) return;
        container.querySelectorAll('button[data-page]').forEach(function (btn) {
            if (disabled) {
                btn.dataset.wasDisabled = btn.disabled ? '1' : '';
                btn.disabled = true;
            } else if (btn.dataset.wasDisabled !== undefined) {
                btn.disabled = btn.dataset.wasDisabled === '1';
                delete btn.dataset.wasDisabled;
            }
        });
    }

    global.Pagination = { render: render, setDisabled: setDisabled, getPageItems: getPageItems };
})(window);