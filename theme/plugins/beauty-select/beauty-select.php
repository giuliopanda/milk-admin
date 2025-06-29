<?php
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Select plugin
 * Non ha il name perché se si usa il plugin allora si deve usare getValue(containerId) per prendere il valore 
 */
$options = $options ?? [];
$id = $id ??  _raz(uniqid('select_', true));
$isMultiple = $isMultiple ?? false;
$showToggleButton = $showToggleButton ?? false;
$onChange = $onChange ?? 'null';
$label = $label ?? '';
$floating = $floating ?? true;
$selectValue = $value ?? '';
if (!is_array($options) || count($options) == 0) return;
//
/*
 converto gli options come in esempio
 da 'options'=>['1'=>'One', '2' => 'Two']
 const simpleOptions = [
            { value: '1', text: 'Option 1' },
            { value: '2', text: 'Option 2' },
            { value: '3', text: 'Option 3' },
            { value: '4', text: 'Option 4' }
        ];

se 'options'=>['Fruit' => ['1'=>'One', '2' => 'Two'], 'Vegetables' => ['1'=>'One', '2' => 'Two']]
lo converto in 
  const groupedOptions = [
            { value: 'fruit1', text: 'Apple', group: 'Fruits' },
            { value: 'fruit2', text: 'Banana', group: 'Fruits' },
            { value: 'fruit3', text: 'Orange', group: 'Fruits' },
            { value: 'veg1', text: 'Carrot', group: 'Vegetables' },
            { value: 'veg2', text: 'Broccoli', group: 'Vegetables' },
            { value: 'veg3', text: 'Spinach', group: 'Vegetables' }
        ];

*/
if (is_array($options) && count($options) > 0) {
    $newOptions = [];
    foreach ($options as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $newOptions[] = [
                    'value' => $key . '_' . $k,
                    'text' => $v,
                    'group' => $key
                ];
            }
        } else {
            $newOptions[] = [
                'value' => $key,
                'text' => $value
            ];
        }
    }
    $options = $newOptions;
}
?>

<div id="<?php echo _raz($id); ?>"></div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.beautySelect.createSelect({
            containerId: '<?php echo _raz($id); ?>',
            isMultiple: <?php _pjs($isMultiple ? true : false); ?>,
            selectOptions: <?php _pjs($options); ?>,
            showToggleButton: <?php _pjs($showToggleButton ? true : false); ?>,
            floating: <?php _pjs($floating ? true : false); ?>,
            labelText: <?php _pjs($label); ?>,
            defaultValue: <?php _pjs($selectValue); ?>,
            onChange: (value) => <?php echo $onChange; ?>
        });
    });

</script>
