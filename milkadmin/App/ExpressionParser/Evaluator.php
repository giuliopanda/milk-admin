<?php
namespace App\ExpressionParser;

/**
 * Evaluator - Motore di esecuzione dell'AST
 */
class Evaluator
{
    use ValueHelper;

    private array $variables = [];
    private array $parameters = [];
    private BuiltinFunctions $functions;
    private int $maxExecutionSteps = 0;
    private int $executionSteps = 0;
    private int $maxParameterPathSegments = 0;
    private bool $allowObjectArrayAccess = true;
    private bool $allowObjectGetterMethods = true;
    private bool $allowObjectMagicAccess = true;

    public function __construct(?BuiltinFunctions $functions = null)
    {
        $this->functions = $functions ?? new BuiltinFunctions();
    }

    /**
     * Accesso diretto alle BuiltinFunctions (per estensione)
     */
    public function getBuiltinFunctions(): BuiltinFunctions
    {
        return $this->functions;
    }

    /**
     * Configura policy di sicurezza runtime per l'evaluatore.
     *
     * Opzioni supportate:
     * - max_execution_steps: int (0 = nessun limite)
     * - max_parameter_path_segments: int (0 = nessun limite)
     * - allow_object_array_access: bool
     * - allow_object_getter_methods: bool
     * - allow_object_magic_access: bool
     */
    public function configureSecurityPolicy(array $policy): void
    {
        if (array_key_exists('max_execution_steps', $policy)) {
            $this->maxExecutionSteps = max(0, (int)$policy['max_execution_steps']);
        }
        if (array_key_exists('max_parameter_path_segments', $policy)) {
            $this->maxParameterPathSegments = max(0, (int)$policy['max_parameter_path_segments']);
        }
        if (array_key_exists('allow_object_array_access', $policy)) {
            $this->allowObjectArrayAccess = (bool)$policy['allow_object_array_access'];
        }
        if (array_key_exists('allow_object_getter_methods', $policy)) {
            $this->allowObjectGetterMethods = (bool)$policy['allow_object_getter_methods'];
        }
        if (array_key_exists('allow_object_magic_access', $policy)) {
            $this->allowObjectMagicAccess = (bool)$policy['allow_object_magic_access'];
        }
    }

    // ==================== GESTIONE STATO ====================

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function resetVariables(): void
    {
        $this->variables = [];
    }

    public function resetAll(): void
    {
        $this->variables = [];
        $this->parameters = [];
    }

    // ==================== ESECUZIONE AST ====================

    /**
     * Esegue l'AST e restituisce il risultato
     * @param array $ast Albero AST
     * @return mixed Risultato dell'esecuzione
     */
    public function execute(array $ast): mixed
    {
        $this->executionSteps = 0;

        $exec = function (array $node) use (&$exec): mixed {
            if (empty($node)) return null;
            $this->assertExecutionStepLimit();

            switch ($node['type']) {
                case TokenType::NODE_PROGRAM:
                    $result = null;
                    foreach ($node['statements'] ?? [] as $stmt) {
                        $result = $exec($stmt);
                    }
                    return $result;

                case TokenType::NODE_NUMBER:
                case TokenType::NODE_STRING:
                    return $node['value'];

                case TokenType::NODE_DATE:
                    $date = \DateTime::createFromFormat('Y-m-d', $node['value']);
                    $date->setTime(0, 0, 0);
                    return $date;

                case TokenType::NODE_IDENTIFIER:
                    if (is_string($node['value'])) {
                        $upper = strtoupper($node['value']);
                        if ($upper === 'TRUE') return true;
                        if ($upper === 'FALSE') return false;
                    }
                    if (!array_key_exists($node['value'], $this->variables)) {
                        throw new \Exception("Variabile non definita: {$node['value']}");
                    }
                    return $this->variables[$node['value']];

                case TokenType::NODE_PARAMETER:
                    return $this->resolveParameter($node['value']);

                case TokenType::NODE_FUNCTION_CALL:
                    $args = [];
                    foreach ($node['arguments'] ?? [] as $argNode) {
                        $args[] = $exec($argNode);
                    }
                    return $this->functions->execute($node['value'], $args);

                case TokenType::NODE_BINARY_OP:
                    return $this->executeBinaryOp($node['value'], $exec($node['left']), $exec($node['right']));

                case TokenType::NODE_UNARY_OP:
                    return $this->executeUnaryOp($node['value'], $exec($node['right']));

                case TokenType::NODE_ASSIGNMENT:
                    $value = $exec($node['right']);
                    $this->variables[$node['value']] = $value;
                    return $value;

                case TokenType::NODE_IF_STATEMENT:
                    $condition = $exec($node['condition']);

                    if (!is_bool($condition)) {
                        $type = gettype($condition);
                        $val = $this->formatResult($condition);
                        throw new \Exception("IF: la condizione deve essere booleano, ricevuto {$type} ({$val})");
                    }

                    if ($condition === true) {
                        $result = null;
                        foreach ($node['thenBranch'] ?? [] as $stmt) {
                            $result = $exec($stmt);
                        }
                        return $result;
                    }

                    if (!empty($node['elseBranch'])) {
                        $result = null;
                        foreach ($node['elseBranch'] as $stmt) {
                            $result = $exec($stmt);
                        }
                        return $result;
                    }

                    return null;

                default:
                    throw new \Exception("Tipo nodo non supportato: {$node['type']}");
            }
        };

        return $exec($ast);
    }

    // ==================== RISOLUZIONE PARAMETRI ====================

    /**
     * Risolve un parametro, supportando dot-notation per navigare oggetti/array.
     *
     * Esempi:
     *   [salary]                → $this->parameters['salary']
     *   [user.name]             → $this->parameters['user']->name (o ['name'])
     *   [user.comment.title]    → $this->parameters['user']->comment->title
     *   [user.comments.0.title] → $this->parameters['user']->comments[0]->title
     *
     * Ad ogni livello verifica se il corrente è un oggetto o un array.
     *
     * @param string $path Il path completo del parametro (es. "user.comment.title")
     * @return mixed Il valore risolto
     */
    private function resolveParameter(string $path): mixed
    {
        if ($this->maxParameterPathSegments > 0) {
            $segmentCount = substr_count($path, '.') + 1;
            if ($segmentCount > $this->maxParameterPathSegments) {
                throw new \Exception("Parametro [{$path}] supera il limite di segmenti consentiti ({$this->maxParameterPathSegments})");
            }
        }

        // Nessun punto → parametro semplice (fast path, retrocompatibile)
        if (strpos($path, '.') === false) {
            if (!array_key_exists($path, $this->parameters)) {
                throw new \Exception("Parametro non definito: [{$path}]");
            }
            return $this->parameters[$path];
        }

        $segments = explode('.', $path);
        $root = array_shift($segments);

        if (!array_key_exists($root, $this->parameters)) {
            throw new \Exception("Parametro non definito: [{$root}] (in [{$path}])");
        }

        $current = $this->parameters[$root];
        $traversed = $root;

        foreach ($segments as $segment) {
            $traversed .= '.' . $segment;
            $current = $this->resolvePathSegment($current, $segment, $path, $traversed);
        }

        return $current;
    }

    /**
     * Risolve un singolo segmento di path su un valore (oggetto o array).
     *
     * @param mixed $current Valore corrente
     * @param string $segment Segmento da risolvere (proprietà, chiave, o indice numerico)
     * @param string $fullPath Path completo (per messaggi di errore)
     * @param string $traversed Porzione di path già attraversata (per messaggi di errore)
     * @return mixed
     */
    private function resolvePathSegment(mixed $current, string $segment, string $fullPath, string $traversed): mixed
    {
        if ($current === null) {
            throw new \Exception("Impossibile accedere a '{$segment}' su valore null (in [{$fullPath}], a [{$traversed}])");
        }

        // Oggetto → prova proprietà, poi metodo getter, poi accesso array (ArrayAccess)
        if (is_object($current)) {
            // Accesso solo a proprietà pubbliche già materializzate.
            $publicProps = get_object_vars($current);
            if (array_key_exists($segment, $publicProps)) {
                return $publicProps[$segment];
            }

            // Accesso tramite offsetGet (ArrayAccess) — utile per molti ORM/Model
            if ($this->allowObjectArrayAccess && $current instanceof \ArrayAccess && $current->offsetExists($segment)) {
                return $current->offsetGet($segment);
            }

            // Indice numerico su oggetto ArrayAccess (es. comments.0)
            if (
                $this->allowObjectArrayAccess &&
                ctype_digit($segment) &&
                $current instanceof \ArrayAccess &&
                $current->offsetExists((int)$segment)
            ) {
                return $current->offsetGet((int)$segment);
            }

            // Prova getter method: getSegment()
            if ($this->allowObjectGetterMethods) {
                $getter = 'get' . ucfirst($segment);
                if (method_exists($current, $getter)) {
                    return $current->$getter();
                }
            }

            // Ultimo tentativo: accesso diretto (potrebbe avere __get)
            if ($this->allowObjectMagicAccess) {
                try {
                    $val = @$current->$segment;
                    // Per stdClass o oggetti senza __get, la proprietà inesistente dà null + warning
                    // Verifichiamo che esista davvero
                    if ($val !== null) {
                        return $val;
                    }
                    if (method_exists($current, '__isset') && isset($current->$segment)) {
                        return $val;
                    }
                } catch (\Throwable) {
                    // Fallback all'errore finale sotto
                }
            }

            throw new \Exception("Proprietà '{$segment}' non trovata sull'oggetto " . get_class($current) . " (in [{$fullPath}], a [{$traversed}])");
        }

        // Array → accesso per chiave stringa o indice numerico
        if (is_array($current)) {
            if (array_key_exists($segment, $current)) {
                return $current[$segment];
            }

            // Prova come indice numerico
            if (ctype_digit($segment) && array_key_exists((int)$segment, $current)) {
                return $current[(int)$segment];
            }

            throw new \Exception("Chiave '{$segment}' non trovata nell'array (in [{$fullPath}], a [{$traversed}])");
        }

        // Scalare → non navigabile
        $type = gettype($current);
        throw new \Exception("Impossibile accedere a '{$segment}' su un valore di tipo {$type} (in [{$fullPath}], a [{$traversed}])");
    }

    // ==================== OPERATORI ====================

    private function executeBinaryOp(string $op, mixed $left, mixed $right): mixed
    {
        $isDate = fn($v) => $v instanceof \DateTime;
        $isNumber = fn($v) => is_int($v) || is_float($v);
        $isBool = fn($v) => is_bool($v);

        switch ($op) {
            case '+':
                if ($isDate($left) && $isNumber($right)) {
                    $result = clone $left;
                    $result->modify("+{$right} days");
                    return $result;
                }
                if ($isNumber($left) && $isDate($right)) {
                    $result = clone $right;
                    $result->modify("+{$left} days");
                    return $result;
                }
                if ($isDate($left) && $isDate($right)) {
                    throw new \Exception('Non è possibile sommare due date');
                }
                return $left + $right;

            case '-':
                if ($isDate($left) && $isDate($right)) {
                    $diffSeconds = $left->getTimestamp() - $right->getTimestamp();
                    return (int)floor($diffSeconds / 86400);
                }
                if ($isDate($left) && $isNumber($right)) {
                    $result = clone $left;
                    $result->modify("-{$right} days");
                    return $result;
                }
                if ($isNumber($left) && $isDate($right)) {
                    throw new \Exception('Non è possibile sottrarre una data da un numero');
                }
                return $left - $right;

            case '*':
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile moltiplicare date');
                }
                return $left * $right;

            case '/':
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile dividere date');
                }
                return $left / $right;

            case '%':
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile usare modulo con date');
                }
                return $left % $right;

            case '^':
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile usare potenza con date');
                }
                return pow($left, $right);

            case '==':
                if ($isDate($left) && $isDate($right)) {
                    return $left == $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando ==');
                }
                return $left === $right;

            case '!=':
            case '<>':
                if ($isDate($left) && $isDate($right)) {
                    return $left != $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando <>');
                }
                return $left !== $right;

            case '<':
                if ($isDate($left) && $isDate($right)) {
                    return $left < $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando <');
                }
                return $left < $right;

            case '>':
                if ($isDate($left) && $isDate($right)) {
                    return $left > $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando >');
                }
                return $left > $right;

            case '<=':
                if ($isDate($left) && $isDate($right)) {
                    return $left <= $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando <=');
                }
                return $left <= $right;

            case '>=':
                if ($isDate($left) && $isDate($right)) {
                    return $left >= $right;
                }
                if ($isDate($left) || $isDate($right)) {
                    throw new \Exception('Non è possibile confrontare una data con un non-data usando >=');
                }
                return $left >= $right;

            case '&&':
            case 'and':
            case 'AND':
                if (!$isBool($left)) {
                    $type = gettype($left);
                    throw new \Exception("Operatore AND: il valore sinistro deve essere booleano, ricevuto {$type} ({$left})");
                }
                if (!$isBool($right)) {
                    $type = gettype($right);
                    throw new \Exception("Operatore AND: il valore destro deve essere booleano, ricevuto {$type} ({$right})");
                }
                return $left && $right;

            case '||':
            case 'or':
            case 'OR':
                if (!$isBool($left)) {
                    $type = gettype($left);
                    throw new \Exception("Operatore OR: il valore sinistro deve essere booleano, ricevuto {$type} ({$left})");
                }
                if (!$isBool($right)) {
                    $type = gettype($right);
                    throw new \Exception("Operatore OR: il valore destro deve essere booleano, ricevuto {$type} ({$right})");
                }
                return $left || $right;

            default:
                throw new \Exception("Operatore non supportato: {$op}");
        }
    }

    private function executeUnaryOp(string $op, mixed $operand): mixed
    {
        switch ($op) {
            case '-':
                return -$operand;
            case '!':
            case 'not':
            case 'NOT':
                if (!is_bool($operand)) {
                    $type = gettype($operand);
                    throw new \Exception("Operatore NOT: il valore deve essere booleano, ricevuto {$type} ({$operand})");
                }
                return !$operand;
            default:
                throw new \Exception("Operatore unario non supportato: {$op}");
        }
    }

    private function assertExecutionStepLimit(): void
    {
        $this->executionSteps++;
        if ($this->maxExecutionSteps > 0 && $this->executionSteps > $this->maxExecutionSteps) {
            throw new \Exception("Limite di esecuzione superato ({$this->maxExecutionSteps} step)");
        }
    }
}
