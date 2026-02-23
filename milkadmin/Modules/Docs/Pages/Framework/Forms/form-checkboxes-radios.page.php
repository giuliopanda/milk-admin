<?php
namespace Modules\Docs\Pages;
use App\{Get, Form};
/**
 * @title Checkboxes, Radios & Switches
 * @guide framework
 * @order 25
 * @tags form, checkbox, radio, switch, inline, vertical, horizontal
 */
!defined('MILK_DIR') && die(); // Avoid direct access
?>
<div class="bg-white p-4">

    <h1>Checkboxes, Radio &amp; Switch</h1>
    <p>This page shows all rendering variants for checkboxes, radio buttons, and switches, focusing on
       <strong>vertical</strong> (default) and <strong>horizontal</strong> (inline) layouts.</p>

    <div class="alert alert-info">
        <strong>Key parameter: <code>$inline</code></strong><br>
        Both <code>Form::checkboxes()</code> and <code>Form::radios()</code> accept a boolean <code>$inline</code>
        as the 4th argument:<br>
        &bull; <code>false</code> (default) → <strong>vertical</strong> layout (one option per row)<br>
        &bull; <code>true</code> → <strong>horizontal</strong> inline layout (adds Bootstrap's
        <code>form-check-inline</code> class to each item)<br><br>
        <strong>Note:</strong> the native horizontal mode places the group label above the inline options.
        To get <strong>label on the left + options indented to the right</strong>, use
        <code>'label-position' => 'left'</code> (see dedicated section below).
    </div>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - VERTICAL (default)
    ============================================================ -->
    <h2>Checkboxes – Vertical (default)</h2>
    <p><code>$inline = false</code> — options stack vertically below the label.</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-6">
            <?php Form::checkboxes(
                'colori_v',
                ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
                ['r', 'b'],
                false,
                ['label' => 'Favourite colours']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'colori_v',
    ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
    ['r', 'b'],   // selected values
    false,         // $inline = false → vertical
    ['label' => 'Favourite colours']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - MULTI COLUMN
    ============================================================ -->
    <h2>Checkboxes – Multi-column (2, 3 or 4)</h2>
    <p>With <code>'columns' => N</code> (2, 3 or 4) in <code>$options_group</code> the options are
       distributed across multiple columns using Bootstrap <code>row row-cols-N</code>.
       Only works with <code>$inline = false</code>.</p>

    <div class="bg-light p-3 mb-2">
        <h6 class="text-muted">2 columns</h6>
        <div class="form-group">
            <?php Form::checkboxes(
                'giorni_2col',
                ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],
                ['mon','wed'],
                false,
                ['label' => 'Days', 'columns' => 2]
            ); ?>
        </div>
        <h6 class="text-muted mt-3">3 columns</h6>
        <div class="form-group">
            <?php Form::checkboxes(
                'giorni_3col',
                ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],
                ['fri','sat','sun'],
                false,
                ['label' => 'Days', 'columns' => 3]
            ); ?>
        </div>
        <h6 class="text-muted mt-3">4 columns</h6>
        <div class="form-group">
            <?php Form::checkboxes(
                'giorni_4col',
                ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],
                [],
                false,
                ['label' => 'Days', 'columns' => 4]
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">// 2 columns
Form::checkboxes('days', [...], ['mon','wed'], false, ['label'=>'Days', 'columns'=>2]);

// 3 columns
Form::checkboxes('days', [...], ['fri','sat'], false, ['label'=>'Days', 'columns'=>3]);

// 4 columns
Form::checkboxes('days', [...], [], false, ['label'=>'Days', 'columns'=>4]);</code></pre>

    <h3 class="mt-4">Multi-column + label on the left</h3>
    <div class="bg-light p-3 mb-2">
        <div class="form-group">
            <?php Form::checkboxes(
                'giorni_left3',
                ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],
                ['mon','fri'],
                false,
                ['label' => 'Days', 'columns' => 3, 'label-position' => 'left', 'label-width' => '6rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes('days', [...], ['mon','fri'], false, [
    'label'          => 'Days',
    'columns'        => 3,
    'label-position' => 'left',
    'label-width'    => '6rem',
]);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - HORIZONTAL (native Bootstrap inline)
    ============================================================ -->
    <h2>Checkboxes – Horizontal / Native inline</h2>
    <p><code>$inline = true</code> — Bootstrap adds <code>form-check-inline</code>; the label appears above
       and options are laid out on the same row.</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-8">
            <?php Form::checkboxes(
                'colori_h',
                ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
                ['g'],
                true,
                ['label' => 'Favourite colours']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'colori_h',
    ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
    ['g'],
    true,          // $inline = true → horizontal
    ['label' => 'Favourite colours']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - HORIZONTAL with left label (CSS workaround)
    ============================================================ -->
    <h2>Checkboxes – Horizontal with left label (workaround)</h2>
    <p>A quick workaround using <code>'form-group-class' => 'd-flex align-items-baseline gap-3'</code>.
       The label becomes the first flex child and the inline options align to its right.</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::checkboxes(
                'colori_hl',
                ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue', 'y' => 'Yellow'],
                ['r'],
                true,
                [
                    'label'            => 'Favourite colours',
                    'form-group-class' => 'd-flex align-items-baseline gap-3',
                ]
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'colori_hl',
    ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue', 'y' => 'Yellow'],
    ['r'],
    true,
    [
        'label'            => 'Favourite colours',
        'form-group-class' => 'd-flex align-items-baseline gap-3',
        // The label is rendered as the first flex child;
        // inline options align to its right
    ]
);</code></pre>

    <div class="alert alert-warning mt-2">
        This workaround works but without a fixed <code>min-width</code> on the label the options may not
        always start at the same horizontal position. Use the native <code>'label-position' => 'left'</code>
        option shown in the next section instead.
    </div>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - LEFT LABEL (native option)
    ============================================================ -->
    <h2>Checkboxes – Left label, vertical options <small class="text-success fs-6">(native)</small></h2>
    <p>With <code>'label-position' => 'left'</code> the label is pinned to the left with a configurable
       <code>min-width</code> via <code>'label-width'</code> (default <code>8rem</code>).</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-7">
            <?php Form::checkboxes(
                'colori_lv',
                ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
                ['r', 'b'],
                false,
                ['label' => 'Colours', 'label-position' => 'left', 'label-width' => '7rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'colori_lv',
    ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue'],
    ['r', 'b'],
    false,
    ['label' => 'Colours', 'label-position' => 'left', 'label-width' => '7rem']
);</code></pre>

    <h2 class="mt-4">Checkboxes – Left label, horizontal options <small class="text-success fs-6">(native)</small></h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::checkboxes(
                'colori_lh',
                ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue', 'y' => 'Yellow'],
                ['r'],
                true,
                ['label' => 'Colours', 'label-position' => 'left', 'label-width' => '7rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'colori_lh',
    ['r' => 'Red', 'g' => 'Green', 'b' => 'Blue', 'y' => 'Yellow'],
    ['r'],
    true,          // $inline = true → horizontal
    ['label' => 'Colours', 'label-position' => 'left', 'label-width' => '7rem']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         RADIO - MULTI COLUMN
    ============================================================ -->
    <h2>Radio – Multi-column (2, 3 or 4)</h2>
    <p>Same mechanism as checkboxes: <code>'columns' => N</code> in <code>$options_group</code>.</p>

    <div class="bg-light p-3 mb-2">
        <h6 class="text-muted">2 columns</h6>
        <div class="form-group">
            <?php Form::radios(
                'mesi_2col',
                ['jan'=>'January','feb'=>'February','mar'=>'March','apr'=>'April','may'=>'May','jun'=>'June'],
                'mar',
                false,
                ['label' => 'Month', 'columns' => 2]
            ); ?>
        </div>
        <h6 class="text-muted mt-3">3 columns</h6>
        <div class="form-group">
            <?php Form::radios(
                'mesi_3col',
                ['jan'=>'January','feb'=>'February','mar'=>'March','apr'=>'April','may'=>'May','jun'=>'June'],
                'apr',
                false,
                ['label' => 'Month', 'columns' => 3]
            ); ?>
        </div>
        <h6 class="text-muted mt-3">3 columns + label on the left</h6>
        <div class="form-group">
            <?php Form::radios(
                'mesi_left3',
                ['jan'=>'January','feb'=>'February','mar'=>'March','apr'=>'April','may'=>'May','jun'=>'June'],
                'jun',
                false,
                ['label' => 'Month', 'columns' => 3, 'label-position' => 'left', 'label-width' => '5rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">// 3 columns
Form::radios('month', [...], 'apr', false, ['label'=>'Month', 'columns'=>3]);

// 3 columns + label on the left
Form::radios('month', [...], 'jun', false, [
    'label'          => 'Month',
    'columns'        => 3,
    'label-position' => 'left',
    'label-width'    => '5rem',
]);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         RADIO - VERTICAL (default)
    ============================================================ -->
    <h2>Radio – Vertical (default)</h2>
    <p><code>$inline = false</code></p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-6">
            <?php Form::radios(
                'taglia_v',
                ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
                'm',
                false,
                ['label' => 'Size']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::radios(
    'taglia_v',
    ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
    'm',
    false,         // $inline = false → vertical
    ['label' => 'Size']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         RADIO - HORIZONTAL (native inline)
    ============================================================ -->
    <h2>Radio – Horizontal / Native inline</h2>
    <p><code>$inline = true</code></p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-8">
            <?php Form::radios(
                'taglia_h',
                ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
                'l',
                true,
                ['label' => 'Size']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::radios(
    'taglia_h',
    ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
    'l',
    true,          // $inline = true → horizontal
    ['label' => 'Size']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         RADIO - HORIZONTAL with left label (workaround)
    ============================================================ -->
    <h2>Radio – Horizontal with left label (workaround)</h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::radios(
                'taglia_hl',
                ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
                's',
                true,
                [
                    'label'            => 'Size',
                    'form-group-class' => 'd-flex align-items-baseline gap-3',
                ]
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::radios(
    'taglia_hl',
    ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
    's',
    true,
    [
        'label'            => 'Size',
        'form-group-class' => 'd-flex align-items-baseline gap-3',
    ]
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         RADIO - LEFT LABEL (native option)
    ============================================================ -->
    <h2>Radio – Left label, vertical options <small class="text-success fs-6">(native)</small></h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-7">
            <?php Form::radios(
                'taglia_lv',
                ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
                'm',
                false,
                ['label' => 'Size', 'label-position' => 'left', 'label-width' => '6rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::radios(
    'taglia_lv',
    ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
    'm',
    false,
    ['label' => 'Size', 'label-position' => 'left', 'label-width' => '6rem']
);</code></pre>

    <h2 class="mt-4">Radio – Left label, horizontal options <small class="text-success fs-6">(native)</small></h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::radios(
                'taglia_lh',
                ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
                'l',
                true,
                ['label' => 'Size', 'label-position' => 'left', 'label-width' => '6rem']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::radios(
    'taglia_lh',
    ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large', 'xl' => 'X-Large'],
    'l',
    true,          // $inline = true → horizontal
    ['label' => 'Size', 'label-position' => 'left', 'label-width' => '6rem']
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         SWITCH - single
    ============================================================ -->
    <h2>Switch – Single</h2>
    <p>A checkbox rendered as a toggle switch: wrap it in a <code>div.form-check.form-switch</code>.</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-6">
            <div class="form-check form-switch">
                <?php Form::checkbox('notifications', 'Enable notifications', '1', true); ?>
            </div>
            <div class="form-check form-switch">
                <?php Form::checkbox('newsletter', 'Subscribe to newsletter', '1', false); ?>
            </div>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">&lt;div class="form-check form-switch"&gt;
    &lt;?php Form::checkbox('notifications', 'Enable notifications', '1', true); ?&gt;
&lt;/div&gt;
&lt;div class="form-check form-switch"&gt;
    &lt;?php Form::checkbox('newsletter', 'Subscribe to newsletter', '1', false); ?&gt;
&lt;/div&gt;</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         SWITCH - group via checkboxes()
    ============================================================ -->
    <h2>Switch – Group via <code>Form::checkboxes()</code></h2>
    <p>Passing <code>'form-check-class' => 'form-switch'</code> in <code>$options_group</code>
       renders every checkbox in the group as a switch.</p>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-6">
            <?php Form::checkboxes(
                'permissions',
                ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
                ['read', 'write'],
                false,
                ['label' => 'Permissions', 'form-check-class' => 'form-switch']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'permissions',
    ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
    ['read', 'write'],
    false,
    [
        'label'            => 'Permissions',
        'form-check-class' => 'form-switch',
    ]
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         SWITCH - inline group (horizontal)
    ============================================================ -->
    <h2>Switch – Inline group (horizontal)</h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::checkboxes(
                'permissions_h',
                ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
                ['read'],
                true,
                ['label' => 'Permissions', 'form-check-class' => 'form-switch']
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'permissions_h',
    ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
    ['read'],
    true,          // $inline = true
    [
        'label'            => 'Permissions',
        'form-check-class' => 'form-switch',
    ]
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         SWITCH - inline group with left label
    ============================================================ -->
    <h2>Switch – Inline with left label</h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-9">
            <?php Form::checkboxes(
                'permissions_hl',
                ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
                ['read', 'delete'],
                true,
                [
                    'label'            => 'Permissions',
                    'form-check-class' => 'form-switch',
                    'label-position'   => 'left',
                    'label-width'      => '8rem',
                ]
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'permissions_hl',
    ['read' => 'Read', 'write' => 'Write', 'delete' => 'Delete'],
    ['read', 'delete'],
    true,
    [
        'label'            => 'Permissions',
        'form-check-class' => 'form-switch',
        'label-position'   => 'left',
        'label-width'      => '8rem',
    ]
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         CHECKBOX - validation
    ============================================================ -->
    <h2>Checkboxes – With validation</h2>

    <div class="bg-light p-3 mb-2">
        <div class="form-group col-xl-6">
            <?php Form::checkboxes(
                'languages',
                ['it' => 'Italian', 'en' => 'English', 'de' => 'German'],
                [],
                false,
                ['label' => 'Known languages', 'invalid-feedback' => 'Please select at least one language'],
                ['required' => true]
            ); ?>
        </div>
    </div>
    <pre class="pre-scrollable border p-2"><code class="language-php">Form::checkboxes(
    'languages',
    ['it' => 'Italian', 'en' => 'English', 'de' => 'German'],
    [],
    false,
    ['label' => 'Known languages', 'invalid-feedback' => 'Please select at least one language'],
    ['required' => true]   // $options_field
);</code></pre>

    <hr class="my-4">

    <!-- ============================================================
         OPTIONS REFERENCE
    ============================================================ -->
    <h2>Complete <code>$options_group</code> reference</h2>

    <div class="alert alert-info">
        <table class="table table-sm mb-0">
            <thead><tr><th>Key</th><th>Type</th><th>Effect</th></tr></thead>
            <tbody>
                <tr><td><code>label</code></td><td>string</td><td>Group label text</td></tr>
                <tr><td><code>label-position</code></td><td><code>'left'</code></td><td>Pins the label to the left; options are indented to the right</td></tr>
                <tr><td><code>label-width</code></td><td>CSS string</td><td><code>min-width</code> of the label in <code>left</code> mode (default: <code>8rem</code>)</td></tr>
                <tr><td><code>columns</code></td><td>int 2–4</td><td>Distributes options across 2, 3 or 4 columns (only with <code>$inline = false</code>)</td></tr>
                <tr><td><code>form-check-class</code></td><td>string</td><td>Extra class on each <code>div.form-check</code> (e.g. <code>form-switch</code>)</td></tr>
                <tr><td><code>form-group-class</code></td><td>string</td><td>Extra class on the outer <code>div.form-group</code> wrapper</td></tr>
                <tr><td><code>class</code></td><td>string</td><td>Alias for <code>form-group-class</code></td></tr>
                <tr><td><code>invalid-feedback</code></td><td>string</td><td>Validation error message (appended after the last item)</td></tr>
            </tbody>
        </table>
    </div>

</div>
