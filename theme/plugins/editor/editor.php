<?php
!defined('MILK_DIR') && die(); // Avoid direct access
/**
 * Trix Editor plugin
 * Plugin per creare editor WYSIWYG usando Trix con funzionalità toggle
 */

$id = $id ?? _raz(uniqid('trix_', true));
$value = $value ?? '';
$placeholder = $placeholder ?? '';
$onChange = $onChange ?? 'null';
$onBlur = $onBlur ?? 'null';
$onFocus = $onFocus ?? 'null';
$onFileAccept = $onFileAccept ?? 'null';
$onAttachmentAdd = $onAttachmentAdd ?? 'null';
$onAttachmentRemove = $onAttachmentRemove ?? 'null';
$toolbar = $toolbar ?? true;
$autofocus = $autofocus ?? false;
$label = $label ?? '';
$height = $height ?? '200px';
$name = $name ?? '';
$enableToggle = $enableToggle ?? true;

// Sanitizza il valore per evitare problemi con le virgolette
$value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<div class="form-group">
    <?php if ($label): ?>
    <label for="<?php echo _raz($id); ?>"><?php echo $label; ?></label>
    <?php endif; ?>
    <div id="<?php echo _raz($id); ?>"></div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.editor.createEditor({
            containerId: '<?php echo _raz($id); ?>',
            value: <?php _pjs(html_entity_decode($value, ENT_QUOTES, 'UTF-8')); ?>,
            placeholder: <?php _pjs($placeholder); ?>,
            toolbar: <?php _pjs($toolbar ? 'true' : 'false'); ?>,
            autofocus: <?php _pjs($autofocus ? 'true' : 'false'); ?>,
            height: <?php _pjs($height); ?>,
            enableToggle: <?php _pjs($enableToggle ? 'true' : 'false'); ?>,
            onChange: <?php echo $onChange !== 'null' ? $onChange : 'null'; ?>,
            onBlur: <?php echo $onBlur !== 'null' ? $onBlur : 'null'; ?>,
            onFocus: <?php echo $onFocus !== 'null' ? $onFocus : 'null'; ?>,
            onFileAccept: <?php echo $onFileAccept !== 'null' ? $onFileAccept : 'null'; ?>,
            onAttachmentAdd: <?php echo $onAttachmentAdd !== 'null' ? $onAttachmentAdd : 'null'; ?>,
            onAttachmentRemove: <?php echo $onAttachmentRemove !== 'null' ? $onAttachmentRemove : 'null'; ?>
        });

        <?php if ($name): ?>
        // Se è specificato un name, lo aggiungiamo all'input nascosto
        setTimeout(function() {
            const instance = window.editor.instances.get('<?php echo _raz($id); ?>');
            if (instance && instance.hiddenInput) {
                instance.hiddenInput.setAttribute('name', <?php _pjs($name); ?>);
            }
        }, 100);
        <?php endif; ?>
    });
</script>