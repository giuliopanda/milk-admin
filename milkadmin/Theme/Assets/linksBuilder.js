/**
 * LinksBuilder JavaScript Support
 * Funzioni generiche per supportare il filtering e interazioni dei LinksBuilder
 */
(function () {
    'use strict';
    
    /**
     * Filtra i link in un container LinksBuilder specifico
     * @param {string} searchText - Il testo da cercare
     * @param {string} containerId - L'ID del container LinksBuilder
     */
    window.filterLinks = function(searchText, containerId) {
        const normalizedSearch = searchText.toLowerCase().trim();
        
        // Se non viene fornito un containerId, cerca in tutto il documento
        const container = containerId ? document.getElementById(containerId) : document;
        if (!container) {
            console.warn('LinksBuilder container not found:', containerId);
            return;
        }
        
        const links = container.querySelectorAll('.doc-link, .nav-link, .link-action');
        const groups = container.querySelectorAll('.group-section, .docs-category');
        const searchResultElement = container.querySelector('[id$="ResultCount"], [id$="searchResultCount"]');
        
        let visibleCount = 0;
        let totalCount = 0;
        
        // Se non c'è testo di ricerca, mostra tutto
        if (!normalizedSearch) {
            links.forEach(link => {
                const listItem = link.closest('li');
                if (listItem) listItem.classList.remove('d-none');
            });
            groups.forEach(group => {
                group.classList.remove('d-none');
            });
            updateSearchResultCount(searchResultElement, null, null);
            return;
        }
        
        // Mappa per tenere traccia dei gruppi con almeno un risultato
        const groupsWithResults = new Set();
        
        // Traccia gli elementi li già processati per evitare duplicati
        const processedListItems = new Set();
        
        // Filtra i link
        links.forEach(link => {
            const listItem = link.closest('li');
            
            // Se abbiamo già processato questo li, salta
            if (listItem && processedListItems.has(listItem)) {
                return;
            }
            
            // Aggiungi il li al set dei processati
            if (listItem) {
                processedListItems.add(listItem);
                totalCount++;
            }
            
            // Estrae dati di ricerca dal data-search o dal testo del link
            let searchData = null;
            let searchableText = '';
            let title = '';
            let tags = '';
            
            // Prova a leggere data-search dal li o dal link stesso
            const dataSearchAttr = (listItem && listItem.getAttribute('data-search')) || 
                                   link.getAttribute('data-search');
            
            if (dataSearchAttr) {
                try {
                    // Decodifica HTML entities usando il metodo nativo del browser
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = dataSearchAttr;
                    const decodedAttr = tempDiv.textContent || tempDiv.innerText || '';
                    
                    searchData = JSON.parse(decodedAttr);
                    searchableText = (searchData.search || '').toLowerCase();
                    title = (searchData.title || '').toLowerCase();
                    tags = (searchData.tags || []).map(tag => tag.toLowerCase()).join(' ');
                } catch (e) {
                    // Se il parsing fallisce, usa il testo del link
                    title = link.textContent.toLowerCase();
                }
            } else {
                // Se non c'è data-search, usa il contenuto del link
                title = link.textContent.toLowerCase();
            }
            
            // Cerca nel titolo, nei tag e nel testo di ricerca
            const matches = title.includes(normalizedSearch) || 
                           tags.includes(normalizedSearch) ||
                           searchableText.includes(normalizedSearch);
            
            if (matches) {
                // Mostra l'elemento li (il contenitore principale)
                if (listItem) {
                    listItem.classList.remove('d-none');
                    visibleCount++;
                }
                
                // Trova il gruppo padre e aggiungilo al set
                const parentGroup = listItem ? listItem.closest('.group-section, .docs-category') : null;
                if (parentGroup) {
                    groupsWithResults.add(parentGroup);
                }
            } else {
                // Nascondi l'elemento li
                if (listItem) {
                    listItem.classList.add('d-none');
                }
            }
        });
        
        // Mostra/nascondi i gruppi in base ai risultati
        groups.forEach(group => {
            if (groupsWithResults.has(group)) {
                group.classList.remove('d-none');
            } else {
                group.classList.add('d-none');
            }
        });
        
        // Aggiorna il contatore dei risultati
        updateSearchResultCount(searchResultElement, visibleCount, totalCount);
    };
    
    /**
     * Aggiorna il contatore dei risultati di ricerca
     * @param {HTMLElement} countElement - L'elemento dove mostrare il contatore
     * @param {number} visible - Numero di link visibili
     * @param {number} total - Numero totale di link
     */
    function updateSearchResultCount(countElement, visible = null, total = null) {
        if (!countElement) return;
        
        if (visible === null || total === null) {
            // Conta tutti i link visibili nel container padre
            const container = countElement.closest('[id^="linksBuilder"], [id$="Container"]') || document;
            // Conta solo i <li> che contengono link (evita duplicati)
            const visibleLinks = container.querySelectorAll('li.nav-item:not(.d-none)');
            const totalLinks = container.querySelectorAll('li.nav-item');
            visible = visibleLinks.length;
            total = totalLinks.length;
        }
        
        // Trova l'input di ricerca per verificare se c'è una ricerca attiva
        const container = countElement.closest('[id^="linksBuilder"], [id$="Container"]') || document;
        const searchInput = container.querySelector('input[type="text"]');
        const hasSearch = searchInput && searchInput.value.trim().length > 0;
        
        if (hasSearch) {
            if (visible === 0) {
                countElement.textContent = 'No results found';
                countElement.className = 'text-danger mt-1 d-block show';
            } else if (visible === total) {
                countElement.textContent = `${visible} results found`;
                countElement.className = 'text-success mt-1 d-block show';
            } else {
                countElement.textContent = `${visible} of ${total} results`;
                countElement.className = 'text-body-secondary mt-1 d-block show';
            }
        } else {
            countElement.classList.remove('show');
            countElement.textContent = '';
        }
    }
    
    /**
     * Resetta il filtro di ricerca per un container specifico
     * @param {string} containerId - L'ID del container LinksBuilder
     */
    window.resetLinksFilter = function(containerId) {
        const container = containerId ? document.getElementById(containerId) : document;
        if (!container) return;
        
        const searchInput = container.querySelector('input[type="text"]');
        if (searchInput) {
            searchInput.value = '';
            filterLinks('', containerId);
        }
    };
    
    /**
     * Evidenzia il link attivo in base all'URL corrente
     * @param {string} containerId - L'ID del container LinksBuilder (opzionale)
     */
    window.highlightActiveLinks = function(containerId) {
        const container = containerId ? document.getElementById(containerId) : document;
        if (!container) return;
        
        const currentUrl = window.location.href;
        const urlParams = new URLSearchParams(window.location.search);
        const currentAction = urlParams.get('action');
        const currentPage = urlParams.get('page');
        
        const links = container.querySelectorAll('a[href]');
        
        links.forEach(link => {
            const linkUrl = new URL(link.href, window.location.origin);
            const linkAction = linkUrl.searchParams.get('action');
            const linkPage = linkUrl.searchParams.get('page');
            
            // Rimuovi classi attive esistenti
            link.classList.remove('active', 'doc-link-active', 'nav-link-active');
            
            // Controlla corrispondenza
            let isActive = false;
            if (currentAction && linkAction === currentAction) {
                isActive = true;
            } else if (!currentAction && currentPage && linkPage === currentPage) {
                isActive = true;
            } else if (link.href === currentUrl) {
                isActive = true;
            }
            
            if (isActive) {
                // Aggiungi la classe appropriata in base al tipo di link
                if (link.classList.contains('doc-link')) {
                    link.classList.add('doc-link-active');
                } else if (link.classList.contains('nav-link')) {
                    link.classList.add('nav-link-active');
                } else {
                    link.classList.add('active');
                }
                
                // Scroll per rendere visibile il link attivo se necessario
                const container = link.closest('[id^="linksBuilder"], [id$="Container"]');
                if (container) {
                    const linkRect = link.getBoundingClientRect();
                    const containerRect = container.getBoundingClientRect();
                    
                    if (linkRect.top < containerRect.top || linkRect.bottom > containerRect.bottom) {
                        link.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            }
        });
    };
    
    // Fallback per compatibilità con filterDocs esistente
    if (typeof window.filterDocs === 'undefined') {
        window.filterDocs = function(searchText) {
            // Cerca container docs specifici prima di usare filterLinks generico
            const docsContainer = document.getElementById('docsContainer') || 
                                 document.querySelector('.docs-sidebar') ||
                                 document;
            
            if (docsContainer.id) {
                filterLinks(searchText, docsContainer.id);
            } else {
                filterLinks(searchText);
            }
        };
    }
    
    // Auto-inizializzazione quando il DOM è pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Evidenzia automaticamente i link attivi in tutti i LinksBuilder
        const containers = document.querySelectorAll('[id^="linksBuilder"], [id$="Container"]');
        containers.forEach(container => {
            highlightActiveLinks(container.id);
        });
    });
    
})();