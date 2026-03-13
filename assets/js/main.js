(() => {
    const menuToggle = document.querySelector('.menu-toggle');
    const mainNav = document.querySelector('.main-nav');

    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', () => {
            const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
            menuToggle.setAttribute('aria-expanded', String(!isExpanded));
            mainNav.classList.toggle('is-open');
        });

        mainNav.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth > 980) {
                    return;
                }
                menuToggle.setAttribute('aria-expanded', 'false');
                mainNav.classList.remove('is-open');
            });
        });
    }

    const getScrollAmount = (slider) => {
        const firstCard = slider.querySelector('.product-card');
        if (!firstCard) {
            return 250;
        }

        const cardRect = firstCard.getBoundingClientRect();
        const sliderStyles = window.getComputedStyle(slider);
        const gap = parseFloat(sliderStyles.columnGap || sliderStyles.gap || '0');
        return cardRect.width + gap;
    };

    document.querySelectorAll('[data-slide-prev], [data-slide-next]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-slide-prev') || btn.getAttribute('data-slide-next');
            if (!id) {
                return;
            }

            const slider = document.getElementById(id);
            if (!slider) {
                return;
            }

            const isNext = btn.hasAttribute('data-slide-next');
            const amount = getScrollAmount(slider) * (isNext ? 1 : -1);
            slider.scrollBy({ left: amount, behavior: 'smooth' });
        });
    });

    const accordion = document.querySelector('[data-accordion]');
    if (accordion) {
        const triggers = accordion.querySelectorAll('.faq-trigger');

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const item = trigger.closest('.faq-item');
                if (!item) {
                    return;
                }

                const panel = item.querySelector('.faq-panel');
                if (!panel) {
                    return;
                }

                const expand = trigger.getAttribute('aria-expanded') !== 'true';

                triggers.forEach((btn) => {
                    btn.setAttribute('aria-expanded', 'false');
                    const btnItem = btn.closest('.faq-item');
                    const btnPanel = btnItem ? btnItem.querySelector('.faq-panel') : null;
                    if (btnPanel) {
                        btnPanel.hidden = true;
                    }
                });

                trigger.setAttribute('aria-expanded', String(expand));
                panel.hidden = !expand;
            });
        });
    }

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    const searchModal = document.querySelector('[data-search-modal]');
    if (searchModal) {
        const searchButtons = document.querySelectorAll('[data-search-open]');
        const searchCloseButtons = searchModal.querySelectorAll('[data-search-close]');
        const searchInput = searchModal.querySelector('[data-search-input]');
        const searchResults = searchModal.querySelector('[data-search-results]');
        const searchEmpty = searchModal.querySelector('[data-search-empty]');
        const searchForm = searchModal.querySelector('[data-search-form]');
        const toneWhitelist = new Set(['rose', 'peach', 'sand', 'mist', 'mint']);
        let closeTarget = null;
        let debounceTimer = null;
        let requestId = 0;

        const formatPrice = (value) => {
            const amount = Number(value || 0);
            return Number.isFinite(amount) ? amount.toLocaleString('en-PK', { maximumFractionDigits: 0 }) : '0';
        };

        const escapeHtml = (value) =>
            String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

        const renderSearchResults = (products) => {
            if (!searchResults || !searchEmpty) {
                return;
            }

            if (!Array.isArray(products) || products.length === 0) {
                searchResults.innerHTML = '';
                searchEmpty.hidden = false;
                return;
            }

            const cards = products.map((product) => {
                const id = Number(product.id || 0);
                const name = escapeHtml(product.name || 'Product');
                const shortName = escapeHtml(product.short_name || product.name || 'Item');
                const slug = String(product.slug || '').trim();
                const tone = toneWhitelist.has(String(product.tone || 'rose')) ? String(product.tone) : 'rose';
                const url = slug !== '' ? `product.php?slug=${encodeURIComponent(slug)}` : `product.php?id=${id}`;
                const price = formatPrice(product.price);
                const comparePrice =
                    product.compare_price !== null && Number(product.compare_price) > Number(product.price)
                        ? `<span class="compare-price">Rs. ${formatPrice(product.compare_price)}</span>`
                        : '';
                const soldOut =
                    String(product.availability || 'in_stock') !== 'in_stock'
                        ? `<span class="sold-out">${escapeHtml(product.stock_label || 'Out of Stock')}</span>`
                        : '';
                const media = product.image_path
                    ? `<img src="${escapeHtml(product.image_path)}" alt="${name}" loading="lazy">`
                    : `<div class="search-card-fallback">${shortName}</div>`;

                return `
                    <article class="search-card tone-${tone}">
                        <a href="${url}" class="search-card-media tone-${tone}">
                            ${media}
                            ${soldOut}
                        </a>
                        <h3><a href="${url}">${name}</a></h3>
                        <p class="price">Rs. ${price}${comparePrice}</p>
                    </article>
                `;
            });

            searchResults.innerHTML = cards.join('');
            searchEmpty.hidden = true;
        };

        const runSearch = async (queryText) => {
            const query = String(queryText || '').trim();
            const currentRequest = ++requestId;
            const url = `search-products.php?q=${encodeURIComponent(query)}&limit=12`;

            try {
                const response = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Search request failed');
                }

                const payload = await response.json();
                if (currentRequest !== requestId) {
                    return;
                }

                renderSearchResults(Array.isArray(payload.products) ? payload.products : []);
            } catch (error) {
                if (currentRequest !== requestId) {
                    return;
                }
                renderSearchResults([]);
            }
        };

        const onEscape = (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            closeModal();
        };

        const setModalState = (isOpen, restoreFocus = false) => {
            searchModal.hidden = !isOpen;
            document.body.classList.toggle('search-open', isOpen);
            searchButtons.forEach((btn) => btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false'));

            if (isOpen) {
                window.addEventListener('keydown', onEscape);
                return;
            }

            window.removeEventListener('keydown', onEscape);

            if (debounceTimer) {
                window.clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            if (restoreFocus && closeTarget instanceof HTMLElement) {
                closeTarget.focus();
            }
        };

        const openModal = (triggerButton) => {
            closeTarget = triggerButton || null;
            setModalState(true);
            runSearch(searchInput ? searchInput.value : '');
            window.requestAnimationFrame(() => {
                if (searchInput instanceof HTMLElement) {
                    searchInput.focus();
                    searchInput.select();
                }
            });
        };

        function closeModal() {
            setModalState(false, true);
        }

        setModalState(false);
        window.addEventListener('pageshow', () => {
            closeTarget = null;
            setModalState(false);
        });

        searchModal.addEventListener('click', (event) => {
            const closeTrigger = event.target.closest('[data-search-close]');
            if (closeTrigger) {
                event.preventDefault();
                closeModal();
            }
        });

        searchButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                openModal(btn);
            });
        });

        searchCloseButtons.forEach((btn) => {
            btn.addEventListener('click', (event) => {
                event.preventDefault();
                closeModal();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (debounceTimer) {
                    window.clearTimeout(debounceTimer);
                }

                debounceTimer = window.setTimeout(() => {
                    runSearch(searchInput.value);
                }, 220);
            });
        }

        if (searchForm) {
            searchForm.addEventListener('submit', () => {
                closeModal();
            });
        }
    }
})();
