function escapeHtml(input) {
    return String(input)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function createSectionContent(sectionData, id) {
    const title = sectionData.title || id;
    const text = sectionData.description || 'This block is ready. Connect your external API to load real content.';
    return '<h2>' + escapeHtml(title) + '</h2><p>' + escapeHtml(text) + '</p>';
}

export function renderAppShell({ appRoot, sectionIds, sections }) {
    const sectionsMarkup = sectionIds.map(function(id) {
        return '<section id="' + id + '" class="site-section">' + createSectionContent(sections[id] || {}, id) + '</section>';
    }).join('');

    appRoot.innerHTML = [
        '<nav class="top-nav" id="top-nav"></nav>',
        '<div class="sections" id="site-sections">' + sectionsMarkup + '</div>',
        '<div class="admin-shell" id="admin-shell"></div>'
    ].join('');

    const thematiques = document.getElementById('thematiques');
    if (thematiques) {
        thematiques.insertAdjacentHTML('beforeend', '<div id="blog-overlay" class="blog-overlay" aria-live="polite"></div>');
    }
}

export function setActiveNav(path) {
    document.querySelectorAll('.nav-link[data-route]').forEach(function(link) {
        const route = link.getAttribute('data-route');
        if (route === path) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

export function renderTopNav({ path, user, absolutePath }) {
    const nav = document.getElementById('top-nav');
    if (!nav) {
        return;
    }

    const isAdmin = user && user.role === 'admin';
    const adminMode = path === '/admin-tool' || path.startsWith('/admin-tool/');

    const publicLinks = [
        ['Header', '/header'],
        ['Projet', '/projet'],
        ['Thematiques', '/thematiques'],
        ['Ressources', '/ressources'],
        ['Ateliers', '/ateliers'],
        ['Partenaires', '/partenaires'],
        ['Blog', '/blog'],
    ];

    const adminLinks = [
        ['Admin home', '/admin-tool'],
        ['Users', '/admin-tool/users'],
        ['Content', '/admin-tool/content'],
        ['Edit DB', '/admin-tool/edit'],
    ];

    const links = adminMode ? adminLinks : publicLinks;
    const linksMarkup = links.map(function(item) {
        return '<a href="' + absolutePath(item[1]) + '" class="nav-link" data-route="' + item[1] + '">' + item[0] + '</a>';
    }).join('');

    const switchButton = isAdmin
        ? (adminMode
            ? '<button class="nav-button" data-route="/thematiques">Back to site</button>'
            : '<button class="nav-button" data-route="/admin-tool">AdminTool</button>')
        : '';

    const userLabel = user
        ? 'Connected as ' + user.name + ' (' + user.role + ')'
        : 'Visitor mode';

    nav.innerHTML = '<div class="top-nav-inner">' + linksMarkup + switchButton + '<span class="user-pill">' + userLabel + '</span></div>';
    setActiveNav(path);
}

export function renderAdminShell({ path, user }) {
    const adminShell = document.getElementById('admin-shell');
    const sectionsNode = document.getElementById('site-sections');
    if (!adminShell || !sectionsNode) {
        return;
    }

    const adminMode = path === '/admin-tool' || path.startsWith('/admin-tool/');
    if (!adminMode) {
        adminShell.classList.remove('active');
        sectionsNode.style.display = 'block';
        return;
    }

    sectionsNode.style.display = 'none';
    adminShell.classList.add('active');

    const isAdmin = user && user.role === 'admin';
    if (!isAdmin) {
        adminShell.innerHTML = [
            '<div class="admin-panel">',
            '<h2>AdminTool</h2>',
            '<p class="warning">Access denied. You must be admin.</p>',
            '<p>You can keep this route, but real protection must also be done by your external backend.</p>',
            '</div>'
        ].join('');
        return;
    }

    adminShell.innerHTML = [
        '<div class="admin-panel">',
        '<h2>AdminTool area</h2>',
        '<p>This page is loaded without reload. Data should come from your external backend.</p>',
        '<div class="admin-grid">',
        '<div class="admin-card"><h3>Users</h3><p>View and edit user profiles from external DB.</p></div>',
        '<div class="admin-card"><h3>Thematiques</h3><p>Edit thematic blocks and section ordering.</p></div>',
        '<div class="admin-card"><h3>Ressources</h3><p>Update resource cards and attached files.</p></div>',
        '<div class="admin-card"><h3>Publications</h3><p>Manage blog posts and publication status.</p></div>',
        '</div>',
        '<p>Current route: <strong>' + escapeHtml(path) + '</strong></p>',
        '</div>'
    ].join('');
}

export function closeBlogOverlay() {
    const overlay = document.getElementById('blog-overlay');
    if (!overlay) {
        return;
    }

    overlay.classList.remove('open');
    overlay.innerHTML = '';
}

export async function openBlogList({ request, absolutePath }) {
    const overlay = document.getElementById('blog-overlay');
    if (!overlay) {
        return;
    }

    overlay.classList.add('open');
    overlay.innerHTML = '<p>Loading blog posts...</p>';

    try {
        const posts = await request('/blog');
        const safePosts = Array.isArray(posts) ? posts : [];

        if (!safePosts.length) {
            overlay.innerHTML = '<h3>Blog</h3><p>No post found yet.</p>';
            return;
        }

        const cards = safePosts.map(function(post) {
            const slug = post.slug || '';
            const title = post.title || slug || 'Untitled post';
            const excerpt = post.excerpt || '';

            return [
                '<article class="blog-card">',
                '<h4><a href="' + absolutePath('/blog/' + encodeURIComponent(slug)) + '" data-route="/blog/' + encodeURIComponent(slug) + '">' + escapeHtml(title) + '</a></h4>',
                '<p>' + escapeHtml(excerpt) + '</p>',
                '</article>'
            ].join('');
        }).join('');

        overlay.innerHTML = '<h3>Blog list</h3>' + cards;
    } catch (error) {
        overlay.innerHTML = '<h3>Blog</h3><p>Cannot load posts. Check external API.</p>';
    }
}

export async function openBlogDetail({ slug, request, absolutePath }) {
    const overlay = document.getElementById('blog-overlay');
    if (!overlay) {
        return;
    }

    overlay.classList.add('open');
    overlay.innerHTML = '<p>Loading article...</p>';

    try {
        const post = await request('/blog/' + encodeURIComponent(slug));
        const title = post.title || slug;
        const content = post.content || 'No content received.';
        overlay.innerHTML = [
            '<h3>' + escapeHtml(title) + '</h3>',
            '<p><a href="' + absolutePath('/blog') + '" data-route="/blog">Back to blog list</a></p>',
            '<div class="blog-card"><p>' + escapeHtml(content) + '</p></div>'
        ].join('');
    } catch (error) {
        overlay.innerHTML = '<h3>Article not available</h3><p>Check slug and external API endpoint.</p>';
    }
}

export function scrollToSection(sectionId, behavior) {
    const section = document.getElementById(sectionId);
    if (!section) {
        return;
    }

    section.scrollIntoView({ behavior: behavior || 'smooth', block: 'start' });
}
