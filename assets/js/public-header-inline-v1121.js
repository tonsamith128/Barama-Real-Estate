/* BARAMA_PUBLIC_HEADER_INLINE_V1121_JS */
(() => {
    'use strict';

    const LINK_SELECTOR = '.site-header .main-nav a';
    const LABEL_CLASS = 'barama-nav-label-v1121';

    function shouldWrapTextNode(node) {
        if (!node || node.nodeType !== Node.TEXT_NODE) return false;
        if (!node.nodeValue || !node.nodeValue.trim()) return false;
        const parent = node.parentElement;
        if (!parent) return false;
        if (parent.closest('svg, script, style, template')) return false;
        if (parent.classList.contains(LABEL_CLASS)) return false;
        return true;
    }

    function normalizeLink(link) {
        if (!(link instanceof Element)) return;

        const accessibleLabel = (link.getAttribute('aria-label') || link.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();

        const walker = document.createTreeWalker(
            link,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode(node) {
                    return shouldWrapTextNode(node)
                        ? NodeFilter.FILTER_ACCEPT
                        : NodeFilter.FILTER_REJECT;
                }
            }
        );

        const textNodes = [];
        while (walker.nextNode()) textNodes.push(walker.currentNode);

        textNodes.forEach((node) => {
            const span = document.createElement('span');
            span.className = LABEL_CLASS;
            span.textContent = node.nodeValue;
            node.parentNode.replaceChild(span, node);
        });

        link.classList.add('barama-nav-icon-link-v1121');
        if (accessibleLabel && !link.getAttribute('aria-label')) {
            link.setAttribute('aria-label', accessibleLabel);
        }
    }

    function normalizeHeaderNavigation() {
        document.querySelectorAll(LINK_SELECTOR).forEach(normalizeLink);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeHeaderNavigation, { once: true });
    } else {
        normalizeHeaderNavigation();
    }
})();
