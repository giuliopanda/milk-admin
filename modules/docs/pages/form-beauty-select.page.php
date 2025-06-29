<?php
namespace Modules\docs;
use MilkCore\Get;
/**
 * @title Beauty Select
 * @category Forms
 * @order 50
 * @tags 
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<style>
    .demo-section {
    margin-bottom: 2rem;
    padding: 1rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
</style>
<div class="bg-white p-4">
    <h1>Beauty Select Plugin</h1>
  
    <p>To manage selects with search functionality.</p>
    <p class="alert alert-warning">Warning: when using the plugin for the select, you must get the selected value through JavaScript window.beautySelect.getValue('selectID'), otherwise it won't save on form submit!</p>
    <p class="alert alert-danger">I haven't developed the required validation yet</p>

        <h3>1. Simple Select</h3>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::theme_plugin('beauty-select', [
        'id'=>'simpleSelect', 
        'options'=>['1'=>'One', '2' => 'Two'], 
        'label'=>'Simple TomSelect',
        'value' => 2
    ] ); ?&gt;</code></pre>
        <div class="demo-section col-xl-6">
            <?php echo Get::theme_plugin('beauty-select',['id'=>'simpleSelect', 'options'=>['1'=>'One', '2' => 'Two'], 'label'=>'Simple TomSelect', 'value'=>2] ); ?>
        </div>
       
        <h3>2. Group Select</h3>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::theme_plugin('beauty-select',['id'=>'groupSelect', 'floating'=>false, 'options'=>['Fruit' => ['fruit1'=>'Apple', 'fruit2' => 'Banana', 'fruit3' => 'Orange'], 'Vegetables' => ['veg1'=>'Carrot', 'veg2' => 'Broccoli', 'veg3' => 'Spinach'] ] ] ); ?&gt;</code></pre>
        <div class="demo-section col-xl-6">
            <?php echo Get::theme_plugin('beauty-select',['id'=>'groupSelect', 'floating'=>false, 'options'=>['Fruit' => ['fruit1'=>'Apple', 'fruit2' => 'Banana', 'fruit3' => 'Orange'], 'Vegetables' => ['veg1'=>'Carrot', 'veg2' => 'Broccoli', 'veg3' => 'Spinach'] ] ] ); ?>
        </div>


        <h3>3. isMultiple</h3>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo 
Get::theme_plugin('beauty-select', ['id'=>'state', 'label'=>'multiple select', 'options'=>['franch' => 'franch', 'spain' => 'spain', 'italy' => 'italy'], 'isMultiple'=>true]); ?&gt;</code></pre>
        <div class="demo-section col-xl-6">
            <?php echo Get::theme_plugin('beauty-select', ['id'=>'state', 'label'=>'multiple select', 'options'=>['franch' => 'franch', 'spain' => 'spain', 'italy' => 'italy'], 'isMultiple'=>true]); ?>
        </div>

        <h4>4. showToggleButton</h4>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::theme_plugin('beauty-select',  ['id'=>'state', 'label'=>'showToggleButton select',  'options'=>['franch' => 'franch', 'spain' => 'spain', 'italy' => 'italy'],  'showToggleButton'=>true]); ?&gt;</code></pre>
        <div class="demo-section col-xl-6">
            <?php echo Get::theme_plugin('beauty-select', ['id'=>'state2', 'floating'=>false, 'label'=>'showToggleButton select', 'options'=>['franch' => 'franch', 'spain' => 'spain', 'italy' => 'italy'], 'showToggleButton'=>true]); ?>
        </div>

        <h4>5. getValue</h4>
        <div id="showvalues"></div>
        <div class="btn btn-primary" onclick="displayValues()">Show Values</div>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-js">const displayValues = () =&gt; {
    const showValuesDiv = document.getElementById(&apos;showvalues&apos;);
    showValuesDiv.innerHTML = &apos;&apos;;
    [&apos;simpleSelect&apos;, &apos;groupSelect&apos;, &apos;state&apos;, &apos;state2&apos;].forEach(id =&gt; {
        // Create an element to show the value
        const valueElement = document.createElement(&apos;div&apos;);
        valueElement.textContent = &grave;${id}: &grave;+window.beautySelect.getValue(id);
        // Add the element to the div
        showValuesDiv.appendChild(valueElement);
    });
};</code></pre>
        <script>
            const displayValues = () => {
                const showValuesDiv = document.getElementById('showvalues');
                showValuesDiv.innerHTML = '';
                ['simpleSelect', 'groupSelect', 'state', 'state2'].forEach(id => {
                    // Create an element to show the value
                    const valueElement = document.createElement('div');
                    valueElement.textContent = `${id}: `+window.beautySelect.getValue(id);
                    // Add the element to the div
                    showValuesDiv.appendChild(valueElement);
                });
            };  
        </script>

    <h4>6. onChange</h4>
    <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-php">&lt;?php echo Get::theme_plugin('beauty-select',['id'=>'simpleSelectChange', 'label'=>'onchange select', 'options'=>['1'=>'One', '2' => 'Two']  'onChange'=>'showInDiv(window.beautySelect.getValue("simpleSelectChange"))'] ); ?&gt;</code></pre>
        <div class="demo-section col-xl-6">
            <?php echo Get::theme_plugin('beauty-select',['id'=>'simpleSelectChange',  'label'=>'onchange select', 'options'=>['1'=>'One', '2' => 'Two'], 'onChange'=>'showInDiv(window.beautySelect.getValue("simpleSelectChange"))'] ); ?>
        
        <div id="showInDiv" class="mt-4"></div>
        <script>
            function showInDiv(str) {
                document.getElementById('showInDiv').innerHTML = 'Value: '+str;
            }
        </script>
        </div>

        <h2 class="mt-5">JavaScript Documentation</h2>
        <p>The beauty-select plugin exposes its functionality through the global <code>window.beautySelect</code> object.</p>

        <h3>Methods</h3>
        
        <h4 class="mt-3">createSelect(options)</h4>
        <p>Creates a new select with TomSelect. Accepts a configuration object with the following properties:</p>
        
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th>Property</th>
                    <th>Type</th>
                    <th>Default</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>containerId</td>
                    <td>string</td>
                    <td>required</td>
                    <td>ID of the container where the select will be created</td>
                </tr>
                <tr>
                    <td>isMultiple</td>
                    <td>boolean</td>
                    <td>false</td>
                    <td>Enables multiple selection</td>
                </tr>
                <tr>
                    <td>selectOptions</td>
                    <td>array</td>
                    <td>[]</td>
                    <td>Array of select options</td>
                </tr>
                <tr>
                    <td>showToggleButton</td>
                    <td>boolean</td>
                    <td>false</td>
                    <td>Shows the button to select/deselect all</td>
                </tr>
                <tr>
                    <td>floating</td>
                    <td>boolean</td>
                    <td>true</td>
                    <td>Enables floating label</td>
                </tr>
                <tr>
                    <td>labelText</td>
                    <td>string</td>
                    <td>''</td>
                    <td>Select label text</td>
                </tr>
                <tr>
                    <td>value</td>
                    <td>string|array</td>
                    <td>''</td>
                    <td>Default value</td>
                </tr>
                <tr>
                    <td>onChange</td>
                    <td>function</td>
                    <td>null</td>
                    <td>Callback executed when the value changes</td>
                </tr>
            </tbody>
        </table>

        <p class="mt-3">Usage example:</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">window.beautySelect.createSelect({
    containerId: 'mySelectId',
    isMultiple: true,
    selectOptions: [
        { value: '1', text: 'Option 1' },
        { value: '2', text: 'Option 2' }
    ],
    showToggleButton: true,
    floating: true,
    labelText: 'Select an option',
    onChange: (value) => {
        console.log('Selected value:', value);
    }
});</code></pre>

        <p class="mt-3">Example with grouped options:</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">window.beautySelect.createSelect({
    containerId: 'groupedSelect',
    selectOptions: {
        'Group 1': {
            'opt1': 'Option 1',
            'opt2': 'Option 2'
        },
        'Group 2': {
            'opt3': 'Option 3',
            'opt4': 'Option 4'
        }
    },
    value: 'opt1'
    labelText: 'Select an option'
});</code></pre>

        <h4 class="mt-3">getValue(containerId)</h4>
        <p>Gets the current value of a select.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">const value = window.beautySelect.getValue('mySelectId');</code></pre>

        <h4 class="mt-3">setValue(containerId, value)</h4>
        <p>Sets the value of a select.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// For single select
window.beautySelect.setValue('mySelectId', 'value1');

// For multiple select
window.beautySelect.setValue('mySelectId', ['value1', 'value2']);</code></pre>

        <h4 class="mt-3">updateOptions(containerId, options)</h4>
        <p>Updates the options of an existing select.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">window.beautySelect.updateOptions('mySelectId', [
    { value: 'new1', text: 'New Option 1', group: 'Group 1' },
    { value: 'new2', text: 'New Option 2' }
]);</code></pre>

        <h4 class="mt-3">removeSelect(containerId)</h4>
        <p>Removes a select and cleans up its instances.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">window.beautySelect.removeSelect('mySelectId');</code></pre>

        <h4 class="mt-3">removeAll()</h4>
        <p>Removes all selects managed by the plugin.</p>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">window.beautySelect.removeAll();</code></pre>

        <h3 class="mt-3">Complete Example</h3>
        <pre class="pre-scrollable border p-2 text-bg-gray"><code class="language-javascript">// Create select through PHP as shown in previous examples

// Get value
const value = window.beautySelect.getValue('mySelectId');

// Update options
window.beautySelect.updateOptions('mySelectId', [
    { value: 'new1', text: 'New Option', group: 'New Group' }
]);

// Set up change handler
document.getElementById('myButton').onclick = () => {
    const currentValue = window.beautySelect.getValue('mySelectId');
    console.log('Current value:', currentValue);
};</code></pre>
</div>