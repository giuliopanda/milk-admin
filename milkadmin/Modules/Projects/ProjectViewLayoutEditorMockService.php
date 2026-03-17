<?php
namespace Modules\Projects;

!defined('MILK_DIR') && die();

class ProjectViewLayoutEditorMockService
{
    /**
     * @return array{
     *   version:string,
     *   root_form:string,
     *   cards:array<int,array{
     *     id:string,
     *     type:string,
     *     title:string,
     *     icon:string,
     *     description:string,
     *     rows:array<int,array{
     *       table:string,
     *       display_as:string,
     *       title:string,
     *       icon:string,
     *       visible?:bool
     *     }>
     *   }>,
     *   unassigned_forms:array<int,array{
     *     table:string,
     *     suggested_display:string,
     *     title:string,
     *     icon:string
     *   }>,
     *   raw_layout_json:string,
     *   manifest_strict_mode:bool
     * }
     */
    public static function buildMockData(): array
    {
        $rawLayout = [
            'version' => '1.0',
            'cards' => [
                [
                    'id' => 'main-record',
                    'type' => 'single-table',
                    'table' => [
                        'name' => 'LongitudinalDatabase',
                        'displayAs' => 'fields',
                        'title' => 'Longitudinal Database',
                        'icon' => 'bi bi-clipboard2-pulse',
                    ],
                ],
                [
                    'id' => 'demographics',
                    'type' => 'single-table',
                    'table' => [
                        'name' => 'Demographics',
                        'displayAs' => 'fields',
                        'title' => 'Demographics',
                        'icon' => 'bi bi-person-vcard',
                    ],
                ],
                [
                    'id' => 'baseline-group',
                    'type' => 'group',
                    'title' => 'Baseline and Visits',
                    'icon' => 'bi bi-grid-1x2',
                    'tables' => [
                        [
                            'name' => 'BaselineData',
                            'displayAs' => 'icon',
                            'title' => 'Baseline Data',
                            'icon' => 'bi bi-clipboard2-data',
                        ],
                        [
                            'name' => 'TitleOnlyTest',
                            'displayAs' => 'table',
                            'title' => 'Title Only Test',
                            'icon' => 'bi bi-card-heading',
                            'hideSideTitle' => true,
                            'preHtml' => '<div class="text-muted small">Before table section</div>',
                        ],
                    ],
                ],
                [
                    'id' => 'visit-1',
                    'type' => 'group',
                    'title' => 'Visit 1',
                    'icon' => 'bi bi-calendar-check',
                    'tables' => [
                        [
                            'name' => 'Visit1',
                            'displayAs' => 'table',
                            'title' => 'Visit 1',
                            'icon' => 'bi bi-calendar-check',
                            'hideSideTitle' => true,
                        ],
                        [
                            'name' => 'AdverseEvents',
                            'displayAs' => 'icon',
                            'title' => 'Adverse Events',
                            'icon' => 'bi bi-exclamation-octagon',
                        ],
                    ],
                ],
                [
                    'id' => 'hidden-tables',
                    'type' => 'group',
                    'title' => 'Hidden Tables',
                    'icon' => 'bi bi-eye-slash',
                    'tables' => [
                        [
                            'name' => 'NewProjectDemo',
                            'displayAs' => 'icon',
                            'title' => 'NewProjectDemo',
                            'icon' => '',
                            'visible' => false,
                        ],
                        [
                            'name' => 'NewTablTestNewProject',
                            'displayAs' => 'icon',
                            'title' => 'NewTablTestNewProject',
                            'icon' => '',
                            'visible' => false,
                        ],
                        [
                            'name' => 'Form90890809',
                            'displayAs' => 'icon',
                            'title' => 'Form90890809',
                            'icon' => '',
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ];

        $cards = [
            [
                'id' => 'main-record',
                'type' => 'single-table',
                'title' => 'Longitudinal Database',
                'icon' => 'bi bi-clipboard2-pulse',
                'description' => '',
                'rows' => [
                    [
                        'table' => 'LongitudinalDatabase',
                        'display_as' => 'fields',
                        'title' => 'Longitudinal Database',
                        'icon' => 'bi bi-clipboard2-pulse',
                    ],
                ],
            ],
            [
                'id' => 'demographics',
                'type' => 'single-table',
                'title' => 'Demographics',
                'icon' => 'bi bi-person-vcard',
                'description' => '',
                'rows' => [
                    [
                        'table' => 'Demographics',
                        'display_as' => 'fields',
                        'title' => 'Demographics',
                        'icon' => 'bi bi-person-vcard',
                    ],
                ],
            ],
            [
                'id' => 'baseline-group',
                'type' => 'group',
                'title' => 'Baseline and Visits',
                'icon' => 'bi bi-grid-1x2',
                'description' => '',
                'rows' => [
                    [
                        'table' => 'BaselineData',
                        'display_as' => 'icon',
                        'title' => 'Baseline Data',
                        'icon' => 'bi bi-clipboard2-data',
                    ],
                    [
                        'table' => 'TitleOnlyTest',
                        'display_as' => 'table',
                        'title' => 'Title Only Test',
                        'icon' => 'bi bi-card-heading',
                    ],
                ],
            ],
            [
                'id' => 'visit-1',
                'type' => 'group',
                'title' => 'Visit 1',
                'icon' => 'bi bi-calendar-check',
                'description' => '',
                'rows' => [
                    [
                        'table' => 'Visit1',
                        'display_as' => 'table',
                        'title' => 'Visit 1',
                        'icon' => 'bi bi-calendar-check',
                    ],
                    [
                        'table' => 'AdverseEvents',
                        'display_as' => 'icon',
                        'title' => 'Adverse Events',
                        'icon' => 'bi bi-exclamation-octagon',
                    ],
                ],
            ],
            [
                'id' => 'hidden-tables',
                'type' => 'group',
                'title' => 'Hidden Tables',
                'icon' => 'bi bi-eye-slash',
                'description' => '',
                'rows' => [
                    [
                        'table' => 'NewProjectDemo',
                        'display_as' => 'icon',
                        'title' => 'NewProjectDemo',
                        'icon' => '',
                        'visible' => false,
                    ],
                    [
                        'table' => 'NewTablTestNewProject',
                        'display_as' => 'icon',
                        'title' => 'NewTablTestNewProject',
                        'icon' => '',
                        'visible' => false,
                    ],
                    [
                        'table' => 'Form90890809',
                        'display_as' => 'icon',
                        'title' => 'Form90890809',
                        'icon' => '',
                        'visible' => false,
                    ],
                ],
            ],
        ];

        $rawLayoutJson = json_encode($rawLayout, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($rawLayoutJson) || $rawLayoutJson === '') {
            $rawLayoutJson = '{}';
        }

        return [
            'version' => '1.0',
            'root_form' => 'LongitudinalDatabase',
            'cards' => $cards,
            'unassigned_forms' => [],
            'raw_layout_json' => $rawLayoutJson,
            'manifest_strict_mode' => false,
        ];
    }
}
