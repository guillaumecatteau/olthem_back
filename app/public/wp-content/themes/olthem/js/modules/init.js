import { createApiClient } from './services/api.js';
import { createRouterHelpers } from './services/router.js';
import {
    renderAppShell,
    renderTopNav,
    renderAdminShell,
    openBlogList,
    openBlogDetail,
    closeBlogOverlay,
    setActiveNav,
    scrollToSection,
} from './ui/render.js';

export function initApp() {
    const appRoot = document.getElementById('olthem-app');
    if (!appRoot) {
        return;
    }

    const config = window.OLTHEM_APP || {};
    const sectionIds = ['header', 'projet', 'thematiques', 'ressources', 'ateliers', 'partenaires'];

    const state = {
        user: null,
        sections: {},
        activePath: '/',
    };

    const router = createRouterHelpers(config.homeUrl || '/', sectionIds);
    const api = createApiClient(config.apiProxyBase || '/wp-json/olthem/v1/proxy/', config.nonce || '');

    function createDefaultSections() {
        return {
            header: {
                title: 'Header',
                description: 'Top section, hero and quick links.',
            },
            projet: {
                title: 'Projet',
                description: 'Project summary from external backend.',
            },
            thematiques: {
                title: 'Thematiques',
                description: 'Thematic categories and blog entry point.',
            },
            ressources: {
                title: 'Ressources',
                description: 'Resources list loaded from external DB.',
            },
            ateliers: {
                title: 'Ateliers',
                description: 'Workshops section.',
            },
            partenaires: {
                title: 'Partenaires',
                description: 'Partner logos and links.',
            },
        };
    }

    async function loadSession() {
        try {
            const me = await api.request('/auth/me');
            state.user = {
                name: me.name || me.email || 'User',
                role: me.role || 'user',
            };
        } catch (error) {
            state.user = null;
        }
    }

    async function loadSections() {
        try {
            const payload = await api.request('/content/sections');
            if (payload && typeof payload === 'object') {
                state.sections = payload;
                return;
            }
        } catch (error) {
            // Fallback below if API is not ready yet.
        }

        state.sections = createDefaultSections();
    }

    function navigate(path, replace) {
        const target = router.normalize(path);
        const absolute = router.absolutePath(target);

        if (replace) {
            history.replaceState({}, '', absolute);
        } else {
            history.pushState({}, '', absolute);
        }

        void handleRoute(target);
    }

    async function handleRoute(path, options) {
        const routePath = router.normalize(path);
        const skipScroll = options && options.skipScroll;

        state.activePath = routePath;

        renderTopNav({
            path: routePath,
            user: state.user,
            absolutePath: router.absolutePath,
        });

        renderAdminShell({
            path: routePath,
            user: state.user,
        });

        if (router.isAdminPath(routePath)) {
            closeBlogOverlay();
            return;
        }

        if (router.isBlogPath(routePath)) {
            scrollToSection('thematiques', skipScroll ? 'auto' : 'smooth');

            if (routePath === '/blog') {
                await openBlogList({
                    request: api.request,
                    absolutePath: router.absolutePath,
                });
            } else {
                const slug = routePath.split('/')[2];
                await openBlogDetail({
                    slug,
                    request: api.request,
                    absolutePath: router.absolutePath,
                });
            }
            return;
        }

        closeBlogOverlay();

        if (router.isSectionPath(routePath)) {
            const sectionId = router.sectionFromPath(routePath);
            scrollToSection(sectionId, skipScroll ? 'auto' : 'smooth');
            return;
        }

        navigate('/header', true);
    }

    function setupLinkInterception() {
        document.addEventListener('click', function(event) {
            const target = event.target.closest('[data-route]');
            if (!target) {
                return;
            }

            const route = target.getAttribute('data-route');
            if (!route) {
                return;
            }

            event.preventDefault();
            navigate(route, false);
        });
    }

    function setupScrollAddressSync() {
        const observer = new IntersectionObserver(function(entries) {
            const sectionEntry = entries
                .filter(function(entry) {
                    return entry.isIntersecting;
                })
                .sort(function(a, b) {
                    return b.intersectionRatio - a.intersectionRatio;
                })[0];

            if (!sectionEntry) {
                return;
            }

            if (router.isBlogPath(state.activePath) || router.isAdminPath(state.activePath)) {
                return;
            }

            const id = sectionEntry.target.id;
            const path = id === 'header' ? '/header' : '/' + id;

            if (state.activePath === path) {
                return;
            }

            state.activePath = path;
            history.replaceState({}, '', router.absolutePath(path));
            setActiveNav(path);
        }, {
            threshold: [0.45, 0.6, 0.8],
        });

        sectionIds.forEach(function(id) {
            const section = document.getElementById(id);
            if (section) {
                observer.observe(section);
            }
        });
    }

    async function bootstrap() {
        await Promise.all([loadSession(), loadSections()]);

        renderAppShell({
            appRoot,
            sectionIds,
            sections: state.sections,
        });

        setupLinkInterception();
        setupScrollAddressSync();

        window.addEventListener('popstate', function() {
            void handleRoute(router.appPathFromLocation(), { skipScroll: false });
        });

        await handleRoute(router.appPathFromLocation(), { skipScroll: true });
    }

    void bootstrap();
}
