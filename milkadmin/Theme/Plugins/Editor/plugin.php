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
<!-- Editor Config JSON -->
<script type="application/json" id="editor-config-<?php echo _raz($id); ?>">
<?php
$config = [
    'containerId' => $id,
    'value' => html_entity_decode($value, ENT_QUOTES, 'UTF-8'),
    'placeholder' => $placeholder,
    'toolbar' => $toolbar,
    'autofocus' => $autofocus,
    'height' => $height,
    'enableToggle' => $enableToggle,
    'name' => $name,
    'onChange' => $onChange !== 'null' ? $onChange : null,
    'onBlur' => $onBlur !== 'null' ? $onBlur : null,
    'onFocus' => $onFocus !== 'null' ? $onFocus : null,
    'onFileAccept' => $onFileAccept !== 'null' ? $onFileAccept : null,
    'onAttachmentAdd' => $onAttachmentAdd !== 'null' ? $onAttachmentAdd : null,
    'onAttachmentRemove' => $onAttachmentRemove !== 'null' ? $onAttachmentRemove : null
];
echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG);
?>
</script>

<div class="form-group">
    <?php if ($label): ?>
    <label for="<?php echo _raz($id); ?>"><?php echo $label; ?></label>
    <?php endif; ?>
    <div id="<?php echo _raz($id); ?>" data-editor-config="editor-config-<?php echo _raz($id); ?>"></div>
</div>
<script>
    // Auto-initialize if not dynamically loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            TrixEditorManager.initFromConfig('<?php echo _raz($id); ?>');
        });
    } else {
        TrixEditorManager.initFromConfig('<?php echo _raz($id); ?>');
    }
</script>