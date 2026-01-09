// tab_session.js - Client-side tab session management

(function() {
    // Get or create tab ID from sessionStorage
    let tabId = sessionStorage.getItem('tab_id');

    if (!tabId) {
        // Generate new unique tab ID
        tabId = 'tab_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('tab_id', tabId);
    }

    // Add tab_id to all forms automatically
    document.addEventListener('DOMContentLoaded', function() {
        // Add hidden input to all forms
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            // Check if tab_id input already exists
            if (!form.querySelector('input[name="tab_id"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tab_id';
                input.value = tabId;
                form.appendChild(input);
            }
        });

        // Add tab_id to all links (except external and logout)
        const links = document.querySelectorAll('a');
        links.forEach(function(link) {
            const href = link.getAttribute('href');
            if (href && !href.startsWith('http') && !href.startsWith('#') && href !== 'logout.php') {
                const separator = href.includes('?') ? '&' : '?';
                link.href = href + separator + 'tab_id=' + encodeURIComponent(tabId);
            }
        });
    });

    // If current URL doesn't have tab_id, add it and reload
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('tab_id') && window.location.pathname !== '/' && window.location.pathname !== '/index.php') {
        const separator = window.location.search ? '&' : '?';
        const newUrl = window.location.pathname + window.location.search + separator + 'tab_id=' + encodeURIComponent(tabId);
        window.history.replaceState({}, '', newUrl);
    }
})();