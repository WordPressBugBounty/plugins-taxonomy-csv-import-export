(() => {
    const sidebar = document.getElementById('toplevel_page_taxonomy-csv-import-export');
    const submenu = sidebar?.querySelector('.wp-submenu');
    if (!submenu) return;

    const labels = window.taxocsieSidebarAutoTagging || {};
    const findItem = (slug) => Array.from(submenu.querySelectorAll(':scope > li > a[href]'))
        .find((link) => new URL(link.href, window.location.href).searchParams.get('page') === slug)?.closest('li');
    const syncGroups = [];

    const addGroup = (slugs, titleText, childLabels = {}) => {
        const items = slugs.map(findItem).filter(Boolean);
        if (items.length < 2) return;

        const group = document.createElement('li');
        group.className = 'taxocsie-sidebar-menu-group';
        const title = document.createElement('a');
        title.className = 'taxocsie-sidebar-menu-group__parent-link';
        title.href = items[0].querySelector('a').href;
        title.textContent = titleText;
        const children = document.createElement('ul');
        children.className = 'taxocsie-sidebar-menu-group__children';
        children.hidden = true;

        items.forEach((item) => {
            const link = item.querySelector('a');
            const slug = new URL(link.href, window.location.href).searchParams.get('page');
            if (childLabels[slug]) link.textContent = childLabels[slug];
        });
        items[0].before(group);
        children.append(...items);
        group.append(title, children);

        const setOpen = (open) => {
            children.hidden = !open;
            group.classList.toggle('is-open', open);
        };
        const syncActive = () => {
            const active = items.some((item) => item.classList.contains('current'));
            group.classList.toggle('is-current', active);
            setOpen(active || group.matches(':hover') || group.contains(document.activeElement));
        };
        syncGroups.push(syncActive);
        syncActive();
        group.addEventListener('mouseenter', () => setOpen(true));
        group.addEventListener('mouseleave', () => {
            if (!group.classList.contains('is-current')) setOpen(false);
        });
        group.addEventListener('focusin', () => setOpen(true));
        group.addEventListener('focusout', (event) => {
            if (!group.contains(event.relatedTarget) && !group.classList.contains('is-current')) setOpen(false);
        });
    };

    addGroup(
        ['taxonomy-csv-import-export-auto-tagging', 'taxonomy-csv-import-export-auto-tagging-ai'],
        labels.title || 'Auto Tagging',
        {
            'taxonomy-csv-import-export-auto-tagging': labels.manual || 'Manually',
            'taxonomy-csv-import-export-auto-tagging-ai': labels.ai || 'AI Suggestions',
        }
    );
    addGroup(
        [
            'taxocsie-term-meta-transfer',
            'taxocsie-term-maintenance',
            'taxonomy-csv-import-export-redirects',
            'taxonomy-csv-import-export-unused',
            'taxonomy-csv-import-export-duplicates',
        ],
        labels.termTools || 'Term Tools'
    );

    window.taxocsieSidebarMenuSync = () => syncGroups.forEach((sync) => sync());
})();
