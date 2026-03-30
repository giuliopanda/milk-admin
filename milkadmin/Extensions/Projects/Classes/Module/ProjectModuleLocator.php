<?php
namespace Extensions\Projects\Classes\Module;

use App\Abstracts\AbstractModule;
use Extensions\Projects\Classes\ProjectJsonStore;
use Extensions\Projects\Classes\ProjectNaming;

!defined('MILK_DIR') && die();

class ProjectModuleLocator
{
    protected AbstractModule $module;
    protected string $schemaFolder;

    public function __construct(AbstractModule $module, string $schemaFolder = 'Project')
    {
        $this->module = $module;
        $this->schemaFolder = $schemaFolder;
    }

    public function resolveModuleNamespace(): string
    {
        $moduleReflection = new \ReflectionClass($this->module);
        return (string) $moduleReflection->getNamespaceName();
    }

    public function resolveModuleDir(): string
    {
        $moduleReflection = new \ReflectionClass($this->module);
        return $this->resolveModuleDirFromPath((string) $moduleReflection->getFileName());
    }

    public function resolveModuleDirFromPath(string $moduleFilePath): string
    {
        $normalized = str_replace('\\', '/', $moduleFilePath);
        if (preg_match('~^(.*?/Modules/[^/]+)(?:/.*)?$~', $normalized, $matches) === 1) {
            return $matches[1];
        }

        return dirname($moduleFilePath);
    }

    /**
     * @return array<int,string>
     */
    public function getSchemaFolderCandidates(): array
    {
        return [$this->schemaFolder];
    }

    public function findManifestPath(?string $moduleDir = null): ?string
    {
        $resolvedModuleDir = $moduleDir ?? $this->resolveModuleDir();
        foreach ($this->getSchemaFolderCandidates() as $folder) {
            $path = $resolvedModuleDir . '/' . $folder . '/manifest.json';
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function resolveProjectsJsonStore(): ?ProjectJsonStore
    {
        try {
            $manifestPath = $this->findManifestPath();
            if ($manifestPath === null) {
                return null;
            }

            return ProjectJsonStore::for(dirname($manifestPath));
        } catch (\Throwable) {
            return null;
        }
    }

    public function resolveModelClassForForm(string $moduleNamespace, string $formName): ?string
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
}
