<?php
namespace Extensions\Projects;

use App\Abstracts\{AbstractModelExtension, RuleBuilder};
use App\Logs;
use Extensions\Projects\Classes\SchemaJsonAdapter;
use Extensions\Projects\Classes\{ProjectJsonStore, ProjectManifestIndex, ProjectNaming};

!defined('MILK_DIR') && die();

/**
 * Projects Model Extension
 *
 * Extends model RuleBuilder configuration from JSON schema files.
 *
 * Expected JSON path:
 *   <ModuleDir>/Project/<ModelNameWithoutModel>.json
 *
 * Examples:
 * - AssenzeModel -> Project/Assenze.json
 * - OrdersModel  -> Project/Orders.json
 */
class Model extends AbstractModelExtension
{
    protected string $schemaFolder = 'Project';

    /**
     * Apply JSON schema model section to the current RuleBuilder.
     */
    public function configure(RuleBuilder $rule_builder): void
    {
        $model = $this->model->get();
        if (!$model) {
            return;
        }

        $jsonPath = $this->resolveSchemaPath($model);
        if (!is_file($jsonPath)) {
            return;
        }

        $projectDir = dirname($jsonPath);
        $schemaName = pathinfo($jsonPath, PATHINFO_FILENAME);
        $store = ProjectJsonStore::for($projectDir);
        $schema = $store->schema($schemaName);

        if (!is_array($schema)) {
            $errorMessage = $this->lastStoreMessage($store->getErrors());
            if ($errorMessage !== '') {
                Logs::set('SYSTEM', "Projects extension: invalid JSON in {$jsonPath} - {$errorMessage}", 'ERROR');
            } else {
                Logs::set('SYSTEM', "Projects extension: empty or unreadable JSON schema at {$jsonPath}", 'ERROR');
            }
            return;
        }

        if (!isset($schema['model']) || !is_array($schema['model'])) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($model);

            $adapter = new SchemaJsonAdapter();
            $adapter->setCallableResolver(
                fn(string $expression) => $this->resolveCallableExpression($expression)
            );
            $adapter->setModelClassResolver(
                fn(string $className) => $this->resolveModelClassName($className, $reflection)
            );

            // Apply only the "model" section to the current RuleBuilder.
            $adapter->applyArray(['model' => $rule_builder], ['model' => $schema['model']]);

            // Apply manifest-driven conventions:
            // - Inject FK field into child forms
            // - Add withCount aggregates to the default/main form
            $this->applyManifestConventions($rule_builder, $reflection);
        } catch (\Throwable $e) {
            Logs::set('SYSTEM', "Projects extension: failed to apply schema {$jsonPath} - {$e->getMessage()}", 'ERROR');
        }
    }

    /**
     * Resolve schema path based on model class name.
     *
     * JSON name rule:
     * model short name without the word "Model" + ".json"
     */
    protected function resolveSchemaPath(object $model): string
    {
        $reflection = new \ReflectionClass($model);
        $moduleDir = $this->resolveModuleDirFromPath((string) $reflection->getFileName());
        $shortName = $reflection->getShortName();
        $jsonName = str_replace('Model', '', $shortName) . '.json';

        return $this->findSchemaPath($moduleDir, $jsonName) ?? ($moduleDir . '/' . $this->schemaFolder . '/' . $jsonName);
    }

    protected function applyManifestConventions(RuleBuilder $rule_builder, \ReflectionClass $currentModelReflection): void
    {
        $moduleDir = $this->resolveModuleDirFromPath((string) $currentModelReflection->getFileName());
        $manifestPath = $this->findManifestPath($moduleDir);
        if ($manifestPath === null) {
            return;
        }

        $store = ProjectJsonStore::for(dirname($manifestPath));
        $index = $store->manifestIndex();
        if (!$index instanceof ProjectManifestIndex) {
            $errorMessage = $this->lastStoreMessage($store->getErrors());
            if ($errorMessage === '') {
                $errorMessage = 'empty, unreadable, or invalid JSON';
            }
            Logs::set('SYSTEM', "Projects extension: invalid manifest {$manifestPath} - {$errorMessage}", 'ERROR');
            return;
        }

        $shortName = $currentModelReflection->getShortName();
        $currentFormName = str_replace('Model', '', $shortName);
        $node = $index->getNode($currentFormName);
        if (!is_array($node)) {
            return;
        }

        $moduleNamespace = $this->resolveModuleNamespace($currentModelReflection->getNamespaceName());

        // Child form: ensure FK fields exist.
        // - parent FK: points to immediate parent
        // - root_id : closure-style pointer to root record id
        $parentFormName = (string) ($node['parent_form_name'] ?? '');
        if ($parentFormName !== '') {
            $fkField = ProjectNaming::foreignKeyFieldForParentForm($parentFormName);
            $rootIdField = ProjectNaming::rootIdField();
            $chainFields = $index->getFkChainFields($currentFormName);
            $rootFkField = $chainFields[0] ?? $fkField;
            $requestedParentId = _absint($_REQUEST[$fkField] ?? 0);
            $requestedRootId = _absint($_REQUEST[$rootFkField] ?? 0);

            $existing = $rule_builder->getRules();
            if (!isset($existing[$fkField])) {
                $rule_builder
                    ->int($fkField)
                        ->label(ProjectNaming::toTitle($parentFormName) . ' ID')
                        ->hideFromList()
                        ->formParams(['readonly' => true])
                        // For new records, if FK is passed via URL we can prefill it (edit verification happens in Module extension).
                        ->default($requestedParentId > 0 ? $requestedParentId : null)
                        ->required();
            } else {
                $rule_builder
                    ->ChangeCurrentField($fkField)
                        ->label(ProjectNaming::toTitle($parentFormName) . ' ID')
                        ->hideFromList()
                        ->formParams(['readonly' => true])
                        ->required();
                if ($requestedParentId > 0) {
                    $rule_builder->default($requestedParentId);
                }
            }

            $existing = $rule_builder->getRules();
            if (!isset($existing[$rootIdField])) {
                $rule_builder
                    ->int($rootIdField)
                        ->label('Root ID')
                        ->hideFromList()
                        ->formParams(['readonly' => true])
                        // root_id follows the root FK in URL chain (for first-level children this equals parent FK).
                        ->default($requestedRootId > 0 ? $requestedRootId : null)
                        ->required();
            } else {
                $rule_builder
                    ->ChangeCurrentField($rootIdField)
                        ->label('Root ID')
                        ->hideFromList()
                        ->formParams(['readonly' => true])
                        ->required();
                if ($requestedRootId > 0) {
                    $rule_builder->default($requestedRootId);
                }
            }
        }

        // Current form: attach withCount for each direct child form (if any).
        $children = $index->getChildrenFormNames($currentFormName);
        if (empty($children)) {
            return;
        }

        $pkField = $rule_builder->getPrimaryKey() ?? null;
        if ($pkField === null || $pkField === '') {
            $pkField = method_exists($this->model->get(), 'getPrimaryKey') ? $this->model->get()->getPrimaryKey() : null;
        }
        if ($pkField === null || $pkField === '') {
            return;
        }

        $rule_builder->ChangeCurrentField($pkField);
        $childFkField = ProjectNaming::foreignKeyFieldForParentForm($currentFormName);

        foreach ($children as $childFormName) {
            $relatedModelClass = $this->resolveModelClassFromNamespace($moduleNamespace, $childFormName);
            if ($relatedModelClass === null) {
                Logs::set('SYSTEM', "Projects extension: missing related model '{$childFormName}Model' for withCount in {$shortName}", 'ERROR');
                continue;
            }

            $alias = ProjectNaming::withCountAliasForForm($childFormName);
            $rule_builder->withCount($alias, $relatedModelClass, $childFkField);

            $rules = $rule_builder->getRules();
            if (isset($rules[$alias]) && is_array($rules[$alias])) {
                $rules[$alias]['list'] = true;
                $rules[$alias]['edit'] = false;
                $rules[$alias]['view'] = true;
                $rules[$alias]['label'] = ProjectNaming::toTitle($childFormName);
                $rule_builder->setRules($rules);
                $rule_builder->ChangeCurrentField($pkField);
            }
        }
    }

    protected function resolveModelClassFromNamespace(string $moduleNamespace, string $formName): ?string
    {
        $direct = $moduleNamespace . '\\' . $formName . 'Model';
        if (class_exists($direct)) {
            return $direct;
        }

        $studly = $moduleNamespace . '\\' . ProjectNaming::toStudlyCase($formName) . 'Model';
        if (class_exists($studly)) {
            return $studly;
        }

        $projectDirect = $moduleNamespace . '\\Project\\Models\\' . $formName . 'Model';
        if (class_exists($projectDirect)) {
            return $projectDirect;
        }

        $projectStudly = $moduleNamespace . '\\Project\\Models\\' . ProjectNaming::toStudlyCase($formName) . 'Model';
        if (class_exists($projectStudly)) {
            return $projectStudly;
        }

        return null;
    }

    /**
     * Resolve callable strings like "\StaticData::Days()".
     */
    protected function resolveCallableExpression(string $expression): array
    {
        $callable = rtrim(trim($expression), '()');

        if (!is_callable($callable)) {
            return [];
        }

        $result = $callable();
        return is_array($result) ? $result : [];
    }

    /**
     * Resolve relationship model class names coming from JSON.
     *
     * Accepted:
     * - Fully qualified names (returned as-is)
     * - Short names (resolved with heuristics)
     */
    protected function resolveModelClassName(string $className, \ReflectionClass $currentModelReflection): string
    {
        $className = ltrim(trim($className), '\\');
        if ($className === '') {
            return $className;
        }

        // Already fully qualified or globally available
        if (str_contains($className, '\\') || class_exists($className)) {
            return $className;
        }

        // 1) Try current model namespace first
        $currentNamespace = $currentModelReflection->getNamespaceName();
        $sameNamespace = $currentNamespace . '\\' . $className;
        if (class_exists($sameNamespace)) {
            return $sameNamespace;
        }

        // 1.5) Try the module root and Project\Models namespace
        $moduleNamespace = $this->resolveModuleNamespace($currentNamespace);
        $moduleRootClass = $moduleNamespace . '\\' . $className;
        if (class_exists($moduleRootClass)) {
            return $moduleRootClass;
        }

        $projectModelsClass = $moduleNamespace . '\\Project\\Models\\' . $className;
        if (class_exists($projectModelsClass)) {
            return $projectModelsClass;
        }

        // 2) Try Local\Modules\<ModelBaseName>\<ClassName>
        $base = preg_replace('/Model$/', '', $className);
        $localModuleClass = 'Local\\Modules\\' . $base . '\\' . $className;
        if (class_exists($localModuleClass)) {
            return $localModuleClass;
        }

        // 3) Try core Modules\<ModelBaseName>\<ClassName>
        $coreModuleClass = 'Modules\\' . $base . '\\' . $className;
        if (class_exists($coreModuleClass)) {
            return $coreModuleClass;
        }

        // Fallback: keep original (RuleBuilder will throw if invalid when used).
        return $className;
    }

    protected function resolveModuleDirFromPath(string $modelFilePath): string
    {
        $normalized = str_replace('\\', '/', $modelFilePath);
        if (preg_match('~^(.*?/Modules/[^/]+)(?:/.*)?$~', $normalized, $matches) === 1) {
            return $matches[1];
        }
        return dirname($modelFilePath);
    }

    protected function getSchemaFolderCandidates(): array
    {
        return [$this->schemaFolder];
    }

    protected function findManifestPath(string $moduleDir): ?string
    {
        foreach ($this->getSchemaFolderCandidates() as $folder) {
            $path = $moduleDir . '/' . $folder . '/manifest.json';
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    protected function findSchemaPath(string $moduleDir, string $jsonName): ?string
    {
        foreach ($this->getSchemaFolderCandidates() as $folder) {
            $path = $moduleDir . '/' . $folder . '/' . $jsonName;
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    protected function resolveModuleNamespace(string $namespace): string
    {
        $parts = explode('\\', trim($namespace, '\\'));

        if ($parts[0] === 'Local' && ($parts[1] ?? '') === 'Modules' && isset($parts[2])) {
            return implode('\\', array_slice($parts, 0, 3));
        }

        if ($parts[0] === 'Modules' && isset($parts[1])) {
            return implode('\\', array_slice($parts, 0, 2));
        }

        return $namespace;
    }

    protected function lastStoreMessage(array $messages): string
    {
        if (empty($messages)) {
            return '';
        }
        $last = end($messages);
        return is_string($last) ? trim($last) : '';
    }
}
