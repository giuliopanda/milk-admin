<?php
namespace Extensions\Audit\Views;

use Builders\TitleBuilder;

!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * Helper function to highlight differences between two strings
 * Returns the current value with changed parts highlighted in yellow
 */

// All fields are normal fields (no audit fields in main table anymore)
$normalFields = $fields;

// Check if current values differ from last audit
$hasDifferences = false;
$differentFields = [];
if (!$isDeleted && $currentRecord && !empty($auditRecords)) {
    $lastAudit = $auditRecords[0];
    foreach ($normalFields as $fieldName => $fieldInfo) {
        $currentValue = $currentRecord->$fieldName ?? '';
        $lastAuditValue = $lastAudit->$fieldName ?? '';
        if ($currentValue !== $lastAuditValue) {
            $hasDifferences = true;
            $differentFields[] = $fieldInfo['label'];
        }
    }
}

// Get delete info if record was deleted
$deleteTitle = '';
if ($isDeleted && $deleteInfo) {
    $user = \App\Get::make('Auth')->getUser($deleteInfo->audit_user_id);
    $username = $user ? $user->username : 'User ' . $deleteInfo->audit_user_id;
    $deleteTitle = '<strong>Deleted by:</strong> ' . $username . '  <small class="text-muted ms-2">' . $deleteInfo->audit_timestamp . '</small>';
}

// Get creation info from oldest audit record
$creationInfo = null;
if (!empty($auditRecords)) {
    $oldestAudit = end($auditRecords);
    if ($oldestAudit->audit_action === 'insert') {
        $createdUser = \App\Get::make('Auth')->getUser($oldestAudit->audit_user_id);
        $creationInfo = [
            'username' => $createdUser ? $createdUser->username : 'User ' . $oldestAudit->audit_user_id,
            'date' => $oldestAudit->audit_timestamp ?? ''
        ];
    }
}

?>
<div class="card">
    <div class="card-header">
        <?php
        $titleBuilder = TitleBuilder::create($title);
        echo $titleBuilder;
        ?>
    </div>
    <div class="card-body">
        <!-- Creation Info Box -->
        <?php if ($creationInfo): ?>
        <div class="alert alert-info mb-3 py-2" style="border-left: 4px solid #0dcaf0;">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-person-plus-fill" style="font-size: 1.5rem;"></i>
                </div>
                <div class="col">
                    <strong>Created by:</strong> <?php _pt($creationInfo['username']); ?>
                    <small class="text-muted ms-2"><?php _p($creationInfo['date']); ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Delete Info Box -->
        <?php if ($deleteTitle): ?>
            <div class="alert alert-danger mb-3 py-2" style="border-left: 4px solid #dc3545;">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-trash-fill" style="font-size: 1.5rem;"></i>
                    </div>
                    <div class="col">
                        <?php echo $deleteTitle; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alert if there are untracked changes -->
        <?php if ($hasDifferences): ?>
        <div class="alert alert-warning mb-3 py-2" style="border-left: 4px solid #ffc107;">
            <div class="row align-items-center">
                <div class="col-auto">
                    <i class="bi bi-exclamation-triangle-fill" style="font-size: 1.5rem;"></i>
                </div>
                <div class="col">
                    <strong>Warning:</strong> There are changes not tracked in audit:
                    <small class="text-muted"><?php echo implode(', ', $differentFields); ?></small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Fields Table -->
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover" style="font-size: 0.875rem;">
                <thead class="table-light">
                    <tr>
                        <th class="align-middle" style="width: 150px;">Field</th>
                        <?php if (!$isDeleted && $currentRecord && !$hasDifferences): ?>
                            <!-- Don't show current value column if values match last audit -->
                        <?php elseif (!$isDeleted && $currentRecord): ?>
                            <th class="align-middle text-center" style="min-width: 200px;">
                                <div class="d-flex flex-column align-items-center">
                                    <span class="badge bg-success mb-1">Current</span>
                                </div>
                            </th>
                        <?php endif; ?>
                        <?php foreach ($auditRecords as $index => $audit): ?>
                            <?php
                            $user = \App\Get::make('Auth')->getUser($audit->audit_user_id);
                            $username = $user ? $user->username : 'User ' . $audit->audit_user_id;
                            $date = $audit->audit_timestamp;
                            $action = $audit->audit_action;

                            // Check if should show restore button
                            $showRestore = true;

                            // Don't show restore for insert action
                            if ($action === 'insert' && count($auditRecords) == 1) {
                                $showRestore = false;
                            }

                            // Don't show restore if values match current record
                            if (!$isDeleted && $currentRecord && $showRestore) {
                                $isIdenticalToCurrent = true;
                                foreach ($normalFields as $fieldName => $fieldInfo) {
                                    $currentValue = $currentRecord->$fieldName ?? '';
                                    $auditValue = $audit->$fieldName ?? '';
                                    if ($currentValue !== $auditValue) {
                                        $isIdenticalToCurrent = false;
                                        break;
                                    }
                                }
                                if ($isIdenticalToCurrent) {
                                    $showRestore = false;
                                }
                            }

                            // Calculate changed fields for modal
                            $changedFields = [];
                            if ($showRestore && !$isDeleted && $currentRecord) {
                                foreach ($normalFields as $fieldName => $fieldInfo) {
                                    $currentValue = $currentRecord->$fieldName ?? '';
                                    $auditValue = $audit->$fieldName ?? '';
                                    if ($currentValue !== $auditValue) {
                                        $changedFields[] = [
                                            'name' => $fieldName,
                                            'label' => $fieldInfo['label'],
                                            'current' => $currentValue,
                                            'restore' => $auditValue
                                        ];
                                    }
                                }
                            }
                            ?>
                            <th class="align-middle text-center" style="min-width: 200px;">
                                <div class="d-flex flex-column align-items-center" style="gap: 3px;">
                                    <small style="font-size: 0.75rem;" class="text-muted">
                                        <?php _p($date); ?> | <?php _p($username); ?> | <?php _p(ucfirst($action)); ?>
                                    </small>
                                    <?php if ($showRestore): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-primary mt-1 py-0 px-2 js-audit-restore-btn"
                                                style="font-size: 0.7rem;"
                                                data-restore-url="?page=<?php _p($page); ?>&action=audit-restore&id=<?php _p($record_id); ?>&audit_id=<?php echo $audit->audit_id; ?>"
                                                data-audit-date="<?php _p($date); ?>"
                                                data-audit-user="<?php _p($username); ?>"
                                                data-changed-fields="<?php _p(json_encode($changedFields)); ?>">
                                            <i class="bi bi-arrow-counterclockwise"></i> Restore
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($normalFields as $fieldName => $fieldInfo): ?>
                        <tr>
                            <td class="align-middle"><strong style="font-size: 0.8rem;"><?php _p($fieldInfo['label']); ?></strong></td>
                            <?php if (!$isDeleted && $currentRecord && $hasDifferences): ?>
                                <td class="align-middle">
                                    <div style="max-height: 150px; overflow-y: auto; font-size: 0.8rem;">
                                        <?php
                                        // Get current value
                                        $currentValue = $currentRecord->$fieldName ?? '';
                                        // Get previous value (first audit record)
                                        $previousValue = isset($auditRecords[0]) ? ($auditRecords[0]->$fieldName ?? '') : '';
                                        // Highlight differences
                                        if ($previousValue !== '') {
                                            echo highlightDiff($currentValue, $previousValue);
                                        } else {
                                            echo formatTextForDisplay($currentValue);
                                        }
                                        ?>
                                    </div>
                                </td>
                            <?php endif; ?>
                            <?php foreach ($auditRecords as $index => $audit): ?>
                                <td class="align-middle">
                                    <div style="max-height: 150px; overflow-y: auto; font-size: 0.8rem;">
                                        <?php
                                        // Get current audit value
                                        $currentValue = $audit->$fieldName ?? '';
                                        // Get next audit value (older version)
                                        $previousValue = isset($auditRecords[$index + 1]) ? ($auditRecords[$index + 1]->$fieldName ?? '') : '';
                                        // Highlight differences
                                        if ($previousValue !== '') {
                                            echo highlightDiff($currentValue, $previousValue);
                                        } else {
                                            echo formatTextForDisplay($currentValue);
                                        }
                                        ?>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <a href="?page=<?php echo $page; ?>&action=audit" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Audit Trail
            </a>
        </div>
    </div>
</div>



<?php

function highlightDiff($current, $previous) {
    if ($previous === null || $previous === '') {
        return '<span style="background-color: #90EE90;">' . formatTextForDisplay($current) . '</span>';
    }

    if ($current === $previous) {
        return formatTextForDisplay($current);
    }

    // Tokenizza preservando gli spazi
    $currentTokens = tokenize($current);
    $previousTokens = tokenize($previous);

    // Calcola la LCS
    $lcs = computeLCS($previousTokens, $currentTokens);

    // Genera l'output con le differenze evidenziate
    return generateDiffOutput($previousTokens, $currentTokens, $lcs);
}

/**
 * Format text for display: preserve <br> tags and convert \n to <br>
 */
function formatTextForDisplay($text) {
    if ($text === null || $text === '') {
        return '';
    }

    // First escape all HTML
    $text = _r($text);

    // Then restore <br> tags (they were escaped to &lt;br&gt;)
    $text = str_replace('&lt;br&gt;', '<br>', $text);
    $text = str_replace('&lt;br /&gt;', '<br>', $text);
    $text = str_replace('&lt;br/&gt;', '<br>', $text);

    // Convert newlines to <br>
    $text = nl2br($text);

    return $text;
}

function tokenize($text) {
    // Tokenizza separando:
    // - Tag HTML (<...>)
    // - Spazi bianchi
    // - Parole/punteggiatura
    preg_match_all('/<[^>]+>|\s+|[^\s<]+/', $text, $matches);
    return $matches[0];
}

function computeLCS($old, $new) {
    $oldLen = count($old);
    $newLen = count($new);

    // Matrice per la programmazione dinamica
    $matrix = array_fill(0, $oldLen + 1, array_fill(0, $newLen + 1, 0));

    // Riempi la matrice
    for ($i = 1; $i <= $oldLen; $i++) {
        for ($j = 1; $j <= $newLen; $j++) {
            if ($old[$i - 1] === $new[$j - 1]) {
                $matrix[$i][$j] = $matrix[$i - 1][$j - 1] + 1;
            } else {
                $matrix[$i][$j] = max($matrix[$i - 1][$j], $matrix[$i][$j - 1]);
            }
        }
    }

    // Ricostruisci la LCS con le posizioni
    $lcs = [];
    $i = $oldLen;
    $j = $newLen;

    while ($i > 0 && $j > 0) {
        if ($old[$i - 1] === $new[$j - 1]) {
            array_unshift($lcs, [
                'token' => $old[$i - 1],
                'oldIndex' => $i - 1,
                'newIndex' => $j - 1
            ]);
            $i--;
            $j--;
        } elseif ($matrix[$i - 1][$j] > $matrix[$i][$j - 1]) {
            $i--;
        } else {
            $j--;
        }
    }

    return $lcs;
}

function generateDiffOutput($old, $new, $lcs) {
    $result = '';
    $newIndex = 0;
    $lcsIndex = 0;

    while ($newIndex < count($new)) {
        if ($lcsIndex < count($lcs) && $lcs[$lcsIndex]['newIndex'] === $newIndex) {
            // Token presente in entrambi (invariato)
            $result .= formatTextForDisplay($new[$newIndex]);
            $lcsIndex++;
        } else {
            // Token aggiunto (non presente nella LCS per questa posizione)
            $token = $new[$newIndex];
            if (trim($token) === '') {
                // È solo spazio bianco, non evidenziare
                $result .= formatTextForDisplay($token);
            } else {
                $result .= '<span style="background-color: #90EE90;">' . formatTextForDisplay($token) . '</span>';
            }
        }
        $newIndex++;
    }

    return $result;
}

// Versione avanzata che mostra anche le rimozioni
function highlightDiffFull($current, $previous) {
    if ($previous === null || $previous === '') {
        return [
            'current' => '<span style="background-color: #90EE90;">' . _r($current) . '</span>',
            'previous' => ''
        ];
    }

    if ($current === $previous) {
        return [
            'current' => _r($current),
            'previous' => _r($previous)
        ];
    }

    $currentTokens = tokenize($current);
    $previousTokens = tokenize($previous);
    $lcs = computeLCS($previousTokens, $currentTokens);

    // Genera output per il testo corrente (aggiunte in verde)
    $currentOutput = generateDiffOutputWithType($currentTokens, $lcs, 'new', '#90EE90');

    // Genera output per il testo precedente (rimozioni in rosso)
    $previousOutput = generateDiffOutputWithType($previousTokens, $lcs, 'old', '#ffcccb');

    return [
        'current' => $currentOutput,
        'previous' => $previousOutput
    ];
}

function generateDiffOutputWithType($tokens, $lcs, $type, $color) {
    $result = '';
    $tokenIndex = 0;
    $lcsIndex = 0;
    $indexKey = ($type === 'new') ? 'newIndex' : 'oldIndex';

    while ($tokenIndex < count($tokens)) {
        if ($lcsIndex < count($lcs) && $lcs[$lcsIndex][$indexKey] === $tokenIndex) {
            // Token presente in entrambi
            $result .= formatTextForDisplay($tokens[$tokenIndex]);
            $lcsIndex++;
        } else {
            // Token aggiunto o rimosso
            $token = $tokens[$tokenIndex];
            if (trim($token) === '') {
                $result .= formatTextForDisplay($token);
            } else {
                $result .= '<span style="background-color: ' . $color . ';">' . formatTextForDisplay($token) . '</span>';
            }
        }
        $tokenIndex++;
    }

    return $result;
}
