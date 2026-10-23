// Filter add-on cards by name and description as the user types.
(function () {
    'use strict';

    const search = document.querySelector('[data-addon-search]');
    const input = search ? search.querySelector('input') : null;
    const cards = Array.from(document.querySelectorAll('[data-addon-search-text]'));
    if (!input || !cards.length) {
        return;
    }

    const labels = window.taxocsieAddonToggle || {};
    const status = document.querySelector('[data-addon-search-status]');
    const empty = document.querySelector('[data-addon-search-empty]');
    // Case- and accent-insensitive matching.
    const normalize = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const index = cards.map((card) => normalize(card.dataset.addonSearchText || card.textContent));
    let announceTimer = 0;

    const apply = () => {
        const words = normalize(input.value).split(/\s+/).filter(Boolean);
        let visible = 0;
        cards.forEach((card, i) => {
            const match = words.every((word) => index[i].includes(word));
            card.hidden = !match;
            visible += match ? 1 : 0;
        });
        empty.hidden = visible > 0;

        // Announce once typing pauses, and only while a search is active.
        window.clearTimeout(announceTimer);
        announceTimer = window.setTimeout(() => {
            if (!words.length) {
                status.textContent = '';
            } else if (!visible) {
                status.textContent = labels.searchNone || '';
            } else {
                status.textContent = ((visible === 1 ? labels.searchResult : labels.searchResults) || '%d').replace('%d', visible);
            }
        }, 400);
    };

    search.hidden = false;
    input.addEventListener('input', apply);
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && input.value) {
            input.value = '';
            apply();
            event.preventDefault();
        }
    });
    document.querySelector('[data-addon-search-clear]').addEventListener('click', () => {
        input.value = '';
        apply();
        input.focus();
    });
    if (input.value) {
        apply();
    }
}());

(function () {
    'use strict';

    if (typeof taxocsieAddonToggle === 'undefined') {
        return;
    }

    function updateMenu(form, enabled) {
        const slug = form.dataset.menuSlug;
        if (!slug) {
            return;
        }
        const menu = document.querySelector('#toplevel_page_taxonomy-csv-import-export .wp-submenu');
        if (!menu) {
            return;
        }
        const links = Array.from(menu.querySelectorAll('a[href]'));
        const match = links.find((link) => new URL(link.href, window.location.href).searchParams.get('page') === slug);
        let item = match ? match.closest('li') : null;
        if (!enabled) {
            if (item) {
                item.style.display = 'none';
            }
            return;
        }
        if (!item) {
            item = document.createElement('li');
            const link = document.createElement('a');
            link.href = form.dataset.menuUrl;
            link.textContent = form.dataset.addonName;
            item.appendChild(link);
            const addonsLink = links.find((link) => new URL(link.href, window.location.href).searchParams.get('page') === 'taxonomy-csv-import-export-addons');
            menu.insertBefore(item, addonsLink ? addonsLink.closest('li') : null);
        }
        item.style.display = '';
    }

    function updateCard(form, enabled) {
        const card = form.closest('[data-addon-card]');
        const button = form.querySelector('.taxocsie-addon-toggle');
        const input = form.querySelector('input[name="enabled"]');
        const name = form.dataset.addonName;
        const needsWoo = card.dataset.requiresWoocommerce === '1';
        const needsAcf = card.dataset.requiresAcf === '1';
        input.value = enabled ? '0' : '1';
        button.classList.toggle('is-on', enabled);
        button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        button.setAttribute('aria-label', (enabled ? taxocsieAddonToggle.offAction : taxocsieAddonToggle.onAction).replace('%s', name));
        button.textContent = enabled ? taxocsieAddonToggle.on : taxocsieAddonToggle.off;
        card.querySelector('[data-addon-off]').hidden = enabled;
        card.querySelector('[data-addon-needs-woo]').hidden = !enabled || !needsWoo;
        card.querySelector('[data-addon-needs-acf]').hidden = !enabled || !needsAcf;
        const open = card.querySelector('[data-addon-open]');
        if (open) {
            open.hidden = !enabled || needsWoo || needsAcf;
        }
        updateMenu(form, enabled);
    }

    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-addon-toggle-form]')) {
            return;
        }
        event.preventDefault();
        const button = form.querySelector('.taxocsie-addon-toggle');
        if (button.disabled) {
            return;
        }
        const feedback = form.parentElement.querySelector('[data-addon-feedback]');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        feedback.textContent = '';
        feedback.classList.remove('is-error');
        try {
            const response = await fetch(taxocsieAddonToggle.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form)
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.data && result.data.message ? result.data.message : taxocsieAddonToggle.error);
            }
            updateCard(form, Boolean(result.data.enabled));
            feedback.textContent = taxocsieAddonToggle.saved;
        } catch (error) {
            feedback.textContent = error.message || taxocsieAddonToggle.error;
            feedback.classList.add('is-error');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
}());
