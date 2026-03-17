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
if (!is_string($id) || $id === '') {
    $id = _raz(uniqid('milkselect', true));
}
if (!is_array($options)) {
    $options = [];
}
if (!is_string($type) || $type === '') {
    $type = 'single';
}
$value = $value ?? null;
if (!is_string($name)) {
    $name = '';
}
if (!is_string($placeholder) || $placeholder === '') {
    $placeholder = ($type === 'multiple' ? 'Aggiungi valore...' : 'Cerca o seleziona...');
}
$required = isset($required) ? (bool) $required : false;
$class = (isset($class) && is_string($class)) ? $class : '';
$floating = isset($floating) ? (bool) $floating : false;
$api_url = $api_url ?? null;
$display_value = $display_value ?? null;
$readonly = isset($readonly) ? (bool) $readonly : false;

// Validate options (only if api_url is not provided)
if (!$api_url && count($options) === 0) {
    echo '<div class="alert alert-warning">MilkSelect: No options or api_url provided</div>';
    return;
}

// Validate type
if (!in_array($type, ['single', 'multiple'])) {
    $type = 'single';
}

// Detect option formats
$isObjectOptions = false;
if (count($options) > 0) {
    $firstOption = reset($options);
    $isObjectOptions = is_array($firstOption) &&
        (array_key_exists('text', $firstOption) || array_key_exists('value', $firstOption) || array_key_exists('group', $firstOption));
}
$isAssociative = !$isObjectOptions && array_keys($options) !== range(0, count($options) - 1);

// For JavaScript: prepare normalized options
$optionValues = [];
$optionKeys = null;
$valueToTextMap = [];
$textToValueMap = [];

if ($isObjectOptions) {
    foreach ($options as $option) {
        if (!is_array($option)) {
            continue;
        }

        $rawText = $option['text'] ?? $option['label'] ?? ($option['value'] ?? null);
        if ($rawText === null || $rawText === '') {
            continue;
        }

        $text = (string)$rawText;
        $rawValue = $option['value'] ?? $text;
        $valueString = (string)$rawValue;
        $group = isset($option['group']) && $option['group'] !== '' ? (string)$option['group'] : null;

        $normalizedOption = [
            'value' => $valueString,
            'text' => $text
        ];
        if ($group !== null) {
            $normalizedOption['group'] = $group;
        }
        $optionValues[] = $normalizedOption;

        if (!array_key_exists($valueString, $valueToTextMap)) {
            $valueToTextMap[$valueString] = $text;
        }
        if (!array_key_exists($text, $textToValueMap)) {
            $textToValueMap[$text] = $valueString;
        }
    }
} else {
    $optionValues = $isAssociative ? array_values($options) : $options;
    $optionKeys = $isAssociative ? array_keys($options) : null;
}

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
            if ($isObjectOptions) {
                $lookupValue = (string)$v;
                if (isset($valueToTextMap[$lookupValue])) {
                    $displayValues[] = $valueToTextMap[$lookupValue];
                    $savedValues[] = $lookupValue;
                } elseif (isset($textToValueMap[$lookupValue])) {
                    $displayValues[] = $lookupValue;
                    $savedValues[] = $textToValueMap[$lookupValue];
                } else {
                    // Not found in options - use as is
                    $displayValues[] = $lookupValue;
                    $savedValues[] = $lookupValue;
                }
            } elseif (is_numeric($v) && $isAssociative && isset($options[$v])) {
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
    } else if ($isObjectOptions) {
        $lookupValue = (string)$value;
        if (isset($valueToTextMap[$lookupValue])) {
            // Single value using option value
            $displayValue = json_encode($valueToTextMap[$lookupValue]);
            $processedValue = $lookupValue;
            $htmlValue = $lookupValue;
        } else if (isset($textToValueMap[$lookupValue])) {
            // Single value passed as display text
            $displayValue = json_encode($lookupValue);
            $processedValue = $textToValueMap[$lookupValue];
            $htmlValue = (string)$processedValue;
        } else {
            // Fallback
            $displayValue = json_encode($lookupValue);
            $processedValue = $lookupValue;
            $htmlValue = $lookupValue;
        }
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
   <?php echo $readonly ? ' data-readonly="1"' : ''; ?>
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
