/**
 * ExpressionParser - Parser per mini linguaggio di programmazione
 * Vanilla JavaScript per frontend
 * 
 * Supporta:
 * - Operazioni matematiche: +, -, *, /, %, ^ (potenza)
 * - Operatori di confronto: ==, <>, !=, <, >, <=, >=
 * - Operatori logici: AND, OR, NOT (o &&, ||, !)
 * - Parentesi: ()
 * - Variabili e assegnazioni: a = 5
 * - Parametri da form: [birth_date] prende valore da input name="data[birth_date]"
 * - IF statements: IF condizione THEN ... ELSE ... ENDIF
 * - Funzioni: NOW(), AGE(date), ROUND(n, decimals), ABS(n), IFNULL(val, default),
 *             UPPER(str), LOWER(str), CONCAT(str1, str2, ...), TRIM(str),
 *             ISEMPTY(val), PRECISION(n, decimals), DATEONLY(datetime),
 *             TIMEADD(time, minutes), ADDMINUTES(time, minutes)
 */

class ExpressionParser {
    
    // ==================== TIPI TOKEN ====================
    static TokenType = {
        NUMBER: 'NUMBER',
        STRING: 'STRING',
        DATE: 'DATE',
        IDENTIFIER: 'IDENTIFIER',
        PARAMETER: 'PARAMETER',
        PLUS: 'PLUS',
        MINUS: 'MINUS',
        MULTIPLY: 'MULTIPLY',
        DIVIDE: 'DIVIDE',
        MODULO: 'MODULO',
        POWER: 'POWER',
        LPAREN: 'LPAREN',
        RPAREN: 'RPAREN',
        COMMA: 'COMMA',
        ASSIGN: 'ASSIGN',
        EQ: 'EQ',
        NEQ: 'NEQ',
        LT: 'LT',
        GT: 'GT',
        LTE: 'LTE',
        GTE: 'GTE',
        AND: 'AND',
        OR: 'OR',
        NOT: 'NOT',
        IF: 'IF',
        THEN: 'THEN',
        ELSE: 'ELSE',
        ENDIF: 'ENDIF',
        NEWLINE: 'NEWLINE',
        EOF: 'EOF'
    };

    // ==================== TIPI NODO AST ====================
    static NodeType = {
        NUMBER: 'NUMBER',
        STRING: 'STRING',
        DATE: 'DATE',
        IDENTIFIER: 'IDENTIFIER',
        PARAMETER: 'PARAMETER',
        BINARY_OP: 'BINARY_OP',
        UNARY_OP: 'UNARY_OP',
        ASSIGNMENT: 'ASSIGNMENT',
        IF_STATEMENT: 'IF_STATEMENT',
        FUNCTION_CALL: 'FUNCTION_CALL',
        PROGRAM: 'PROGRAM'
    };

    constructor() {
        this.variables = {};
        this.parameters = {};
        this._nodeId = 0;
        
        // Funzioni builtin disponibili
        this._builtinFunctions = [
            'NOW', 'AGE', 'ROUND', 'ABS', 'IFNULL',
            'UPPER', 'LOWER', 'CONCAT', 'TRIM', 'ISEMPTY',
            'PRECISION', 'DATEONLY', 'TIMEADD', 'ADDMINUTES'
        ];
    }

    // ==================== HELPER FUNCTIONS ====================
    
    /**
     * Verifica se un valore è nullish (null o undefined)
     */
    _isNullish(v) {
        return v === null || v === undefined;
    }

    /**
     * Normalizza un valore per checkbox
     * @param {*} value
     * @returns {boolean}
     */
    normalizeCheckboxValue(value) {
        if (value === true) return true;
        if (value === false || value === null || value === undefined) return false;

        if (typeof value === 'number') {
            return value === 1;
        }

        if (typeof value === 'string') {
            const trimmed = value.trim().toLowerCase();
            if (trimmed === '' || trimmed === '0' || trimmed === 'false') return false;
            if (trimmed === '1' || trimmed === 'true' || trimmed === 'on' || trimmed === 'yes') return true;
            return trimmed.length > 0;
        }

        return Boolean(value);
    }

    /**
     * Converte un valore in numero
     */
    _toNumber(v) {
        if (typeof v === 'number') return v;
        if (this._isNullish(v)) throw new Error('Cannot convert null to number');
        const n = Number(v);
        if (isNaN(n)) throw new Error(`Cannot convert "${v}" to number`);
        return n;
    }

    /**
     * Converte un valore in intero
     */
    _toInt(v) {
        return Math.floor(this._toNumber(v));
    }

    /**
     * Converte un valore in stringa
     */
    _toString(v) {
        if (this._isNullish(v)) return '';
        if (v instanceof Date) {
            const year = v.getFullYear();
            const month = String(v.getMonth() + 1).padStart(2, '0');
            const day = String(v.getDate()).padStart(2, '0');
            const hours = String(v.getHours()).padStart(2, '0');
            const minutes = String(v.getMinutes()).padStart(2, '0');
            const seconds = String(v.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }
        return String(v);
    }

    /**
     * Converte un valore in Date (robusto per form HTML)
     */
    _toDate(v) {
        if (v instanceof Date) return v;
        if (this._isNullish(v)) throw new Error('Date is null');

        if (typeof v === 'number') return new Date(v);

        if (typeof v === 'string') {
            // YYYY-MM-DD
            let m = v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            if (m) return new Date(+m[1], +m[2] - 1, +m[3]);

            // YYYY-MM-DDTHH:mm(:ss)?
            m = v.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?$/);
            if (m) return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0));
        }

        throw new Error(`Invalid date: ${v}`);
    }

    /**
     * Converte un valore in orario (HH:MM o HH:MM:SS)
     */
    _toTime(value) {
        if (value instanceof Date) {
            return {
                hours: value.getHours(),
                minutes: value.getMinutes(),
                seconds: value.getSeconds(),
                hasSeconds: true
            };
        }

        if (typeof value !== 'string') {
            throw new Error('TIMEADD() richiede un orario come stringa o Date');
        }

        const trimmed = value.trim();
        const match = trimmed.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
        if (!match) {
            throw new Error('TIMEADD() richiede orario nel formato HH:MM o HH:MM:SS');
        }

        const hours = parseInt(match[1], 10);
        const minutes = parseInt(match[2], 10);
        const seconds = match[3] !== undefined ? parseInt(match[3], 10) : 0;

        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59 || seconds < 0 || seconds > 59) {
            throw new Error('TIMEADD(): orario fuori range');
        }

        return {
            hours,
            minutes,
            seconds,
            hasSeconds: match[3] !== undefined
        };
    }

    _formatTime(time) {
        const hh = String(time.hours).padStart(2, '0');
        const mm = String(time.minutes).padStart(2, '0');
        if (time.hasSeconds) {
            const ss = String(time.seconds).padStart(2, '0');
            return `${hh}:${mm}:${ss}`;
        }
        return `${hh}:${mm}`;
    }

    _addMinutesToTime(time, minutes) {
        const delta = Math.round(this._toNumber(minutes)) * 60;
        const total = time.hours * 3600 + time.minutes * 60 + time.seconds + delta;
        const day = 24 * 3600;
        const normalized = ((total % day) + day) % day;

        const hours = Math.floor(normalized / 3600);
        const minutesOut = Math.floor((normalized % 3600) / 60);
        const seconds = normalized % 60;

        return {
            hours,
            minutes: minutesOut,
            seconds,
            hasSeconds: time.hasSeconds
        };
    }

    // ==================== BUILTIN FUNCTIONS ====================

    /**
     * NOW() - Restituisce data e ora corrente
     */
    _func_NOW(args) {
        if (args.length !== 0) {
            throw new Error('NOW() non accetta argomenti');
        }
        return new Date();
    }

    /**
     * AGE(birthdate) - Calcola età in anni da data di nascita
     */
    _func_AGE(args) {
        if (args.length !== 1) {
            throw new Error('AGE() richiede esattamente 1 argomento (data di nascita)');
        }

        const birth = this._toDate(args[0]);
        const now = new Date();

        let years = now.getFullYear() - birth.getFullYear();

        // Se il compleanno non è ancora passato quest'anno, -1
        const m = now.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && now.getDate() < birth.getDate())) {
            years--;
        }

        return years;
    }

    /**
     * ROUND(number, decimals) - Arrotondamento matematico
     */
    _func_ROUND(args) {
        if (args.length < 1 || args.length > 2) {
            throw new Error('ROUND() richiede 1 o 2 argomenti (numero, decimali opzionali)');
        }

        const num = this._toNumber(args[0]);
        const decimals = args.length === 2 ? this._toInt(args[1]) : 0;

        const factor = Math.pow(10, decimals);

        // FIX FLOAT
        const result = Math.round((num + Number.EPSILON) * factor) / factor;

        // se decimals = 0 torna intero (come PHP)
        return decimals === 0 ? Math.round(result) : result;
    }

    /**
     * ABS(number) - Valore assoluto
     */
    _func_ABS(args) {
        if (args.length !== 1) {
            throw new Error('ABS() richiede esattamente 1 argomento');
        }

        return Math.abs(this._toNumber(args[0]));
    }

    /**
     * IFNULL(value, default) - Restituisce default se value è null/undefined
     */
    _func_IFNULL(args) {
        if (args.length !== 2) {
            throw new Error('IFNULL() richiede esattamente 2 argomenti (valore, default)');
        }

        return this._isNullish(args[0]) ? args[1] : args[0];
    }

    /**
     * UPPER(string) - Converte in maiuscolo
     */
    _func_UPPER(args) {
        if (args.length !== 1) {
            throw new Error('UPPER() richiede esattamente 1 argomento');
        }

        return this._toString(args[0]).toUpperCase();
    }

    /**
     * LOWER(string) - Converte in minuscolo
     */
    _func_LOWER(args) {
        if (args.length !== 1) {
            throw new Error('LOWER() richiede esattamente 1 argomento');
        }

        return this._toString(args[0]).toLowerCase();
    }

    /**
     * CONCAT(str1, str2, ...) - Concatena stringhe
     */
    _func_CONCAT(args) {
        let result = '';
        for (const arg of args) {
            if (!this._isNullish(arg)) {
                result += this._toString(arg);
            }
        }
        return result;
    }

    /**
     * TRIM(string) - Rimuove spazi iniziali e finali
     */
    _func_TRIM(args) {
        if (args.length !== 1) {
            throw new Error('TRIM() richiede esattamente 1 argomento');
        }

        return this._toString(args[0]).trim();
    }

    /**
     * ISEMPTY(value) - Verifica se vuoto o null/undefined
     */
    _func_ISEMPTY(args) {
        if (args.length !== 1) {
            throw new Error('ISEMPTY() richiede esattamente 1 argomento');
        }

        const v = args[0];
        
        // null o undefined
        if (this._isNullish(v)) return true;
        
        // Stringa vuota o solo spazi
        if (typeof v === 'string' && v.trim().length === 0) return true;
        
        return false;
    }

    /**
     * PRECISION(number, decimals) - Forza numero ad avere N decimali
     * Se decimals = 0, si comporta come Math.floor()
     */
    _func_PRECISION(args) {
        if (args.length !== 2) {
            throw new Error('PRECISION() richiede esattamente 2 argomenti (numero, decimali)');
        }

        const num = this._toNumber(args[0]);
        const decimals = this._toInt(args[1]);

        // Se decimals = 0, comportamento FLOOR
        if (decimals === 0) {
            return Math.floor(num);
        }

        // Altrimenti formatta con N decimali
        const factor = Math.pow(10, decimals);
        return Math.round((num + Number.EPSILON) * factor) / factor;
    }

    /**
     * DATEONLY(datetime) - Azzera ore, minuti, secondi
     */
    _func_DATEONLY(args) {
        if (args.length !== 1) {
            throw new Error('DATEONLY() richiede esattamente 1 argomento');
        }

        const dt = this._toDate(args[0]);
        const out = new Date(dt.getTime());
        out.setHours(0, 0, 0, 0);
        return out;
    }

    /**
     * TIMEADD(time, minutes) - Somma minuti ad un orario (HH:MM o HH:MM:SS)
     */
    _func_TIMEADD(args) {
        if (args.length !== 2) {
            throw new Error('TIMEADD() richiede esattamente 2 argomenti (orario, minuti)');
        }

        const time = this._toTime(args[0]);
        const result = this._addMinutesToTime(time, args[1]);
        return this._formatTime(result);
    }

    /**
     * ADDMINUTES(time, minutes) - Alias di TIMEADD
     */
    _func_ADDMINUTES(args) {
        return this._func_TIMEADD(args);
    }

    /**
     * Esegue una funzione builtin
     */
    _executeFunction(funcName, args) {
        const upperName = funcName.toUpperCase();
        
        if (!this._builtinFunctions.includes(upperName)) {
            throw new Error(`Funzione non riconosciuta: ${funcName}`);
        }

        const methodName = `_func_${upperName}`;
        
        if (typeof this[methodName] !== 'function') {
            throw new Error(`Funzione ${funcName} non implementata`);
        }

        return this[methodName](args);
    }

    // ==================== GESTIONE PARAMETRI DA FORM ====================
    
    /**
     * Imposta i parametri da una form HTML o da un oggetto
     * @param {HTMLFormElement|Object} source - Form HTML o oggetto {key: value}
     */
    setParametersFromForm(source) {
        this.parameters = {};
        
        if (source instanceof HTMLFormElement) {
            const formData = new FormData(source);
            for (let [key, value] of formData.entries()) {
                // Gestisce sia "name" che "data[name]"
                const match = key.match(/^data\[(.+)\]$/);
                const paramName = match ? match[1] : key;
                this.parameters[paramName] = this._parseValue(value);
            }
        } else if (typeof source === 'object' && source !== null) {
            for (let [key, value] of Object.entries(source)) {
                this.parameters[key] = this._parseValue(value);
            }
        }
        
        return this;
    }

    /**
     * Imposta un singolo parametro
     */
    setParameter(name, value) {
        this.parameters[name] = this._parseValue(value);
        return this;
    }

    _parseValue(value) {
        // numeri e boolean/Date ecc: lascia stare
        if (typeof value === 'number' || typeof value === 'boolean') return value;
        if (value instanceof Date) return value;
        if (typeof value !== 'string') return value;

        const trimmed = value.trim();

        // stringa vuota resta stringa vuota (non numero)
        if (trimmed === '') return '';

        // datetime-local: YYYY-MM-DDTHH:MM o YYYY-MM-DDTHH:MM:SS
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/.test(trimmed)) {
            // invece di new Date(trimmed) -> usa parsing deterministico
            return this._toDate(trimmed);
        }

        // date-only: YYYY-MM-DD
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            // invece di new Date(trimmed) -> usa parsing deterministico (mezzanotte locale)
            return this._toDate(trimmed);
        }

        // time: HH:MM o HH:MM:SS -> secondi dalla mezzanotte
        if (/^\d{2}:\d{2}(:\d{2})?$/.test(trimmed)) {
            const parts = trimmed.split(':');
            const hours = parseInt(parts[0], 10);
            const minutes = parseInt(parts[1], 10);
            const seconds = parts[2] ? parseInt(parts[2], 10) : 0;
            return hours * 3600 + minutes * 60 + seconds;
        }

        // numeri: mantieni il tuo comportamento (parseFloat)
        // NB: parseFloat("123abc") = 123; se vuoi più strict usa una regex, ma non lo cambio
        const num = parseFloat(trimmed);
        if (!isNaN(num)) return num;

        // fallback: stringa
        return trimmed;
    }


    // ==================== LEXER (Tokenizzatore) ====================
    
    _tokenize(input) {
        const tokens = [];
        let pos = 0;
        let line = 1;
        let column = 1;
        const TT = ExpressionParser.TokenType;

        const peek = (offset = 0) => input[pos + offset] || null;
        
        const advance = () => {
            const char = input[pos++];
            if (char === '\n') { line++; column = 1; }
            else { column++; }
            return char;
        };

        const skipWhitespace = () => {
            while (peek() && /[ \t\r]/.test(peek())) advance();
        };

        const readNumber = () => {
            let num = '';
            const startCol = column;
            const startPos = pos;
            let hasDecimalPoint = false;
            
            // Leggi tutti i caratteri che potrebbero essere parte di un numero o data
            while (peek() && /[0-9]/.test(peek())) {
                num += advance();
            }
            
            // Supporto per numeri decimali con punto (es: 1.22, 3.14)
            if (peek() === '.' && peek(1) && /[0-9]/.test(peek(1))) {
                // Salva lo stato nel caso dovessimo tornare indietro
                const savedPos = pos;
                const savedCol = column;
                const savedLine = line;
                
                num += advance(); // aggiungi il punto
                hasDecimalPoint = true;
                
                // Leggi la parte decimale
                while (peek() && /[0-9]/.test(peek())) {
                    num += advance();
                }
                
                // Se dopo il punto c'era almeno una cifra, è un numero decimale valido
                // Altrimenti torna indietro (era un punto non numerico)
                if (num.endsWith('.')) {
                    pos = savedPos;
                    column = savedCol;
                    line = savedLine;
                    num = num.slice(0, -1); // rimuovi il punto
                    hasDecimalPoint = false;
                }
            }
            
            // Controlla se è una data YYYY-MM-DD (solo se non è un numero decimale)
            if (!hasDecimalPoint && peek() === '-' && num.length === 4) {
                const savedPos = pos;
                const savedCol = column;
                const savedLine = line;
                
                let dateStr = num;
                dateStr += advance(); // -
                
                let month = '';
                while (peek() && /[0-9]/.test(peek())) {
                    month += advance();
                }
                
                if (month.length === 2 && peek() === '-') {
                    dateStr += month;
                    dateStr += advance(); // -
                    
                    let day = '';
                    while (peek() && /[0-9]/.test(peek())) {
                        day += advance();
                    }
                    
                    if (day.length === 2) {
                        dateStr += day;
                        // Valida la data
                        const date = new Date(dateStr);
                        if (!isNaN(date.getTime())) {
                            return { type: TT.DATE, value: dateStr, line, column: startCol };
                        }
                    }
                }
                
                // Non è una data valida, torna indietro
                pos = savedPos;
                column = savedCol;
                line = savedLine;
            }
            
            // Controlla se è una data DD/MM/YYYY (solo se non è un numero decimale)
            if (!hasDecimalPoint && peek() === '/' && (num.length === 1 || num.length === 2)) {
                const savedPos = pos;
                const savedCol = column;
                const savedLine = line;
                
                let day = num;
                advance(); // /
                
                let month = '';
                while (peek() && /[0-9]/.test(peek())) {
                    month += advance();
                }
                
                if ((month.length === 1 || month.length === 2) && peek() === '/') {
                    advance(); // /
                    
                    let year = '';
                    while (peek() && /[0-9]/.test(peek())) {
                        year += advance();
                    }
                    
                    if (year.length === 4) {
                        // Converti in formato ISO per creare la data
                        const isoDate = `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
                        const date = new Date(isoDate);
                        if (!isNaN(date.getTime())) {
                            return { type: TT.DATE, value: isoDate, line, column: startCol };
                        }
                    }
                }
                
                // Non è una data valida, torna indietro
                pos = savedPos;
                column = savedCol;
                line = savedLine;
            }
            
            return { type: TT.NUMBER, value: parseFloat(num), line, column: startCol };
        };

        const readString = () => {
            const quote = advance();
            let str = '';
            const startCol = column;
            while (peek() && peek() !== quote) {
                if (peek() === '\\') { advance(); str += advance(); }
                else { str += advance(); }
            }
            if (peek() === quote) advance();
            return { type: TT.STRING, value: str, line, column: startCol };
        };

        const readIdentifier = () => {
            let id = '';
            const startCol = column;
            while (peek() && /[a-zA-Z0-9_]/.test(peek())) {
                id += advance();
            }
            
            const keywords = {
                'IF': TT.IF, 'if': TT.IF,
                'THEN': TT.THEN, 'then': TT.THEN,
                'ELSE': TT.ELSE, 'else': TT.ELSE,
                'ENDIF': TT.ENDIF, 'endif': TT.ENDIF,
                'AND': TT.AND, 'and': TT.AND,
                'OR': TT.OR, 'or': TT.OR,
                'NOT': TT.NOT, 'not': TT.NOT
            };
            
            return { type: keywords[id] || TT.IDENTIFIER, value: id, line, column: startCol };
        };

        const readParameter = () => {
            advance(); // [
            let param = '';
            const startCol = column;
            while (peek() && peek() !== ']') {
                param += advance();
            }
            if (peek() === ']') advance();
            return { type: TT.PARAMETER, value: param.trim(), line, column: startCol };
        };

        while (pos < input.length) {
            skipWhitespace();
            if (pos >= input.length) break;

            const char = peek();
            const startCol = column;

            if (/[0-9]/.test(char)) {
                tokens.push(readNumber());
            }
            else if (char === '"' || char === "'") {
                tokens.push(readString());
            }
            else if (char === '[') {
                tokens.push(readParameter());
            }
            else if (/[a-zA-Z_]/.test(char)) {
                tokens.push(readIdentifier());
            }
            else if (char === ',') { advance(); tokens.push({ type: TT.COMMA, value: ',', line, column: startCol }); }
            else if (char === '+') { advance(); tokens.push({ type: TT.PLUS, value: '+', line, column: startCol }); }
            else if (char === '-') { advance(); tokens.push({ type: TT.MINUS, value: '-', line, column: startCol }); }
            else if (char === '*') { advance(); tokens.push({ type: TT.MULTIPLY, value: '*', line, column: startCol }); }
            else if (char === '/') { advance(); tokens.push({ type: TT.DIVIDE, value: '/', line, column: startCol }); }
            else if (char === '%') { advance(); tokens.push({ type: TT.MODULO, value: '%', line, column: startCol }); }
            else if (char === '^') { advance(); tokens.push({ type: TT.POWER, value: '^', line, column: startCol }); }
            else if (char === '(') { advance(); tokens.push({ type: TT.LPAREN, value: '(', line, column: startCol }); }
            else if (char === ')') { advance(); tokens.push({ type: TT.RPAREN, value: ')', line, column: startCol }); }
            else if (char === '=') {
                advance();
                if (peek() === '=') { advance(); tokens.push({ type: TT.EQ, value: '==', line, column: startCol }); }
                else { tokens.push({ type: TT.ASSIGN, value: '=', line, column: startCol }); }
            }
            else if (char === '<') {
                advance();
                if (peek() === '=') { advance(); tokens.push({ type: TT.LTE, value: '<=', line, column: startCol }); }
                else if (peek() === '>') { advance(); tokens.push({ type: TT.NEQ, value: '<>', line, column: startCol }); }
                else { tokens.push({ type: TT.LT, value: '<', line, column: startCol }); }
            }
            else if (char === '>') {
                advance();
                if (peek() === '=') { advance(); tokens.push({ type: TT.GTE, value: '>=', line, column: startCol }); }
                else { tokens.push({ type: TT.GT, value: '>', line, column: startCol }); }
            }
            else if (char === '!') {
                advance();
                if (peek() === '=') { advance(); tokens.push({ type: TT.NEQ, value: '!=', line, column: startCol }); }
                else { tokens.push({ type: TT.NOT, value: '!', line, column: startCol }); }
            }
            else if (char === '&' && peek(1) === '&') {
                advance(); advance();
                tokens.push({ type: TT.AND, value: '&&', line, column: startCol });
            }
            else if (char === '|' && peek(1) === '|') {
                advance(); advance();
                tokens.push({ type: TT.OR, value: '||', line, column: startCol });
            }
            else if (char === '\n') {
                advance();
                tokens.push({ type: TT.NEWLINE, value: '\\n', line, column: startCol });
            }
            else {
                const bad = advance();
                const code = bad ? bad.charCodeAt(0) : 0;
                throw new Error(
                    `Carattere non riconosciuto "${bad}" (ASCII ${code}) alla riga ${line}, colonna ${startCol}`
                );
            }
        }

        tokens.push({ type: TT.EOF, value: null, line, column });
        return tokens;
    }

    // ==================== PARSER (Costruisce AST) ====================

    _createNode(type, value = null) {
        return {
            type,
            value,
            left: null,
            right: null,
            condition: null,
            thenBranch: null,
            elseBranch: null,
            arguments: null,
            id: ++this._nodeId
        };
    }

    _parse(tokens) {
        const TT = ExpressionParser.TokenType;
        const NT = ExpressionParser.NodeType;
        let pos = 0;

        const peek = () => tokens[pos] || { type: TT.EOF };
        const advance = () => tokens[pos++];
        const expect = (type) => {
            const token = peek();
            if (token.type === type) return advance();
            throw new Error(`Atteso ${type} ma trovato ${token.type} alla riga ${token.line}`);
        };
        const skipNewlines = () => {
            while (peek().type === TT.NEWLINE) advance();
        };

        // Precedenza operatori (dal basso all'alto):
        // OR -> AND -> Confronto -> Add/Sub -> Mul/Div -> Power -> Unary -> Primary

        const parseExpression = () => parseOr();

        const parseOr = () => {
            let left = parseAnd();
            while (peek().type === TT.OR) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parseAnd();
                left = node;
            }
            return left;
        };

        const parseAnd = () => {
            let left = parseComparison();
            while (peek().type === TT.AND) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parseComparison();
                left = node;
            }
            return left;
        };

        const parseComparison = () => {
            let left = parseAdditive();
            const compOps = [TT.EQ, TT.NEQ, TT.LT, TT.GT, TT.LTE, TT.GTE];
            while (compOps.includes(peek().type)) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parseAdditive();
                left = node;
            }
            return left;
        };

        const parseAdditive = () => {
            let left = parseMultiplicative();
            while (peek().type === TT.PLUS || peek().type === TT.MINUS) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parseMultiplicative();
                left = node;
            }
            return left;
        };

        const parseMultiplicative = () => {
            let left = parsePower();
            while ([TT.MULTIPLY, TT.DIVIDE, TT.MODULO].includes(peek().type)) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parsePower();
                left = node;
            }
            return left;
        };

        const parsePower = () => {
            let left = parseUnary();
            if (peek().type === TT.POWER) {
                const op = advance();
                const node = this._createNode(NT.BINARY_OP, op.value);
                node.left = left;
                node.right = parsePower(); // Associatività a destra
                return node;
            }
            return left;
        };

        const parseUnary = () => {
            if (peek().type === TT.MINUS || peek().type === TT.NOT) {
                const op = advance();
                const node = this._createNode(NT.UNARY_OP, op.value);
                node.right = parseUnary();
                return node;
            }
            return parsePrimary();
        };

        const parsePrimary = () => {
            const token = peek();
            
            if (token.type === TT.NUMBER) {
                advance();
                return this._createNode(NT.NUMBER, token.value);
            }
            if (token.type === TT.DATE) {
                advance();
                return this._createNode(NT.DATE, token.value);
            }
            if (token.type === TT.STRING) {
                advance();
                return this._createNode(NT.STRING, token.value);
            }
            if (token.type === TT.IDENTIFIER) {
                const idToken = advance();
                
                // Controlla se è una funzione (IDENTIFIER seguito da LPAREN)
                if (peek().type === TT.LPAREN) {
                    advance(); // (
                    
                    // Leggi argomenti
                    const args = [];
                    if (peek().type !== TT.RPAREN) {
                        args.push(parseExpression());
                        while (peek().type === TT.COMMA) {
                            advance(); // ,
                            args.push(parseExpression());
                        }
                    }
                    
                    expect(TT.RPAREN);
                    
                    const node = this._createNode(NT.FUNCTION_CALL, idToken.value.toUpperCase());
                    node.arguments = args;
                    return node;
                }
                
                return this._createNode(NT.IDENTIFIER, idToken.value);
            }
            if (token.type === TT.PARAMETER) {
                advance();
                return this._createNode(NT.PARAMETER, token.value);
            }
            if (token.type === TT.LPAREN) {
                advance();
                const expr = parseExpression();
                expect(TT.RPAREN);
                return expr;
            }
            
            throw new Error(`Token inatteso: ${token.type} alla riga ${token.line}`);
        };

        const parseStatement = () => {
            const token = peek();
            
            if (token.type === TT.IF) {
                return parseIfStatement();
            }
            
            if (token.type === TT.IDENTIFIER && tokens[pos + 1]?.type === TT.ASSIGN) {
                return parseAssignment();
            }
            
            return parseExpression();
        };

        const parseAssignment = () => {
            const id = advance();
            expect(TT.ASSIGN);
            const node = this._createNode(NT.ASSIGNMENT, id.value);
            node.right = parseExpression();
            return node;
        };

        const parseIfStatement = () => {
            expect(TT.IF);
            const node = this._createNode(NT.IF_STATEMENT);
            node.condition = parseExpression();
            
            if (peek().type === TT.THEN) advance();
            skipNewlines();
            
            // THEN branch
            const thenStatements = [];
            while (![TT.ELSE, TT.ENDIF, TT.EOF].includes(peek().type)) {
                skipNewlines();
                if ([TT.ELSE, TT.ENDIF, TT.EOF].includes(peek().type)) break;
                thenStatements.push(parseStatement());
                skipNewlines();
            }
            node.thenBranch = thenStatements;
            
            // ELSE branch (opzionale)
            if (peek().type === TT.ELSE) {
                advance();
                skipNewlines();
                const elseStatements = [];
                while (![TT.ENDIF, TT.EOF].includes(peek().type)) {
                    skipNewlines();
                    if ([TT.ENDIF, TT.EOF].includes(peek().type)) break;
                    elseStatements.push(parseStatement());
                    skipNewlines();
                }
                node.elseBranch = elseStatements;
            }
            
            if (peek().type === TT.ENDIF) advance();
            return node;
        };

        // Parse programma
        const statements = [];
        while (peek().type !== TT.EOF) {
            skipNewlines();
            if (peek().type === TT.EOF) break;
            statements.push(parseStatement());
        }

        const program = this._createNode(NT.PROGRAM);
        program.statements = statements;
        return program;
    }

    // ==================== METODI PUBBLICI ====================

    /**
     * Parsa il codice sorgente e restituisce l'AST
     * @param {string} source - Codice sorgente
     * @returns {Object} AST
     */
    parse(source) {
        this._nodeId = 0;
        const tokens = this._tokenize(source);
        return this._parse(tokens);
    }

    /**
     * Restituisce l'elenco delle operazioni nell'ordine di esecuzione
     * @param {Object} ast - Albero AST (o stringa da parsare)
     * @returns {Array} Lista operazioni in ordine
     */
    getOperationOrder(ast) {
        if (typeof ast === 'string') {
            ast = this.parse(ast);
        }
        
        const NT = ExpressionParser.NodeType;
        const operations = [];
        let order = 1;

        const nodeToString = (node) => {
            if (!node) return '';
            switch (node.type) {
                case NT.NUMBER: return String(node.value);
                case NT.DATE: return node.value;
                case NT.STRING: return `"${node.value}"`;
                case NT.IDENTIFIER: return node.value;
                case NT.PARAMETER: return `[${node.value}]`;
                case NT.FUNCTION_CALL:
                    const argStrs = (node.arguments || []).map(nodeToString);
                    return `${node.value}(${argStrs.join(', ')})`;
                case NT.BINARY_OP: return `(${nodeToString(node.left)} ${node.value} ${nodeToString(node.right)})`;
                case NT.UNARY_OP: return `${node.value}(${nodeToString(node.right)})`;
                default: return '?';
            }
        };

        const traverse = (node, depth = 0) => {
            if (!node) return;

            if (node.type === NT.PROGRAM) {
                (node.statements || []).forEach(stmt => traverse(stmt, depth));
                return;
            }

            if (node.type === NT.FUNCTION_CALL) {
                (node.arguments || []).forEach(arg => traverse(arg, depth + 1));
                operations.push({
                    order: order++,
                    operation: 'FUNCTION',
                    nodeId: node.id,
                    depth,
                    function: node.value,
                    description: nodeToString(node)
                });
            }
            else if (node.type === NT.BINARY_OP) {
                traverse(node.left, depth + 1);
                traverse(node.right, depth + 1);
                operations.push({
                    order: order++,
                    operation: node.value,
                    nodeId: node.id,
                    depth,
                    left: nodeToString(node.left),
                    right: nodeToString(node.right),
                    description: `${nodeToString(node.left)} ${node.value} ${nodeToString(node.right)}`
                });
            }
            else if (node.type === NT.UNARY_OP) {
                traverse(node.right, depth + 1);
                operations.push({
                    order: order++,
                    operation: node.value,
                    nodeId: node.id,
                    depth,
                    operand: nodeToString(node.right),
                    description: `${node.value}(${nodeToString(node.right)})`
                });
            }
            else if (node.type === NT.ASSIGNMENT) {
                traverse(node.right, depth + 1);
                operations.push({
                    order: order++,
                    operation: '=',
                    nodeId: node.id,
                    depth,
                    variable: node.value,
                    value: nodeToString(node.right),
                    description: `${node.value} = ${nodeToString(node.right)}`
                });
            }
            else if (node.type === NT.IF_STATEMENT) {
                operations.push({
                    order: order++,
                    operation: 'IF',
                    nodeId: node.id,
                    depth,
                    description: `IF (valuta condizione)`
                });
                traverse(node.condition, depth + 1);
                (node.thenBranch || []).forEach(stmt => traverse(stmt, depth + 1));
                (node.elseBranch || []).forEach(stmt => traverse(stmt, depth + 1));
            }
        };

        traverse(ast);
        return operations;
    }

    /**
     * Visualizza l'albero AST come stringa formattata
     * @param {Object} ast - Albero AST (o stringa da parsare)
     * @returns {string} Rappresentazione testuale dell'albero
     */
    visualizeTree(ast, indent = '', isLast = true) {
        if (typeof ast === 'string') {
            ast = this.parse(ast);
        }
        
        const NT = ExpressionParser.NodeType;
        let result = '';
        const connector = isLast ? '└── ' : '├── ';
        const childIndent = indent + (isLast ? '    ' : '│   ');

        if (ast.type === NT.PROGRAM) {
            result += 'PROGRAM\n';
            const stmts = ast.statements || [];
            stmts.forEach((stmt, i) => {
                result += this.visualizeTree(stmt, '', i === stmts.length - 1);
            });
            return result;
        }

        result += indent + connector;

        switch (ast.type) {
            case NT.NUMBER:
                result += `NUM(${ast.value})\n`;
                break;
            case NT.DATE:
                result += `DATE(${ast.value})\n`;
                break;
            case NT.STRING:
                result += `STR("${ast.value}")\n`;
                break;
            case NT.IDENTIFIER:
                result += `VAR(${ast.value})\n`;
                break;
            case NT.PARAMETER:
                result += `PARAM[${ast.value}]\n`;
                break;
            case NT.FUNCTION_CALL:
                result += `FUNC(${ast.value}) [id:${ast.id}]\n`;
                (ast.arguments || []).forEach((arg, i, arr) => {
                    result += this.visualizeTree(arg, childIndent, i === arr.length - 1);
                });
                break;
            case NT.BINARY_OP:
                result += `OP(${ast.value}) [id:${ast.id}]\n`;
                if (ast.left) result += this.visualizeTree(ast.left, childIndent, !ast.right);
                if (ast.right) result += this.visualizeTree(ast.right, childIndent, true);
                break;
            case NT.UNARY_OP:
                result += `UNARY(${ast.value}) [id:${ast.id}]\n`;
                if (ast.right) result += this.visualizeTree(ast.right, childIndent, true);
                break;
            case NT.ASSIGNMENT:
                result += `ASSIGN(${ast.value}) [id:${ast.id}]\n`;
                if (ast.right) result += this.visualizeTree(ast.right, childIndent, true);
                break;
            case NT.IF_STATEMENT:
                result += `IF [id:${ast.id}]\n`;
                result += childIndent + '├── CONDITION:\n';
                if (ast.condition) result += this.visualizeTree(ast.condition, childIndent + '│   ', true);
                result += childIndent + '├── THEN:\n';
                (ast.thenBranch || []).forEach((stmt, i, arr) => {
                    result += this.visualizeTree(stmt, childIndent + '│   ', i === arr.length - 1);
                });
                if (ast.elseBranch?.length) {
                    result += childIndent + '└── ELSE:\n';
                    ast.elseBranch.forEach((stmt, i, arr) => {
                        result += this.visualizeTree(stmt, childIndent + '    ', i === arr.length - 1);
                    });
                }
                break;
            default:
                result += `UNKNOWN(${ast.type})\n`;
        }

        return result;
    }

    /**
     * Esegue l'AST e restituisce il risultato
     * @param {Object} ast - Albero AST (o stringa da parsare)
     * @returns {*} Risultato dell'esecuzione
     */
    execute(ast) {
        if (typeof ast === 'string') {
            ast = this.parse(ast);
        }
        
        const NT = ExpressionParser.NodeType;

        const exec = (node) => {
            if (!node) return null;

            switch (node.type) {
                case NT.PROGRAM: {
                    let result;
                    (node.statements || []).forEach(stmt => result = exec(stmt));
                    return result;
                }
                case NT.NUMBER:
                case NT.STRING:
                    return node.value;
                
                case NT.DATE:
                    return new Date(node.value);

                case NT.IDENTIFIER:
                    if (!(node.value in this.variables)) {
                        throw new Error(`Variabile non definita: ${node.value}`);
                    }
                    return this.variables[node.value];

                case NT.PARAMETER:
                    if (!(node.value in this.parameters)) {
                        throw new Error(`Parametro non definito: [${node.value}]`);
                    }
                    return this.parameters[node.value];

                case NT.FUNCTION_CALL: {
                    // Valuta gli argomenti
                    const args = (node.arguments || []).map(argNode => exec(argNode));
                    // Esegui la funzione
                    return this._executeFunction(node.value, args);
                }

                case NT.BINARY_OP: {
                    const left = exec(node.left);
                    const right = exec(node.right);
                    
                    const isDate = (v) => v instanceof Date;
                    const isNumber = (v) => typeof v === 'number';
                    const MS_PER_DAY = 24 * 60 * 60 * 1000;
                    
                    switch (node.value) {
                        case '+': 
                            // Data + Numero (giorni) = Data
                            if (isDate(left) && isNumber(right)) {
                                const result = new Date(left.getTime());
                                result.setDate(result.getDate() + right);
                                return result;
                            }
                            // Numero + Data (giorni) = Data
                            if (isNumber(left) && isDate(right)) {
                                const result = new Date(right.getTime());
                                result.setDate(result.getDate() + left);
                                return result;
                            }
                            // Data + Data = Errore
                            if (isDate(left) && isDate(right)) {
                                throw new Error('Non è possibile sommare due date');
                            }
                            return left + right;
                            
                        case '-': 
                            // Data - Data = Numero (differenza in giorni)
                            if (isDate(left) && isDate(right)) {
                                return Math.floor((left.getTime() - right.getTime()) / MS_PER_DAY);
                            }
                            // Data - Numero (giorni) = Data
                            if (isDate(left) && isNumber(right)) {
                                const result = new Date(left.getTime());
                                result.setDate(result.getDate() - right);
                                return result;
                            }
                            // Numero - Data = Errore
                            if (isNumber(left) && isDate(right)) {
                                throw new Error('Non è possibile sottrarre una data da un numero');
                            }
                            return left - right;
                            
                        case '*': 
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile moltiplicare date');
                            }
                            return left * right;
                            
                        case '/': 
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile dividere date');
                            }
                            return left / right;
                            
                        case '%': 
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile usare modulo con date');
                            }
                            return left % right;
                            
                        case '^': 
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile usare potenza con date');
                            }
                            return Math.pow(left, right);
                            
                        case '==': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() === right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando ==');
                            }
                            return left === right;
                            
                        case '!=':
                        case '<>': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() !== right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando <>');
                            }
                            return left !== right;
                            
                        case '<': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() < right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando <');
                            }
                            return left < right;
                            
                        case '>': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() > right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando >');
                            }
                            return left > right;
                            
                        case '<=': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() <= right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando <=');
                            }
                            return left <= right;
                            
                        case '>=': 
                            if (isDate(left) && isDate(right)) {
                                return left.getTime() >= right.getTime();
                            }
                            if (isDate(left) || isDate(right)) {
                                throw new Error('Non è possibile confrontare una data con un non-data usando >=');
                            }
                            return left >= right;
                            
                        case '&&':
                        case 'and':
                        case 'AND': 
                            if (typeof left !== 'boolean') {
                                throw new Error(`Operatore AND: il valore sinistro deve essere booleano, ricevuto ${typeof left} (${left})`);
                            }
                            if (typeof right !== 'boolean') {
                                throw new Error(`Operatore AND: il valore destro deve essere booleano, ricevuto ${typeof right} (${right})`);
                            }
                            return left && right;
                        case '||':
                        case 'or':
                        case 'OR':
                            if (typeof left !== 'boolean') {
                                throw new Error(`Operatore OR: il valore sinistro deve essere booleano, ricevuto ${typeof left} (${left})`);
                            }
                            if (typeof right !== 'boolean') {
                                throw new Error(`Operatore OR: il valore destro deve essere booleano, ricevuto ${typeof right} (${right})`);
                            }
                            return left || right;
                        default: throw new Error(`Operatore non supportato: ${node.value}`);
                    }
                }

                case NT.UNARY_OP: {
                    const operand = exec(node.right);
                    switch (node.value) {
                        case '-': return -operand;
                        case '!':
                        case 'not':
                        case 'NOT': 
                            if (typeof operand !== 'boolean') {
                                throw new Error(`Operatore NOT: il valore deve essere booleano, ricevuto ${typeof operand} (${operand})`);
                            }
                            return !operand;
                        default: throw new Error(`Operatore unario non supportato: ${node.value}`);
                    }
                }

                case NT.ASSIGNMENT: {
                    const value = exec(node.right);
                    this.variables[node.value] = value;
                    return value;
                }

                case NT.IF_STATEMENT: {
                    const condition = exec(node.condition);

                    if (typeof condition !== 'boolean') {
                        throw new Error(
                        `IF: la condizione deve essere booleano, ricevuto ${typeof condition} (${this.formatResult(condition)})`
                        );
                    }

                    if (condition === true) {
                        let result;
                        (node.thenBranch || []).forEach(stmt => result = exec(stmt));
                        return result;
                    } else if (node.elseBranch?.length) {
                        let result;
                        node.elseBranch.forEach(stmt => result = exec(stmt));
                        return result;
                    }
                    return null;
                }


                default:
                    throw new Error(`Tipo nodo non supportato: ${node.type}`);
            }
        };

        return exec(ast);
    }

    /**
     * Metodo completo: parsa, analizza e opzionalmente esegue
     * @param {string} source - Codice sorgente
     * @param {boolean} executeCode - Se true, esegue il codice
     * @returns {Object} { ast, operations, tree, result?, error? }
     */
    analyze(source, executeCode = true) {
        const ast = this.parse(source);
        const operations = this.getOperationOrder(ast);
        const tree = this.visualizeTree(ast);
        
        const result = {
            ast,
            operations,
            tree,
            source
        };

        if (executeCode) {
            try {
                result.result = this.execute(ast);
            } catch (e) {
                result.error = e.message;
            }
        }

        return result;
    }

    /**
     * Reset delle variabili
     */
    reset() {
        this.variables = {};
        return this;
    }

    /**
     * Reset completo (variabili e parametri)
     */
    resetAll() {
        this.variables = {};
        this.parameters = {};
        return this;
    }

    /**
     * Formatta il risultato per output leggibile
     * @param {*} value - Valore da formattare
     * @returns {string} Valore formattato
     */
    formatResult(value) {
        if (value instanceof Date) {
            const year = value.getFullYear();
            const month = String(value.getMonth() + 1).padStart(2, '0');
            const day = String(value.getDate()).padStart(2, '0');
            const hours = String(value.getHours()).padStart(2, '0');
            const minutes = String(value.getMinutes()).padStart(2, '0');
            const seconds = String(value.getSeconds()).padStart(2, '0');
            return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }
        if (typeof value === 'boolean') {
            return value ? 'true' : 'false';
        }
        if (value === null || value === undefined) {
            return 'null';
        }
        return String(value);
    }

    /**
     * Restituisce la lista delle funzioni builtin disponibili
     * @returns {Array}
     */
    getBuiltinFunctions() {
        return this._builtinFunctions;
    }
}
