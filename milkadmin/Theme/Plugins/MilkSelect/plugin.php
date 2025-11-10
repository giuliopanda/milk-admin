<?php
!defined('MILK_DIR') && die(); // Avoid direct access

/**
 * MilkSelect Plugin
 *
 * Autocomplete select component with single and multiple selection support
 *
 * @var string $id - Unique ID for the element (auto-generated if not provided)
 * @var array $options - Array of options to display in the dropdown (can be indexed or associative array)
 * @var string $type - Selection type: 'single' or 'multiple' (default: 'single')
 * @var mixed $value - Pre-selected value(s) - can be string, array, or key from options
 * @var string $name - Name attribute for the hidden input (optional)
 * @var string $placeholder - Placeholder text for the input field
 * @var bool $required - Whether the field is required (default: false)
 * @var string $class - Additional CSS classes for the hidden input
 */

// Default values
$id = $id ?? _raz(uniqid('milkselect', true));
$options = $options ?? [];
$type = $type ?? 'single';
$value = $value ?? null;
$name = $name ?? '';
$placeholder = $placeholder ?? ($type === 'multiple' ? 'Aggiungi valore...' : 'Cerca o seleziona...');
$required = $required ?? false;
$class = $class ?? '';
$floating = $floating ?? false;
$api_url = $api_url ?? null;
$display_value = $display_value ?? null;

// Validate options (only if api_url is not provided)
if (!$api_url && (!is_array($options) || count($options) === 0)) {
    echo '<div class="alert alert-warning">MilkSelect: No options or api_url provided</div>';
    return;
}

// Validate type
if (!in_array($type, ['single', 'multiple'])) {
    $type = 'single';
}

// Detect if array is associative (has custom keys like IDs)
$isAssociative = array_keys($options) !== range(0, count($options) - 1);

// For JavaScript: prepare parallel arrays
$optionValues = $isAssociative ? array_values($options) : $options;
$optionKeys = $isAssociative ? array_keys($options) : null;

// Process preselected value
$processedValue = '';
$displayValue = ''; // What user sees (for JavaScript)
$htmlValue = ''; // What goes in the value attribute (raw, no JSON encoding for single values)

// If using api_url with display_value from belongsTo, use that
if ($api_url && $display_value !== null && $value !== null && $value !== '') {
    // When using API, we have the ID in $value and display text in $display_value
    $displayValue = json_encode($display_value);
    $processedValue = $value;
    $htmlValue = (string)$value;
} else if ($value !== null && $value !== '') {
    if (is_array($value)) {
        // Multiple values - convert to IDs if associative array
        $displayValues = [];
        $savedValues = [];
        foreach ($value as $v) {
            if (is_numeric($v) && $isAssociative && isset($options[$v])) {
                // Value is an ID - get display name
                $displayValues[] = $options[$v];
                $savedValues[] = $v; // Keep ID
            } elseif ($isAssociative) {
                // Value is a display name - find the corresponding ID
                $foundKey = array_search($v, $options, true);
                if ($foundKey !== false) {
                    $displayValues[] = $v;
                    $savedValues[] = $foundKey; // Save the ID
                } else {
                    // Not found in options - use as is
                    $displayValues[] = $v;
                    $savedValues[] = $v;
                }
            } else {
                // Not associative - use value as is
                $displayValues[] = $v;
                $savedValues[] = $v;
            }
        }
        $displayValue = json_encode($displayValues);
        $processedValue = json_encode($savedValues);
        $htmlValue = json_encode($savedValues); // Arrays need JSON in HTML
    } else if ($isAssociative && isset($options[$value])) {
        // Single value with key (numeric or string) - display the text, save the key
        $displayValue = json_encode($options[$value]);
        $processedValue = $value; // Just the key, no JSON
        $htmlValue = (string)$value; // Raw key in HTML attribute
    } else {
        // Simple string value - no associative array
        $displayValue = json_encode($value);
        $processedValue = $value; // Just the string, no JSON
        $htmlValue = (string)$value; // Raw string in HTML attribute
    }
}

// Prepare name attribute
$nameAttr = !empty($name) ? 'name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"' : '';

// Prepare required attribute
$requiredAttr = $required ? 'required' : '';

// Prepare class attribute
$classAttr = !empty($class) ? 'class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';

?>
<!-- MilkSelect Config JSON -->
<script type="application/json" id="milkselect-config-<?php echo $id; ?>">
<?php
$config = [
    'options' => $api_url ? [] : $optionValues,
    'keys' => $api_url ? null : $optionKeys,
    'displayValue' => !empty($displayValue) ? json_decode($displayValue) : null,
    'placeholder' => $placeholder,
    'apiUrl' => $api_url
];
echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG);
?>
</script>

<input
    type="hidden"
    id="<?php echo $id; ?>"
    <?php echo $nameAttr; ?>
    <?php if (!empty($htmlValue)): ?>
        <?php if (is_array($value)): ?>
            value='<?php echo $htmlValue; ?>'
        <?php else: ?>
            value="<?php echo htmlspecialchars($htmlValue, ENT_QUOTES, 'UTF-8'); ?>"
        <?php endif; ?>
    <?php endif; ?>
    <?php echo $requiredAttr; ?>
    <?php echo $classAttr; ?>
    data-select-type="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>"
    data-placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"
    data-floating="<?php echo $floating ? '1' : '0'; ?>"
    data-milkselect-config="milkselect-config-<?php echo $id; ?>"
    <?php if ($api_url): ?>
    data-api-url="<?php echo htmlspecialchars($api_url, ENT_QUOTES, 'UTF-8'); ?>"
    <?php endif; ?>
>
<script>
// Auto-initialize if not dynamically loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        MilkSelect.initFromConfig('<?php echo $id; ?>');
    });
} else {
    MilkSelect.initFromConfig('<?php echo $id; ?>');
}
</script>
