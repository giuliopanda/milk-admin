<?php

namespace Modules\Projects\DraftService;

!defined('MILK_DIR') && die();

use Modules\Projects\ContainerNormalizer;

class DraftContainerNormalizer
{
    /**
     * @param array<int,mixed> $rawContainers
     * @param array<string,bool> $allowedFieldMap lowercase field map, empty = no strict field set
     * @return array{
     *   containers:array<int,array{id:string,fields:array<int,string|array<int,string>>,cols:int|array<int,int>,position_before:string,title:string,attributes:array<string,mixed>}>,
     *   error:string
     * }
     */
    public static function normalize(array $rawContainers, array $allowedFieldMap = [], bool $strict = true): array
    {
        $containers = [];
        $seenContainerIds = [];
        $assignedFields = [];

        foreach ($rawContainers as $index => $rawContainer) {
            if (!is_array($rawContainer)) {
                continue;
            }

            $id = trim((string) ($rawContainer['id'] ?? ''));
            if ($id === '') {
                if ($strict) {
                    return [
                        'containers' => [],
                        'error' => 'Invalid container at position ' . ($index + 1) . ': missing container id.',
                    ];
                }
                continue;
            }
            if (!ContainerNormalizer::isValidContainerId($id)) {
                if ($strict) {
                    return [
                        'containers' => [],
                        'error' => "Invalid container id '{$id}'. Use letters, numbers, underscore or dash.",
                    ];
                }
                continue;
            }

            $idLower = strtolower($id);
            if (isset($seenContainerIds[$idLower])) {
                if ($strict) {
                    return [
                        'containers' => [],
                        'error' => "Duplicate container id '{$id}'.",
                    ];
                }
                continue;
            }

            $fields = [];
            $seenFieldNames = [];
            $rawFields = is_array($rawContainer['fields'] ?? null) ? $rawContainer['fields'] : [];
            foreach ($rawFields as $fieldEntry) {
                if (is_array($fieldEntry)) {
                    $group = [];
                    foreach ($fieldEntry as $groupEntry) {
                        if (is_array($groupEntry)) {
                            if ($strict) {
                                return [
                                    'containers' => [],
                                    'error' => "Container '{$id}' supports nested field arrays only one level deep.",
                                ];
                            }
                            continue;
                        }

                        $fieldName = trim((string) $groupEntry);
                        if ($fieldName === '') {
                            continue;
                        }

                        $fieldLower = strtolower($fieldName);
                        if (!empty($allowedFieldMap) && !isset($allowedFieldMap[$fieldLower])) {
                            if ($strict) {
                                return [
                                    'containers' => [],
                                    'error' => "Container '{$id}' references unknown field '{$fieldName}'.",
                                ];
                            }
                            continue;
                        }

                        if (isset($seenFieldNames[$fieldLower])) {
                            continue;
                        }
                        if (isset($assignedFields[$fieldLower])) {
                            if ($strict) {
                                return [
                                    'containers' => [],
                                    'error' => "Field '{$fieldName}' is already assigned to container '{$assignedFields[$fieldLower]}'.",
                                ];
                            }
                            continue;
                        }
                        $seenFieldNames[$fieldLower] = true;
                        $assignedFields[$fieldLower] = $id;
                        $group[] = $fieldName;
                    }

                    if (count($group) === 1) {
                        $fields[] = $group[0];
                    } elseif (!empty($group)) {
                        $fields[] = $group;
                    }
                    continue;
                }

                $fieldName = trim((string) $fieldEntry);
                if ($fieldName === '') {
                    continue;
                }

                $fieldLower = strtolower($fieldName);
                if (!empty($allowedFieldMap) && !isset($allowedFieldMap[$fieldLower])) {
                    if ($strict) {
                        return [
                            'containers' => [],
                            'error' => "Container '{$id}' references unknown field '{$fieldName}'.",
                        ];
                    }
                    continue;
                }

                if (isset($seenFieldNames[$fieldLower])) {
                    continue;
                }
                if (isset($assignedFields[$fieldLower])) {
                    if ($strict) {
                        return [
                            'containers' => [],
                            'error' => "Field '{$fieldName}' is already assigned to container '{$assignedFields[$fieldLower]}'.",
                        ];
                    }
                    continue;
                }
                $seenFieldNames[$fieldLower] = true;
                $assignedFields[$fieldLower] = $id;
                $fields[] = $fieldName;
            }

            if (empty($fields)) {
                if ($strict) {
                    return [
                        'containers' => [],
                        'error' => "Container '{$id}' must include at least one field.",
                    ];
                }
                continue;
            }

            $positionBefore = trim((string) ($rawContainer['position_before'] ?? ($rawContainer['positionBefore'] ?? '')));
            if (
                $positionBefore !== ''
                && !empty($allowedFieldMap)
                && !isset($allowedFieldMap[strtolower($positionBefore)])
            ) {
                if ($strict) {
                    return [
                        'containers' => [],
                        'error' => "Container '{$id}' has invalid position_before '{$positionBefore}'.",
                    ];
                }
                $positionBefore = '';
            }

            $seenContainerIds[$idLower] = true;
            $containers[] = [
                'id' => $id,
                'fields' => $fields,
                'cols' => ContainerNormalizer::normalizeContainerCols($rawContainer['cols'] ?? count($fields), count($fields)),
                'position_before' => $positionBefore,
                'title' => trim((string) ($rawContainer['title'] ?? '')),
                'attributes' => ContainerNormalizer::normalizeContainerAttributes($rawContainer['attributes'] ?? []),
            ];
        }

        return [
            'containers' => $containers,
            'error' => '',
        ];
    }
}
