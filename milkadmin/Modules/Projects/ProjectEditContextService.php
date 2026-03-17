<?php

namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectEditContextService
{
    /**
     * Build shared context box data for project editing pages.
     *
     * @param array<string,mixed>|null $project
     * @return array{
     *   show:bool,
     *   has_project:bool,
     *   section:string,
     *   project_name:string,
     *   module_name:string,
     *   back_to_projects_url:string,
     *   title_actions:array<int,array{label:string,url:string,class:string,id:string}>,
     *   right_actions:array<int,array{label:string,url:string,class:string,id:string}>
     * }
     */
    public static function buildBoxData(?array $project, string $section, string $projectsPage = 'projects', array $options = []): array
    {
        $section = trim($section);
        if ($section === '') {
            $section = 'Project';
        }
        $projectsPage = trim($projectsPage);
        if ($projectsPage === '') {
            $projectsPage = 'projects';
        }

        $moduleName = trim((string) ($project['module_name'] ?? ''));
        $projectName = trim((string) ($project['project_name'] ?? ''));
        if ($projectName === '') {
            $projectName = $moduleName !== '' ? $moduleName : 'Project';
        }

        $titleActions = self::normalizeActions($options['title_actions'] ?? []);
        $rightActions = self::normalizeActions($options['right_actions'] ?? []);
        $hideDefaultBack = !empty($options['hide_default_back']);
        if (!$hideDefaultBack && empty($rightActions)) {
            $rightActions[] = [
                'label' => 'Back to projects list',
                'url' => '?page=' . rawurlencode($projectsPage),
                'class' => 'btn btn-sm btn-outline-secondary',
                'id' => '',
            ];
        }

        return [
            'show' => true,
            'has_project' => is_array($project),
            'section' => $section,
            'project_name' => $projectName,
            'module_name' => $moduleName,
            'back_to_projects_url' => '?page=' . rawurlencode($projectsPage),
            'title_actions' => $titleActions,
            'right_actions' => $rightActions,
        ];
    }

    /**
     * @param mixed $rawActions
     * @return array<int,array{label:string,url:string,class:string,id:string}>
     */
    private static function normalizeActions(mixed $rawActions): array
    {
        if (!is_array($rawActions)) {
            return [];
        }

        $actions = [];
        foreach ($rawActions as $rawAction) {
            if (!is_array($rawAction)) {
                continue;
            }

            $label = trim((string) ($rawAction['label'] ?? ''));
            $url = trim((string) ($rawAction['url'] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }

            $actions[] = [
                'label' => $label,
                'url' => $url,
                'class' => trim((string) ($rawAction['class'] ?? 'btn btn-outline-secondary')),
                'id' => trim((string) ($rawAction['id'] ?? '')),
            ];
        }

        return $actions;
    }
}
