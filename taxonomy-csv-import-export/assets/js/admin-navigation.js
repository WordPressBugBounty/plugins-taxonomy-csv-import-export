(() => {
    const navigation = document.querySelector('.taxocsie-admin-navigation');
    const content = document.getElementById('wpbody-content');
    if (!navigation || !content) {
        return;
    }

    const groups = Array.from(navigation.querySelectorAll('.taxocsie-admin-navigation__group'));
    const closeOtherGroups = (current = null) => groups.forEach((group) => {
        if (group !== current) {
            group.open = false;
        }
    });

    document.addEventListener('pointerdown', (event) => {
        const current = navigation.contains(event.target)
            ? event.target.closest('.taxocsie-admin-navigation__group')
            : null;
        closeOtherGroups(current);
    });
    document.addEventListener('focusin', (event) => {
        if (!navigation.contains(event.target)) {
            closeOtherGroups();
        }
    });
    groups.forEach((group) => group.addEventListener('toggle', () => {
        if (group.open) {
            closeOtherGroups(group);
        }
    }));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const openGroup = groups.find((group) => group.open);
            if (openGroup) {
                closeOtherGroups();
                openGroup.querySelector('summary')?.focus();
                event.preventDefault();
            }
        }
    });

    const initialUrl = new URL(window.location.href);
    const initialTitle = document.title;
    const links = Array.from(navigation.querySelectorAll('a[href]'))
        .filter((link) => !link.classList.contains('taxocsie-admin-navigation__subgroup-title')
            && !link.classList.contains('taxocsie-admin-navigation__help'));
    const pluginPages = new Set(links.map((link) => new URL(link.href).searchParams.get('page')));
    const cache = new Map();
    const cacheLifetime = 30000;
    const maxCachedPages = 3;
    const accountPage = 'taxonomy-csv-import-export-license-account';
    let activeEntry = null;
    let wantedUrl = initialUrl.href;
    let initialStale = false;
    let resizeObserver = null;
    let prefetchTimer = null;

    const isPluginPage = (url) => url.origin === initialUrl.origin
        && url.pathname === initialUrl.pathname
        && pluginPages.has(url.searchParams.get('page'));

    const isAccountPage = (url) => url.searchParams.get('page') === accountPage;

    const embeddedUrl = (url) => {
        const embedded = new URL(url.href);
        embedded.searchParams.set('taxocsie_embed', '1');
        return embedded.href;
    };

    const updateActiveMenu = (url) => {
        const page = url.searchParams.get('page');
        navigation.querySelectorAll('.is-active').forEach((item) => item.classList.remove('is-active'));
        navigation.querySelectorAll('[aria-current="page"]').forEach((item) => item.removeAttribute('aria-current'));
        const activeLink = links.find((link) => new URL(link.href).searchParams.get('page') === page);
        if (activeLink) {
            activeLink.classList.add('is-active');
            activeLink.setAttribute('aria-current', 'page');
            activeLink.closest('.taxocsie-admin-navigation__group')?.classList.add('is-active');
        }

        const sidebar = document.getElementById('toplevel_page_taxonomy-csv-import-export');
        if (sidebar) {
            sidebar.querySelectorAll('.wp-submenu li.current, .wp-submenu a.current').forEach((item) => item.classList.remove('current'));
            sidebar.querySelectorAll('.wp-submenu a[aria-current="page"]').forEach((item) => item.removeAttribute('aria-current'));
            const sidebarPage = page === 'taxonomy-csv-import-export-create'
                ? 'taxonomy-csv-import-export-all-terms'
                : page;
            const sidebarLink = Array.from(sidebar.querySelectorAll('.wp-submenu a[href]:not(.taxocsie-sidebar-menu-group__parent-link)'))
                .find((link) => new URL(link.href, window.location.href).searchParams.get('page') === sidebarPage);
            if (sidebarLink) {
                sidebarLink.classList.add('current');
                sidebarLink.parentElement.classList.add('current');
                sidebarLink.setAttribute('aria-current', 'page');
            }
            window.taxocsieSidebarMenuSync?.();
        }
    };

    const minimumFrameHeight = () => Math.max(480, window.innerHeight - navigation.getBoundingClientRect().bottom - 20);

    const resizeActiveFrame = () => {
        if (!activeEntry) {
            return;
        }
        try {
            const frame = activeEntry.frame;
            const page = frame.contentDocument;
            if (!page) {
                return;
            }
            const height = Math.max(minimumFrameHeight(), page.body?.scrollHeight || 0, page.documentElement?.scrollHeight || 0);
            if (Math.abs(frame.offsetHeight - height) > 4) {
                frame.style.height = `${height}px`;
            }
        } catch (error) {
            // A cross-origin redirect is handled by the frame load listener.
        }
    };

    const removeEntry = (entry) => {
        if (activeEntry === entry) {
            entry.discardAfterSwitch = true;
            cache.delete(entry.url);
            return;
        }
        entry.disposed = true;
        cache.delete(entry.url);
        entry.frame.remove();
    };

    const trimCache = () => {
        if (cache.size <= maxCachedPages) {
            return;
        }
        const candidates = Array.from(cache.values())
            .filter((entry) => entry !== activeEntry && entry.url !== wantedUrl)
            .sort((a, b) => a.lastUsed - b.lastUsed);
        while (cache.size > maxCachedPages && candidates.length) {
            removeEntry(candidates.shift());
        }
    };

    const invalidateOtherPages = (currentEntry) => {
        initialStale = true;
        cache.forEach((entry) => {
            if (entry !== currentEntry) {
                entry.stale = true;
            }
        });
    };

    content.addEventListener('submit', () => invalidateOtherPages(null), true);
    content.addEventListener('click', (event) => {
        if (event.target.closest('button, input[type="button"], input[type="submit"]')) {
            invalidateOtherPages(null);
        }
    }, true);

    const showEntry = (entry) => {
        if (activeEntry && activeEntry !== entry) {
            if (activeEntry.discardAfterSwitch) {
                activeEntry.frame.remove();
            } else {
                activeEntry.frame.classList.add('is-cached');
            }
        }
        resizeObserver?.disconnect();
        activeEntry = entry;
        entry.lastUsed = Date.now();
        entry.frame.classList.remove('is-pending', 'is-cached');
        content.classList.remove('taxocsie-content-preparing');
        content.classList.add('taxocsie-content-routing');
        document.body.classList.add('taxocsie-content-routing');
        document.title = entry.frame.contentDocument?.title || initialTitle;
        resizeActiveFrame();
        if (window.ResizeObserver && entry.frame.contentDocument?.body) {
            resizeObserver = new ResizeObserver(resizeActiveFrame);
            resizeObserver.observe(entry.frame.contentDocument.body);
        }
        window.scrollTo(0, 0);
        trimCache();
    };

    const showInitialPage = () => {
        resizeObserver?.disconnect();
        if (activeEntry) {
            if (activeEntry.discardAfterSwitch) {
                activeEntry.frame.remove();
            } else {
                activeEntry.frame.classList.add('is-cached');
            }
            activeEntry = null;
        }
        content.classList.remove('taxocsie-content-routing', 'taxocsie-content-preparing');
        document.body.classList.remove('taxocsie-content-routing');
        document.title = initialTitle;
        window.scrollTo(0, 0);
    };

    const loadEntry = (url) => {
        const cached = cache.get(url.href);
        if (cached && !cached.stale && (cached.loaded ? Date.now() - cached.loadedAt < cacheLifetime : Date.now() - cached.createdAt < 15000)) {
            cached.lastUsed = Date.now();
            return cached;
        }
        if (cached) {
            removeEntry(cached);
        }

        const frame = document.createElement('iframe');
        const entry = {
            url: url.href,
            frame,
            loaded: false,
            loadedAt: 0,
            lastUsed: Date.now(),
            createdAt: Date.now(),
            stale: false,
        };
        frame.className = 'taxocsie-content-frame is-pending';
        frame.title = links.find((link) => new URL(link.href).searchParams.get('page') === url.searchParams.get('page'))?.textContent.trim() || 'WP Taxonomy Manager';
        frame.style.height = `${minimumFrameHeight()}px`;
        if (!activeEntry) {
            content.classList.add('taxocsie-content-preparing');
        }
        content.appendChild(frame);
        cache.set(entry.url, entry);
        frame.addEventListener('error', () => {
            if (wantedUrl === entry.url) {
                window.location.assign(entry.url);
            }
        });

        frame.addEventListener('load', () => {
            if (entry.disposed) {
                return;
            }
            let loadedUrl;
            let loadedPage;
            try {
                loadedUrl = new URL(frame.contentWindow.location.href);
                loadedPage = frame.contentDocument;
            } catch (error) {
                if (wantedUrl === entry.url) {
                    window.location.assign(entry.url);
                } else {
                    removeEntry(entry);
                }
                return;
            }
            if (loadedUrl.href === 'about:blank') {
                return;
            }
            if (!isPluginPage(loadedUrl)) {
                if (wantedUrl === entry.url) {
                    window.location.assign(loadedUrl.href);
                } else {
                    removeEntry(entry);
                }
                return;
            }
            if (isAccountPage(loadedUrl)) {
                const accountUrl = new URL(loadedUrl.href);
                accountUrl.searchParams.delete('taxocsie_embed');
                if (wantedUrl === entry.url) {
                    window.location.assign(accountUrl.href);
                } else {
                    removeEntry(entry);
                }
                return;
            }
            if (loadedUrl.searchParams.get('taxocsie_embed') !== '1') {
                frame.classList.add('is-pending');
                try {
                    frame.contentWindow.location.replace(embeddedUrl(loadedUrl));
                } catch (error) {
                    window.location.assign(loadedUrl.href);
                }
                return;
            }

            const cleanUrl = new URL(loadedUrl.href);
            cleanUrl.searchParams.delete('taxocsie_embed');
            const oldUrl = entry.url;
            if (cleanUrl.href !== oldUrl) {
                const duplicate = cache.get(cleanUrl.href);
                if (duplicate && duplicate !== entry) {
                    if (duplicate === activeEntry && wantedUrl !== oldUrl) {
                        removeEntry(entry);
                        return;
                    }
                    removeEntry(duplicate);
                }
                cache.delete(oldUrl);
                entry.url = cleanUrl.href;
                cache.set(entry.url, entry);
            }
            entry.loaded = true;
            entry.loadedAt = Date.now();

            // Events inside the routed iframe do not reach the parent document.
            loadedPage.addEventListener('pointerdown', () => closeOtherGroups(), true);
            loadedPage.addEventListener('focusin', () => closeOtherGroups(), true);
            loadedPage.addEventListener('submit', () => invalidateOtherPages(entry), true);
            loadedPage.addEventListener('click', (event) => {
                const link = event.target.closest('a[href]');
                if (event.target.closest('button, input[type="button"], input[type="submit"]')) {
                    invalidateOtherPages(entry);
                }
                if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') || (link.target && link.target !== '_self')) {
                    return;
                }
                const linkUrl = new URL(link.href, loadedUrl.href);
                if (isPluginPage(linkUrl)) {
                    event.preventDefault();
                    route(linkUrl);
                } else if (linkUrl.origin === initialUrl.origin && linkUrl.pathname === initialUrl.pathname) {
                    event.preventDefault();
                    window.location.assign(linkUrl.href);
                }
            });

            if (wantedUrl === oldUrl || wantedUrl === entry.url) {
                wantedUrl = entry.url;
                if (window.location.href !== entry.url) {
                    window.history.replaceState({ taxocsieRoute: entry.url }, '', entry.url);
                }
                updateActiveMenu(new URL(entry.url));
                showEntry(entry);
            } else {
                if (activeEntry !== entry) {
                    frame.classList.remove('is-pending');
                    frame.classList.add('is-cached');
                }
                trimCache();
            }
        });

        try {
            frame.contentWindow.location.replace(embeddedUrl(url));
        } catch (error) {
            window.location.assign(url.href);
        }
        trimCache();
        return entry;
    };

    const route = (url, pushHistory = true) => {
        const destination = new URL(url.href);
        destination.searchParams.delete('taxocsie_embed');
        if (!isPluginPage(destination)) {
            window.location.assign(destination.href);
            return;
        }
        // Freemius puts its hosted checkout in an iframe and sizes it from the
        // parent viewport. A second routing iframe makes those sizes feed back.
        if (isAccountPage(destination)) {
            window.location.assign(destination.href);
            return;
        }
        if (destination.href === wantedUrl) {
            closeOtherGroups();
            return;
        }

        wantedUrl = destination.href;
        closeOtherGroups();
        updateActiveMenu(destination);
        if (pushHistory) {
            window.history.pushState({ taxocsieRoute: destination.href }, '', destination.href);
        }

        if (destination.href === initialUrl.href && !initialStale && !cache.has(destination.href)) {
            showInitialPage();
            return;
        }

        const entry = loadEntry(destination);
        if (entry.loaded) {
            showEntry(entry);
        }
    };

    document.addEventListener('click', (event) => {
        const link = event.target.closest('.taxocsie-admin-navigation a[href], #toplevel_page_taxonomy-csv-import-export a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        const destination = new URL(link.href);
        if (isPluginPage(destination)) {
            event.preventDefault();
            route(destination);
        }
    });

    const schedulePrefetch = (event) => {
        const link = event.target.closest('a[href]');
        if (!link) {
            return;
        }
        const destination = new URL(link.href);
        if (!isPluginPage(destination) || isAccountPage(destination) || destination.href === wantedUrl) {
            return;
        }
        clearTimeout(prefetchTimer);
        prefetchTimer = setTimeout(() => loadEntry(destination), 150);
    };

    navigation.addEventListener('pointerover', schedulePrefetch);
    navigation.addEventListener('focusin', schedulePrefetch);
    document.getElementById('toplevel_page_taxonomy-csv-import-export')?.addEventListener('pointerover', schedulePrefetch);

    const cancelPrefetch = (event) => {
        const link = event.target.closest('a[href]');
        if (link && !link.contains(event.relatedTarget)) {
            clearTimeout(prefetchTimer);
        }
    };

    navigation.addEventListener('pointerout', cancelPrefetch);
    document.getElementById('toplevel_page_taxonomy-csv-import-export')?.addEventListener('pointerout', cancelPrefetch);

    window.addEventListener('popstate', () => route(new URL(window.location.href), false));
    window.addEventListener('resize', resizeActiveFrame);
})();
