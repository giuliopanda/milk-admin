/**
 * SuggestionSystem - Una classe per aggiungere suggerimenti automatici a textarea
 * 
 * @author Claude (versione migliorata)
 */
class SuggestionSystem {
    /**
     * Crea un nuovo SuggestionSystem
     * @param {HTMLTextAreaElement} textarea - L'elemento textarea a cui aggiungere i suggerimenti
     * @param {Object} suggestions - Oggetto contenente i suggerimenti organizzati
     * @param {Object} options - Opzioni di configurazione
     */
    constructor(textarea, suggestions, options = {}) {
      // Elementi DOM
      this.textarea = textarea;
      this.suggestionsData = suggestions;
      
      // Inizializza le opzioni di stile base
      this.defaultStyles = {
        container: 'suggestions-container',
        item: 'suggestion-item',
        activeItem: 'active-suggestion'
      };
      
      this.defaultTypeStyles = {
        keyword: 'keyword',
        table: 'table-name',
        field: 'field-name'
      };
      
      // Opzioni di configurazione con valori di default
      this.options = {
        maxHeight: options.maxHeight || 200,
        addQuotes: options.addQuotes !== undefined ? options.addQuotes : true,
        styles: options.styles || this.defaultStyles,
        typeStyles: options.typeStyles || this.defaultTypeStyles,
        externalCSS: options.externalCSS || false
      };
      
      // Aggiunge le regole CSS alle opzioni dopo aver inizializzato styles
      this.options.cssRules = options.cssRules || this.getDefaultCSSRules();
      
      // Stato interno
      this.currentSuggestions = [];
      this.activeSuggestionIndex = -1;
      
      // Creare il container per i suggerimenti
      this.createSuggestionsContainer();
      
      // Aggiungere gli stili CSS se non forniti esternamente
      if (!this.options.externalCSS) {
        this.addStyles();
      }
      
      // Inizializzare gli event listener
      this.initEventListeners();
    }
    
    /**
     * Crea il container per i suggerimenti
     */
    createSuggestionsContainer() {
      // Creare un container per i suggerimenti
      this.suggestionsContainer = document.createElement('div');
      this.suggestionsContainer.className = this.options.styles.container;
      this.suggestionsContainer.style.display = 'none';
      this.suggestionsContainer.style.maxHeight = `${this.options.maxHeight}px`;
      
      // Inserire il container dopo la textarea
      this.textarea.parentNode.insertBefore(
        this.suggestionsContainer,
        this.textarea.nextSibling
      );
      
      // Impostare la posizione del container
      // Usa position absolute rispetto al contenitore parent che deve essere position relative
      this.suggestionsContainer.style.position = 'absolute';
      this.suggestionsContainer.style.width = '100%';
      this.suggestionsContainer.style.left = '0';
      this.suggestionsContainer.style.top = `${this.textarea.offsetHeight}px`;
      
      // Aggiungere un'osservatore per aggiornare la posizione quando la textarea cambia dimensione
      const resizeObserver = new ResizeObserver(() => {
        this.suggestionsContainer.style.width = '100%';
        this.suggestionsContainer.style.top = `${this.textarea.offsetHeight}px`;
      });
      
      resizeObserver.observe(this.textarea);
    }
    
    /**
     * Aggiunge gli stili CSS al documento
     */
    addStyles() {
      const styleElement = document.createElement('style');
      styleElement.textContent = this.options.cssRules;
      document.head.appendChild(styleElement);
    }
    
    /**
     * Restituisce le regole CSS predefinite
     * @return {string} Regole CSS
     */
    getDefaultCSSRules() {
      const containerClass = this.options.styles.container;
      const itemClass = this.options.styles.item;
      const activeItemClass = this.options.styles.activeItem;
      const keywordClass = this.options.typeStyles.keyword;
      const tableClass = this.options.typeStyles.table;
      const fieldClass = this.options.typeStyles.field;
      
      return `
        .${containerClass} {
          position: absolute;
          width: 100%;
          max-height: ${this.options.maxHeight}px;
          overflow-y: auto;
          background-color: #fff;
          border: 1px solid #ced4da;
          border-radius: 0 0 0.25rem 0.25rem;
          z-index: 1000;
          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .${itemClass} {
          padding: 8px 12px;
          cursor: pointer;
        }
        .${itemClass}:hover {
          background-color: #f8f9fa;
        }
        .${activeItemClass} {
          background-color: #e9ecef;
        }
        .${keywordClass} {
          color: #0d6efd;
          font-weight: bold;
        }
        .${tableClass} {
          color: #198754;
        }
        .${fieldClass} {
          color: #dc3545;
        }
      `;
    }
    
    /**
     * Inizializza tutti gli event listener
     */
    initEventListeners() {
      // Event listener per input e click nella textarea
      this.textarea.addEventListener('input', this.handleSuggestions.bind(this));
      this.textarea.addEventListener('click', this.handleSuggestions.bind(this));
      
      // Event listener per i tasti freccia, enter, escape e tab
      this.textarea.addEventListener('keydown', this.handleKeyDown.bind(this));
      
      // Event listener per click al di fuori della textarea
      document.addEventListener('click', (e) => {
        if (!this.textarea.contains(e.target) && !this.suggestionsContainer.contains(e.target)) {
          this.hideSuggestions();
        }
      });
    }
    
    /**
     * Pulisce il testo dagli apici backtick per la ricerca
     * @param {string} text - Il testo da pulire
     * @return {string} Il testo pulito
     */
    cleanTextForSearch(text) {
      return text.replace(/`/g, '');
    }
    
    /**
     * Estrae la parola corrente senza apici backtick
     * @param {string} textBeforeCursor - Testo prima del cursore
     * @return {Object} Oggetto con la parola corrente e informazioni sul contesto
     */
    extractCurrentWord(textBeforeCursor) {
      // Trova l'inizio della parola corrente
      const lastSpaceIndex = textBeforeCursor.lastIndexOf(' ');
      const lastNewlineIndex = textBeforeCursor.lastIndexOf('\n');
      const lastTabIndex = textBeforeCursor.lastIndexOf('\t');
      const lastBreakIndex = Math.max(lastSpaceIndex, lastNewlineIndex, lastTabIndex);
      
      // Estrae la parola corrente
      let currentWord = textBeforeCursor.substring(lastBreakIndex + 1).trim();
      
      // Verifica se siamo in un contesto di tabella.campo
      const dotPosition = currentWord.lastIndexOf('.');
      let tablePrefix = '';
      let fieldPrefix = '';
      let isDotContext = false;
      
      if (dotPosition > -1) {
        tablePrefix = currentWord.substring(0, dotPosition);
        fieldPrefix = currentWord.substring(dotPosition + 1);
        isDotContext = true;
        
        // Rimuovi eventuali apici backtick dal prefisso tabella
        tablePrefix = this.cleanTextForSearch(tablePrefix);
      }
      
      // Rimuovi eventuali apici backtick dalla parola corrente
      currentWord = this.cleanTextForSearch(currentWord);
      
      return {
        currentWord,
        lastBreakIndex,
        isDotContext,
        tablePrefix,
        fieldPrefix
      };
    }
    
    /**
     * Gestisce i suggerimenti quando l'utente digita o clicca
     */
    handleSuggestions() {
      // Ottiene la posizione del cursore
      const cursorPosition = this.textarea.selectionStart;
      const textBeforeCursor = this.textarea.value.substring(0, cursorPosition);
      
      // Estrae la parola corrente senza apici backtick
      const { currentWord } = this.extractCurrentWord(textBeforeCursor);
      
      // Se la parola corrente è vuota, nascondi i suggerimenti
      if (currentWord === '') {
        this.hideSuggestions();
        return;
      }
      
      // Cerca i suggerimenti che corrispondono alla parola corrente
      const matchingSuggestions = this.findMatchingSuggestions(currentWord, textBeforeCursor);
      
      // Mostra i suggerimenti se ce ne sono
      if (matchingSuggestions.length > 0) {
        this.showSuggestions(matchingSuggestions, currentWord);
      } else {
        this.hideSuggestions();
      }
    }
    
    /**
     * Trova i suggerimenti che corrispondono alla parola corrente
     * @param {string} currentWord - La parola corrente (già pulita dagli apici)
     * @param {string} textBeforeCursor - Il testo prima del cursore
     * @return {Array} Array di suggerimenti corrispondenti
     */
    findMatchingSuggestions(currentWord, textBeforeCursor) {
      // Algoritmo di ricerca dei suggerimenti
      let suggestions = [];
      
      // Verifica se i suggerimenti sono strutturati come un oggetto SQL
      if (this.suggestionsData.keywords && this.suggestionsData.tables && this.suggestionsData.fields) {
        // Suggerimenti strutturati per query SQL
        suggestions = this.findSQLSuggestions(currentWord, textBeforeCursor);
      } else {
        // Suggerimenti semplici da array
        suggestions = this.findSimpleSuggestions(currentWord);
      }
      
      return suggestions;
    }
    
    /**
     * Trova suggerimenti semplici da array
     * @param {string} currentWord - La parola corrente (già pulita dagli apici)
     * @return {Array} Array di suggerimenti corrispondenti
     */
    findSimpleSuggestions(currentWord) {
      // Assumiamo che this.suggestionsData sia un array semplice
      const items = Array.isArray(this.suggestionsData) ? 
        this.suggestionsData : 
        Object.values(this.suggestionsData).flat();
      
      return items
        .filter(item => {
          const itemText = typeof item === 'string' ? item : item.text;
          // Pulisci il testo dell'item dagli apici backtick per la comparazione
          const cleanItemText = this.cleanTextForSearch(itemText);
          return cleanItemText.toLowerCase().startsWith(currentWord.toLowerCase());
        })
        .map(item => {
          return typeof item === 'string' ? 
            { text: item, type: 'default' } : 
            item;
        });
    }
    
    /**
     * Trova suggerimenti per query SQL
     * @param {string} currentWord - La parola corrente (già pulita dagli apici)
     * @param {string} textBeforeCursor - Il testo prima del cursore
     * @return {Array} Array di suggerimenti corrispondenti
     */
    findSQLSuggestions(currentWord, textBeforeCursor) {
      let suggestions = [];
      
      // Pulisci il testo dagli apici backtick per l'analisi del contesto
      const cleanTextBeforeCursor = this.cleanTextForSearch(textBeforeCursor);
      
      // Estrai informazioni sul contesto attuale
      const { isDotContext, tablePrefix, fieldPrefix } = this.extractCurrentWord(textBeforeCursor);
      
      // Verifica se siamo dopo un FROM o JOIN per suggerire tabelle
      const fromMatch = /\bFROM\s+(\w*)$/i.exec(cleanTextBeforeCursor);
      const joinMatch = /\b(JOIN)\s+(\w*)$/i.exec(cleanTextBeforeCursor);
      
      if (isDotContext) {
        // Siamo in un contesto tabella.campo, suggerisci solo i campi della tabella specificata
        if (this.suggestionsData.fields[tablePrefix]) {
          // Tabella trovata esattamente
          suggestions = this.suggestionsData.fields[tablePrefix]
            .filter(field => field.toLowerCase().startsWith(fieldPrefix.toLowerCase()))
            .map(field => ({ text: `${tablePrefix}.${field}`, type: 'field' }));
        } else {
          // Cerca tabelle simili se la tabella specificata non esiste esattamente
          this.suggestionsData.tables.forEach(table => {
            // Rimuovi eventuali apici per la comparazione
            const cleanTableName = this.cleanTextForSearch(table);
            if (cleanTableName.toLowerCase().startsWith(tablePrefix.toLowerCase())) {
              // Per ogni campo di questa tabella
              this.suggestionsData.fields[table].forEach(field => {
                if (field.toLowerCase().startsWith(fieldPrefix.toLowerCase())) {
                  suggestions.push({ text: `${table}.${field}`, type: 'field' });
                }
              });
            }
          });
        }
      } else if (fromMatch || joinMatch) {
        // Suggerimenti per tabelle dopo FROM/JOIN
        const tablePrefix = fromMatch ? fromMatch[1] : joinMatch[2];
        
        // Aggiungi le tabelle corrispondenti
        const matchingTables = this.suggestionsData.tables
          .filter(table => {
            // Rimuovi eventuali apici per la comparazione
            const cleanTableName = this.cleanTextForSearch(table);
            return cleanTableName.toLowerCase().startsWith(tablePrefix.toLowerCase());
          });
        
        // Prima aggiungi le tabelle
        suggestions = matchingTables.map(table => ({ text: table, type: 'table' }));
        
        // Poi aggiungi tutti i campi di ogni tabella mostrata
        matchingTables.forEach(table => {
          // Per ogni campo di questa tabella
          this.suggestionsData.fields[table].forEach(field => {
            suggestions.push({ text: `${table}.${field}`, type: 'field' });
          });
        });
      } else {
        // Verifica se siamo dopo un SELECT per suggerire campi
        const selectMatch = /\bSELECT\s+(\w*)$/i.exec(cleanTextBeforeCursor);
        
        if (selectMatch) {
          // Aggiungi * come suggerimento speciale dopo SELECT
          if ('*'.startsWith(selectMatch[1])) {
            suggestions.push({ text: '*', type: 'keyword' });
          }
          
          // Aggiungi tutti i campi di tutte le tabelle ma solo con prefisso tabella
          for (const table in this.suggestionsData.fields) {
            this.suggestionsData.fields[table].forEach(field => {
              const cleanField = this.cleanTextForSearch(field);
              if (cleanField.toLowerCase().startsWith(selectMatch[1].toLowerCase())) {
                suggestions.push({ text: `${table}.${field}`, type: 'field' });
              }
            });
          }
        } else {
          // Suggerimenti generali basati sulla parola corrente
          // Aggiungi prima le parole chiave
          suggestions = this.suggestionsData.keywords
            .filter(keyword => keyword.toLowerCase().startsWith(currentWord.toLowerCase()))
            .map(keyword => ({ text: keyword, type: 'keyword' }));
          
          // Aggiungi poi le tabelle
          const matchingTables = this.suggestionsData.tables
            .filter(table => {
              const cleanTableName = this.cleanTextForSearch(table);
              return cleanTableName.toLowerCase().startsWith(currentWord.toLowerCase());
            });
          
          // Aggiungi le tabelle corrispondenti
          suggestions = [
            ...suggestions,
            ...matchingTables.map(table => ({ text: table, type: 'table' }))
          ];
          
          // Per ogni tabella mostrata, aggiungi anche tutti i suoi campi
          matchingTables.forEach(table => {
            this.suggestionsData.fields[table].forEach(field => {
              suggestions.push({ text: `${table}.${field}`, type: 'field' });
            });
          });
          
          // Aggiungi i campi che corrispondono direttamente alla ricerca, ma solo con prefisso tabella
          if (/^[a-z]/i.test(currentWord)) {
            for (const table in this.suggestionsData.fields) {
              if (this.suggestionsData.fields[table]) {
                this.suggestionsData.fields[table].forEach(field => {
                  const cleanField = this.cleanTextForSearch(field);
                  if (cleanField.toLowerCase().startsWith(currentWord.toLowerCase())) {
                    suggestions.push({ text: `${table}.${field}`, type: 'field' });
                  }
                });
              } 
            }
          }
        }
      }
      
      return suggestions;
    }
    
    /**
     * Formatta il testo per la visualizzazione aggiungendo apici se necessario
     * @param {string} text - Il testo originale
     * @param {string} type - Il tipo di suggerimento
     * @return {string} Il testo formattato
     */
    formatDisplayText(text, type) {
      if (!this.options.addQuotes) {
        return text;
      }
      
      let displayText = text;
      
      if (type === 'table') {
        displayText = '`' + text + '`';
      } else if (type === 'field') {
        if (!text.includes('.')) {
          // Non dovrebbe accadere perché ora tutti i campi sono con prefisso tabella
          displayText = '`' + text + '`';
        } else {
          // Formatta tabella.campo con apici
          const parts = text.split('.');
          displayText = '`' + parts[0] + '`.' + '`' + parts[1] + '`';
        }
      }
      
      return displayText;
    }
    
    /**
     * Mostra i suggerimenti
     * @param {Array} suggestions - Array di suggerimenti
     * @param {string} currentWord - La parola corrente
     */
    showSuggestions(suggestions, currentWord) {
      // Salva i suggerimenti correnti
      this.currentSuggestions = suggestions;
      
      // Pulisci il contenitore dei suggerimenti
      this.suggestionsContainer.innerHTML = '';
      this.activeSuggestionIndex = -1;
      
      // Aggiungi ogni suggerimento al contenitore
      suggestions.forEach((suggestion, index) => {
        const suggestionElement = document.createElement('div');
        suggestionElement.className = this.options.styles.item;
        
        // Aggiungi classi di stile basate sul tipo di suggerimento
        let styleClass = '';
        let displayText = this.formatDisplayText(suggestion.text, suggestion.type);
        
        if (suggestion.type === 'keyword') {
          styleClass = this.options.typeStyles.keyword;
        } else if (suggestion.type === 'table') {
          styleClass = this.options.typeStyles.table;
        } else if (suggestion.type === 'field') {
          styleClass = this.options.typeStyles.field;
        }
        
        suggestionElement.innerHTML = `<span class="${styleClass}">${displayText}</span>`;
        
        // Aggiungi evento click per inserire il suggerimento
        suggestionElement.addEventListener('click', () => {
          this.applySuggestion(displayText, currentWord);
        });
        
        // Aggiungi evento hover
        suggestionElement.addEventListener('mouseover', () => {
          this.activeSuggestionIndex = index;
          this.updateActiveSuggestion();
        });
        
        this.suggestionsContainer.appendChild(suggestionElement);
      });
      
      // Mostra il contenitore dei suggerimenti
      this.suggestionsContainer.style.display = 'block';
      
      // Seleziona il primo suggerimento come attivo di default
      if (this.currentSuggestions.length > 0) {
        this.activeSuggestionIndex = 0;
        this.updateActiveSuggestion();
      }
    }
    
    /**
     * Nasconde i suggerimenti
     */
    hideSuggestions() {
      this.suggestionsContainer.style.display = 'none';
      this.activeSuggestionIndex = -1;
      this.currentSuggestions = [];
    }
    
    /**
     * Aggiorna il suggerimento attivo
     */
    updateActiveSuggestion() {
      const suggestionItems = this.suggestionsContainer.querySelectorAll(`.${this.options.styles.item}`);
      suggestionItems.forEach((item, index) => {
        if (index === this.activeSuggestionIndex) {
          item.classList.add(this.options.styles.activeItem);
          
          // Fai scorrere la scrollbar per mostrare l'elemento selezionato
          // Calcola la posizione dell'elemento rispetto al contenitore
          const containerTop = this.suggestionsContainer.scrollTop;
          const containerBottom = containerTop + this.suggestionsContainer.clientHeight;
          const elementTop = item.offsetTop;
          const elementBottom = elementTop + item.clientHeight;
          
          // Verifica se l'elemento è fuori dalla vista
          if (elementTop < containerTop) {
            // L'elemento è sopra la vista, scorrere verso l'alto
            this.suggestionsContainer.scrollTop = elementTop;
          } else if (elementBottom > containerBottom) {
            // L'elemento è sotto la vista, scorrere verso il basso
            this.suggestionsContainer.scrollTop = elementBottom - this.suggestionsContainer.clientHeight;
          }
        } else {
          item.classList.remove(this.options.styles.activeItem);
        }
      });
    }
    
    /**
     * Applica un suggerimento
     * @param {string} suggestion - Il suggerimento da applicare (formattato con apici)
     * @param {string} currentWord - La parola corrente
     */
    applySuggestion(suggestion, currentWord) {
      const cursorPosition = this.textarea.selectionStart;
      const textBeforeCursor = this.textarea.value.substring(0, cursorPosition);
      const textAfterCursor = this.textarea.value.substring(cursorPosition);
      
      // Estrai info sul contesto attuale
      const { lastBreakIndex, isDotContext, tablePrefix } = this.extractCurrentWord(textBeforeCursor);
      
      // Se siamo in un contesto tabella.campo, mantieni la tabella esistente
      if (isDotContext && suggestion.includes(tablePrefix)) {
        // Troviamo la posizione del punto nel testo originale
        const dotPos = textBeforeCursor.lastIndexOf('.');
        if (dotPos !== -1) {
          // Estrai solo la parte dopo il punto dal suggerimento
          const parts = suggestion.split('.');
          if (parts.length > 1) {
            // Sostituisci solo la parte dopo il punto
            const textBeforeDot = textBeforeCursor.substring(0, dotPos + 1);
            const newText = textBeforeDot + parts[1] + textAfterCursor;
            this.textarea.value = newText;
            
            // Posiziona il cursore dopo il suggerimento inserito
            const newCursorPosition = dotPos + 1 + parts[1].length;
            this.textarea.setSelectionRange(newCursorPosition, newCursorPosition);
          }
        }
      } else {
        // Sostituzione normale della parola completa
        const textBeforeWord = this.textarea.value.substring(0, lastBreakIndex + 1);
        const newText = textBeforeWord + suggestion + textAfterCursor;
        this.textarea.value = newText;
        
        // Posiziona il cursore dopo il suggerimento inserito
        const newCursorPosition = lastBreakIndex + 1 + suggestion.length;
        this.textarea.setSelectionRange(newCursorPosition, newCursorPosition);
      }
      
      // Nascondi i suggerimenti
      this.hideSuggestions();
      
      // Dai il focus di nuovo alla textarea
      this.textarea.focus();
    }
    
    /**
     * Gestisce gli eventi dei tasti
     * @param {KeyboardEvent} e - L'evento keydown
     */
    handleKeyDown(e) {
      if (this.suggestionsContainer.style.display === 'block') {
        switch (e.key) {
          case 'ArrowDown':
            e.preventDefault();
            this.activeSuggestionIndex = (this.activeSuggestionIndex + 1) % this.currentSuggestions.length;
            this.updateActiveSuggestion();
            break;
            
          case 'ArrowUp':
            e.preventDefault();
            this.activeSuggestionIndex = (this.activeSuggestionIndex - 1 + this.currentSuggestions.length) % this.currentSuggestions.length;
            this.updateActiveSuggestion();
            break;
            
          case 'Tab':
            e.preventDefault();
            // Seleziona il suggerimento successivo
            this.activeSuggestionIndex = (this.activeSuggestionIndex + 1) % this.currentSuggestions.length;
            this.updateActiveSuggestion();
            break;
            
          case 'Enter':
            if (this.activeSuggestionIndex >= 0) {
              e.preventDefault();
              // Estrai la parola attuale
              const cursorPosition = this.textarea.selectionStart;
              const textBeforeCursor = this.textarea.value.substring(0, cursorPosition);
              const { currentWord } = this.extractCurrentWord(textBeforeCursor);
              
              // Recupera il testo formattato con gli apici
              const suggestionItems = this.suggestionsContainer.querySelectorAll(`.${this.options.styles.item}`);
              const selectedItem = suggestionItems[this.activeSuggestionIndex];
              const displayText = selectedItem.textContent;
              
              this.applySuggestion(displayText, currentWord);
            }
            break;
            
          case 'Escape':
            e.preventDefault();
            this.hideSuggestions();
            break;
        }
      }
    }
    
    /**
     * Aggiorna i suggerimenti disponibili
     * @param {Object} newSuggestions - I nuovi suggerimenti
     */
    updateSuggestions(newSuggestions) {
      this.suggestionsData = newSuggestions;
    }
    
    /**
     * Distrugge l'istanza del sistema di suggerimenti
     */
    destroy() {
      // Rimuovi gli event listener
      this.textarea.removeEventListener('input', this.handleSuggestions);
      this.textarea.removeEventListener('click', this.handleSuggestions);
      this.textarea.removeEventListener('keydown', this.handleKeyDown);
      
      // Rimuovi il container
      if (this.suggestionsContainer.parentNode) {
        this.suggestionsContainer.parentNode.removeChild(this.suggestionsContainer);
      }
    }
}