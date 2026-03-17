/**
 * Syntax Highlighter - VS Code Light Theme
 * Simple regex-based approach that works in all browsers
 */
(function() {

    const escapeHtml = str => str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    // Placeholder system to protect already-processed tokens
    let placeholders = [];

    const savePlaceholder = (html) => {
        const id = `___PLACEHOLDER_${placeholders.length}___`;
        placeholders.push(html);
        return id;
    };

    const restorePlaceholders = (text) => {
        let result = text;
        for (let i = placeholders.length - 1; i >= 0; i--) {
            result = result.split(`___PLACEHOLDER_${i}___`).join(placeholders[i]);
        }
        return result;
    };

    const wrap = (text, cls) => `<span class="${cls}">${text}</span>`;

    // ========== PHP ==========
    const highlightPHP = (code) => {
        placeholders = [];
        let result = escapeHtml(code);

        // IMPORTANTE: Stringhe PRIMA dei commenti per proteggere il contenuto!
        // Double-quoted strings
        result = result.replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Single-quoted strings
        result = result.replace(/('(?:[^'\\]|\\.)*?')/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Multi-line comments
        result = result.replace(/(\/\*[\s\S]*?\*\/)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));

        // Single-line comments
        result = result.replace(/(\/\/.*$)/gm, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));
        result = result.replace(/(#[^\n]*$)/gm, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));
        
        // PHP tags
        result = result.replace(/(&lt;\?php|\?&gt;)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-tag')));
        
        // Variables
        result = result.replace(/(\$[a-zA-Z_][a-zA-Z0-9_]*)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-var')));
        
        // Numbers
        result = result.replace(/\b(0x[0-9a-fA-F]+|\d+\.?\d*)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-num')));
        
        // Function calls (word followed by parenthesis)
        result = result.replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g, (m, fn) => 
            savePlaceholder(wrap(fn, 'tok-func')) + '(');
        
        // Keywords
        const phpKeywords = 'abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield|true|false|null|void|int|float|bool|string|mixed|never|self|parent';
        result = result.replace(new RegExp(`\\b(${phpKeywords})\\b`, 'g'), (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        // Class names (PascalCase after specific keywords)
        result = result.replace(/\b([A-Z][a-zA-Z0-9_]*)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-class')));
        
        // Arrow operator and double arrow
        result = result.replace(/(-&gt;|=&gt;)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-operator')));
        
        return restorePlaceholders(result);
    };

    // ========== JavaScript ==========
    const highlightJS = (code) => {
        placeholders = [];
        let result = escapeHtml(code);

        // IMPORTANTE: Stringhe PRIMA dei commenti!
        // Template literals (backticks)
        result = result.replace(/(`(?:[^`\\]|\\.)*?`)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Double-quoted strings
        result = result.replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Single-quoted strings
        result = result.replace(/('(?:[^'\\]|\\.)*?')/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Multi-line comments
        result = result.replace(/(\/\*[\s\S]*?\*\/)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));

        // Single-line comments
        result = result.replace(/(\/\/.*$)/gm, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));
        
        // Numbers
        result = result.replace(/\b(0x[0-9a-fA-F]+|0b[01]+|\d+\.?\d*(?:[eE][+-]?\d+)?)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-num')));
        
        // Known globals
        result = result.replace(/\b(document|window|console|Math|JSON|Object|Array|String|Number|Boolean|Promise|Set|Map|this)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-var')));
        
        // Function calls
        result = result.replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g, (m, fn) => 
            savePlaceholder(wrap(fn, 'tok-func')) + '(');
        
        // Keywords
        const jsKeywords = 'async|await|break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|false|finally|for|function|if|import|in|instanceof|let|new|null|of|return|static|super|switch|throw|true|try|typeof|undefined|var|void|while|with|yield|from';
        result = result.replace(new RegExp(`\\b(${jsKeywords})\\b`, 'g'), (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        // Class names (PascalCase)
        result = result.replace(/\b([A-Z][a-zA-Z0-9_]*)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-class')));
        
        // Arrow functions
        result = result.replace(/(=&gt;)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-operator')));
        
        return restorePlaceholders(result);
    };

    // ========== CSS ==========
    const highlightCSS = (code) => {
        placeholders = [];
        let result = escapeHtml(code);

        // IMPORTANTE: Stringhe PRIMA dei commenti!
        // Strings
        result = result.replace(/(&quot;[^&]*?&quot;)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));
        result = result.replace(/('(?:[^'\\]|\\.)*?')/g, (m) =>
            savePlaceholder(wrap(m, 'tok-string')));

        // Comments
        result = result.replace(/(\/\*[\s\S]*?\*\/)/g, (m) =>
            savePlaceholder(wrap(m, 'tok-comment')));
        
        // URLs
        result = result.replace(/(url\([^)]*\))/gi, (m) => 
            savePlaceholder(wrap(m, 'tok-string')));
        
        // Hex colors
        result = result.replace(/(#[0-9a-fA-F]{3,8})\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-num')));
        
        // Numbers with units
        result = result.replace(/\b(-?\d+\.?\d*)(px|em|rem|%|vh|vw|vmin|vmax|deg|s|ms|fr)?\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-num')));
        
        // At-rules
        result = result.replace(/(@[a-zA-Z-]+)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        // Pseudo-classes/elements
        result = result.replace(/(:{1,2}[a-zA-Z-]+)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        // Class selectors
        result = result.replace(/(\.[a-zA-Z_][a-zA-Z0-9_-]*)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-class')));
        
        // ID selectors
        result = result.replace(/(#[a-zA-Z_][a-zA-Z0-9_-]*)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-var')));
        
        // Properties (word before colon, but not selectors)
        result = result.replace(/([a-zA-Z-]+)\s*:/g, (m, prop) => 
            savePlaceholder(wrap(prop, 'tok-attr')) + ':');
        
        // Tag selectors
        result = result.replace(/\b(html|body|div|span|p|a|ul|ol|li|h[1-6]|header|footer|nav|main|section|article|aside|form|input|button|table|tr|td|th|img|video|audio|canvas|svg)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-tag')));
        
        return restorePlaceholders(result);
    };

    // ========== HTML ==========
    const highlightHTML = (code) => {
        placeholders = [];
        let result = escapeHtml(code);
        
        // Comments: &lt;!-- ... --&gt;
        result = result.replace(/(&lt;!--[\s\S]*?--&gt;)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-comment')));
        
        // DOCTYPE
        result = result.replace(/(&lt;!DOCTYPE[^&]*&gt;)/gi, (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        // Tags with attributes
        result = result.replace(/(&lt;\/?)([\w-]+)((?:[^&]|&(?!gt;))*?)(\/?&gt;)/g, (match, open, tag, attrs, close) => {
            let attrHtml = attrs;
            
            // Process attributes within the tag
            if (attrs) {
                // Attribute with double quotes
                attrHtml = attrHtml.replace(/([\w-:]+)\s*=\s*(&quot;(?:[^&]|&(?!quot;))*?&quot;)/g, (m, name, val) => 
                    savePlaceholder(wrap(name, 'tok-attr')) + '=' + savePlaceholder(wrap(val, 'tok-string')));
                
                // Attribute with single quotes
                attrHtml = attrHtml.replace(/([\w-:]+)\s*=\s*('(?:[^'\\]|\\.)*?')/g, (m, name, val) => 
                    savePlaceholder(wrap(name, 'tok-attr')) + '=' + savePlaceholder(wrap(val, 'tok-string')));
                
                // Boolean attributes
                attrHtml = attrHtml.replace(/\s([\w-]+)(?=\s|$)/g, (m, name) => 
                    ' ' + savePlaceholder(wrap(name, 'tok-attr')));
            }
            
            return savePlaceholder(wrap(open, 'tok-punctuation')) + 
                   savePlaceholder(wrap(tag, 'tok-tag')) + 
                   attrHtml + 
                   savePlaceholder(wrap(close, 'tok-punctuation'));
        });
        
        return restorePlaceholders(result);
    };

    // ========== JSON ==========
    const highlightJSON = (code) => {
        placeholders = [];
        let result = escapeHtml(code);
        
        // Property names (keys) - strings followed by colon
        result = result.replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)\s*:/g, (m, key) => 
            savePlaceholder(wrap(key, 'tok-attr')) + ':');
        
        // String values (remaining strings)
        result = result.replace(/(&quot;(?:[^&]|&(?!quot;))*?&quot;)/g, (m) => 
            savePlaceholder(wrap(m, 'tok-string')));
        
        // Numbers
        result = result.replace(/:\s*(-?\d+\.?\d*(?:[eE][+-]?\d+)?)/g, (m, num) => 
            ': ' + savePlaceholder(wrap(num, 'tok-num')));
        result = result.replace(/\[\s*(-?\d+\.?\d*)/g, (m, num) => 
            '[ ' + savePlaceholder(wrap(num, 'tok-num')));
        result = result.replace(/,\s*(-?\d+\.?\d*)\b/g, (m, num) => 
            ', ' + savePlaceholder(wrap(num, 'tok-num')));
        
        // Booleans and null
        result = result.replace(/\b(true|false|null)\b/g, (m) => 
            savePlaceholder(wrap(m, 'tok-keyword')));
        
        return restorePlaceholders(result);
    };

    // ========== Process blocks ==========
    const getHighlighter = (lang) => {
        const map = {
            'php': highlightPHP,
            'js': highlightJS,
            'javascript': highlightJS,
            'css': highlightCSS,
            'html': highlightHTML,
            'htm': highlightHTML,
            'json': highlightJSON
        };
        return map[lang.toLowerCase()];
    };

    const detectLanguage = (el) => {
        // Supporta sia "language-php" che "lang-php"
        const match = el.className.match(/(?:language|lang)-(\w+)/);
        return match ? match[1] : null;
    };

    const processBlock = (el) => {
        // Skip if already processed AND contains span tags (properly highlighted)
        if (el.dataset.highlighted === 'true') {
            const hasSpanTags = el.innerHTML && el.innerHTML.includes('<span');
            if (hasSpanTags) return;
            el.dataset.highlighted = 'false'; // Reset per forzare reprocessing
        }

        const lang = detectLanguage(el);
        if (!lang) return;

        const highlighter = getHighlighter(lang);
        if (!highlighter) return;

        // Get raw text - handle both <pre><code> and <pre> directly
        let codeEl = el.tagName.toLowerCase() === 'code' ? el : el.querySelector('code');
        if (!codeEl) codeEl = el;

        const rawCode = codeEl.textContent || '';
        const highlighted = highlighter(rawCode);

        codeEl.innerHTML = highlighted;

        // Monitora se qualcuno modifica l'innerHTML dopo di noi e RE-APPLICA il highlighting
        let isReapplying = false;
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    const stillHasSpan = codeEl.innerHTML.includes('<span');
                    if (!stillHasSpan && !isReapplying) {
                        isReapplying = true;
                        codeEl.innerHTML = highlighted;
                        isReapplying = false;
                    }
                }
            });
        });
        observer.observe(codeEl, { childList: true, characterData: true, subtree: true });

        codeEl.classList.add('code-colored');
        el.dataset.highlighted = 'true';
    };

    const highlightAll = () => {
        // Supporta sia "language-*" che "lang-*"
        const elements = document.querySelectorAll('pre[class*="language-"], code[class*="language-"], pre[class*="lang-"], code[class*="lang-"]');
        elements.forEach(processBlock);
    };

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', highlightAll);
    } else {
        highlightAll();
    }

    // Expose API
    window.SyntaxHighlighter = {
        highlightAll,
        highlightElement: processBlock,
        highlightPHP,
        highlightJS,
        highlightCSS,
        highlightHTML,
        highlightJSON
    };

})();