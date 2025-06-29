/**
 * Javascript per il sistema di documentazione
 * Gestisce il filtro real-time dei documenti nella sidebar
 */
(function () {
    'use strict';
    
    // Inizializza il sistema quando il DOM è pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Evidenzia il documento attivo nella sidebar
        highlightActiveDocument();
        
        // Inizializza il contatore dei risultati
        updateSearchResultCount();
    });
})();

/**
 * Filtra i documenti nella sidebar in base al testo di ricerca
 * @param {string} searchText - Il testo da cercare
 */
function filterDocs(searchText) {
    const normalizedSearch = searchText.toLowerCase().trim();
    const docLinks = document.querySelectorAll('.doc-link');
    const categories = document.querySelectorAll('.docs-category');
    
    let visibleCount = 0;
    let totalCount = 0;
    
    // Se non c'è testo di ricerca, mostra tutto
    if (!normalizedSearch) {
        docLinks.forEach(link => {
            link.classList.remove('hidden');
        });
        categories.forEach(category => {
            category.classList.remove('hidden');
        });
        updateSearchResultCount();
        return;
    }
    
    // Mappa per tenere traccia delle categorie con almeno un risultato
    const categoriesWithResults = new Set();
    
    // Filtra i documenti
    docLinks.forEach(link => {
        const searchData = link.getAttribute('data-search');
        
        if (searchData) {
            try {
                const data = JSON.parse(searchData);
                const searchableText = (data.search || '').toLowerCase();
                const title = (data.title || '').toLowerCase();
                const tags = (data.tags || []).map(tag => tag.toLowerCase()).join(' ');
                
                // Cerca nel titolo, nei tag e nel testo di ricerca
                const matches = title.includes(normalizedSearch) || 
                               tags.includes(normalizedSearch) ||
                               searchableText.includes(normalizedSearch);
                
                if (matches) {
                    link.classList.remove('hidden');
                    visibleCount++;
                    
                    // Trova la categoria padre e aggiungila al set
                    const parentCategory = link.closest('.docs-category');
                    if (parentCategory) {
                        categoriesWithResults.add(parentCategory);
                    }
                } else {
                    link.classList.add('hidden');
                }
                
                totalCount++;
            } catch (e) {
                console.error('Errore nel parsing dei dati di ricerca:', e);
                link.classList.remove('hidden');
            }
        }
    });
    
    // Mostra/nascondi le categorie in base ai risultati
    categories.forEach(category => {
        if (categoriesWithResults.has(category)) {
            category.classList.remove('hidden');
        } else {
            category.classList.add('hidden');
        }
    });
    
    // Aggiorna il contatore dei risultati
    updateSearchResultCount(visibleCount, totalCount);
}

/**
 * Aggiorna il contatore dei risultati di ricerca
 * @param {number} visible - Numero di documenti visibili
 * @param {number} total - Numero totale di documenti
 */
function updateSearchResultCount(visible = null, total = null) {
    const countElement = document.getElementById('searchResultCount');
    if (!countElement) return;
    
    if (visible === null || total === null) {
        // Conta tutti i documenti visibili
        const visibleLinks = document.querySelectorAll('.doc-link:not(.hidden)');
        const totalLinks = document.querySelectorAll('.doc-link');
        visible = visibleLinks.length;
        total = totalLinks.length;
    }
    
    const searchInput = document.getElementById('docsSearchInput');
    const hasSearch = searchInput && searchInput.value.trim().length > 0;
    
    if (hasSearch) {
        if (visible === 0) {
            countElement.textContent = 'Nessun documento trovato';
            countElement.className = 'text-danger mt-1 d-block show';
        } else if (visible === total) {
            countElement.textContent = `${visible} documenti trovati`;
            countElement.className = 'text-success mt-1 d-block show';
        } else {
            countElement.textContent = `${visible} di ${total} documenti`;
            countElement.className = 'text-muted mt-1 d-block show';
        }
    } else {
        countElement.classList.remove('show');
        countElement.textContent = '';
    }
}

/**
 * Evidenzia il documento attivo nella sidebar
 */
function highlightActiveDocument() {
    // Ottieni l'URL corrente
    const currentUrl = window.location.href;
    const urlParams = new URLSearchParams(window.location.search);
    const currentAction = urlParams.get('action');
    
    if (!currentAction) return;
    
    // Trova tutti i link nella sidebar
    const links = document.querySelectorAll('.doc-link .nav-link');
    
    links.forEach(link => {
        const linkUrl = new URL(link.href, window.location.origin);
        const linkAction = linkUrl.searchParams.get('action');
        
        if (linkAction === currentAction) {
            link.classList.add('active');
            
            // Assicurati che il link attivo sia visibile scrollando se necessario
            const sidebar = link.closest('.docs-sidebar');
            if (sidebar) {
                const linkRect = link.getBoundingClientRect();
                const sidebarRect = sidebar.getBoundingClientRect();
                
                if (linkRect.top < sidebarRect.top || linkRect.bottom > sidebarRect.bottom) {
                    link.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        } else {
            link.classList.remove('active');
        }
    });
}

/**
 * Espande/contrae una categoria (per future implementazioni)
 * @param {HTMLElement} categoryElement - L'elemento categoria da toggle
 */
function toggleCategory(categoryElement) {
    const categoryList = categoryElement.nextElementSibling;
    if (categoryList) {
        categoryList.classList.toggle('collapsed');
        categoryElement.classList.toggle('collapsed');
    }
}

/**
 * Resetta il filtro di ricerca
 */
function resetSearch() {
    const searchInput = document.getElementById('docsSearchInput');
    if (searchInput) {
        searchInput.value = '';
        filterDocs('');
    }
}

// Esporta le funzioni globalmente per compatibilità
window.filterDocs = filterDocs;
window.toggleCategory = toggleCategory;
window.resetSearch = resetSearch;