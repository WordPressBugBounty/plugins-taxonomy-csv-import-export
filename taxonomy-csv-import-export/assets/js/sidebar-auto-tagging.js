(() => {
    const sidebar = document.getElementById('toplevel_page_taxonomy-csv-import-export');
    const submenu = sidebar?.querySelector('.wp-submenu');
    if (!submenu) return;

    const labels = window.taxocsieSidebarAutoTagging || {};
    const findItem = (slug) => Array.from(submenu.querySelectorAll(':scope > li > a[href]'))
        .find((link) => new URL(link.href, window.location.href).searchParams.get('page') === slug)?.closest('li');
    const syncGroups = [];
    const groups = [];

    const addGroup = (slugs, titleText, childLabels = {}, openFirstPage = false) => {
        const items = slugs.map(findItem).filter(Boolean);
        if (items.length < 2) return;

        const group = document.createElement('li');
        group.className = 'taxocsie-sidebar-menu-group';
        const title = document.createElement(openFirstPage ? 'a' : 'button');
        if (openFirstPage) {
            title.href = items[0].querySelector('a').href;
            group.classList.add('has-page-link');
        } else {
            title.type = 'button';
        }
        title.className = 'taxocsie-sidebar-menu-group__parent-link';
        title.textContent = titleText;
        const toggle = openFirstPage ? document.createElement('button') : title;
        if (openFirstPage) {
            toggle.type = 'button';
            toggle.className = 'taxocsie-sidebar-menu-group__toggle';
            toggle.setAttribute('aria-label', `${labels.toggleSubmenu || 'Toggle submenu'}: ${titleText}`);
        }
        const children = document.createElement('ul');
        children.className = 'taxocsie-sidebar-menu-group__children';
        children.id = `taxocsie-sidebar-${slugs[0]}`;
        children.hidden = true;
        toggle.setAttribute('aria-controls', children.id);
        toggle.setAttribute('aria-expanded', 'false');

        items.forEach((item) => {
            const link = item.querySelector('a');
            const slug = new URL(link.href, window.location.href).searchParams.get('page');
            if (childLabels[slug]) link.textContent = childLabels[slug];
        });
        items[0].before(group);
        children.append(...items);
        group.append(title);
        if (openFirstPage) group.append(toggle);
        group.append(children);

        const setOpen = (open) => {
            group.classList.toggle('is-open', open);
            children.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
        };
        toggle.addEventListener('click', () => {
            const shouldOpen = !group.classList.contains('is-open');
            groups.forEach((entry) => {
                if (entry.group !== group && !entry.group.classList.contains('is-current')) {
                    entry.setOpen(false);
                }
            });
            setOpen(shouldOpen);
        });
        group.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && group.classList.contains('is-open')) {
                setOpen(false);
                toggle.focus();
                event.preventDefault();
            }
        });
        groups.push({ group, setOpen });

        const syncActive = () => {
            const active = items.some((item) => item.classList.contains('current'));
            const wasActive = group.classList.contains('is-current');
            group.classList.toggle('is-current', active);
            if (active && !wasActive) setOpen(true);
            if (!active && wasActive) setOpen(false);
        };
        syncGroups.push(syncActive);
        syncActive();
    };

    addGroup(
        ['taxonomy-csv-import-export-auto-tagging', 'taxonomy-csv-import-export-auto-tagging-ai'],
        labels.title || 'Auto Tagging',
        {
            'taxonomy-csv-import-export-auto-tagging': labels.manual || 'Manually',
            'taxonomy-csv-import-export-auto-tagging-ai': labels.ai || 'AI Suggestions',
        },
        true
    );
    addGroup(
        [
            'taxocsie-term-meta-transfer',
            'taxocsie-term-maintenance',
            'taxonomy-csv-import-export-redirects',
            'taxonomy-csv-import-export-unused',
            'taxonomy-csv-import-export-duplicates',
        ],
        labels.termTools || 'Term Tools',
        {},
        true
    );

    document.addEventListener('click', (event) => {
        groups.forEach((entry) => {
            if (!entry.group.contains(event.target) && !entry.group.classList.contains('is-current')) {
                entry.setOpen(false);
            }
        });
    });

    window.taxocsieSidebarMenuSync = () => syncGroups.forEach((sync) => sync());
})();
