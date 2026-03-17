<?php
namespace Extensions\Projects\Classes;

!defined('MILK_DIR') && die();

/**
 * Naming/conventions for manifest-driven Projects modules.
 */
class ProjectNaming
{
    /**
     * Closure-root field stored in every child table.
     * Points to the root record id of the current manifest branch.
     */
    public static function rootIdField(): string
    {
        return 'root_id';
    }

    /**
     * Human-readable title.
     * Examples:
     * - VisitBaseline -> "Visit Baseline"
     * - visit_baseline -> "Visit Baseline"
     * - visit-baseline -> "Visit Baseline"
     */
    public static function toTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // Split camelCase/PascalCase boundaries.
        $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        // Normalize separators.
        $value = str_replace(['_', '-'], ' ', (string) $value);
        $value = preg_replace('/\\s+/', ' ', (string) $value);

        return ucwords(strtolower(trim((string) $value)));
    }

    public static function toStudlyCase(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);
        $value = ucwords($value);
        return str_replace(' ', '', $value);
    }

    /**
     * URL/action slug (kebab-case).
     */
    public static function toActionSlug(string $name): string
    {
        $slug = preg_replace('/([a-z])([A-Z])/', '$1-$2', $name);
        $slug = str_replace('_', '-', (string) $slug);
        return strtolower($slug);
    }

    /**
     * DB field name (snake_case).
     */
    public static function toSnake(string $name): string
    {
        $snake = preg_replace('/([a-z])([A-Z])/', '$1_$2', $name);
        $snake = str_replace('-', '_', (string) $snake);
        $snake = preg_replace('/_+/', '_', (string) $snake);
        return strtolower(trim((string) $snake, '_'));
    }

    /**
     * Foreign key field for a child form pointing to its parent form.
     *
     * Convention: <parent_form_snake>_id
     * Example: ProjectsExtensionTest -> projects_extension_test_id
     */
    public static function foreignKeyFieldForParentForm(string $parentFormName): string
    {
        $base = self::toSnake($parentFormName);
        return $base === '' ? 'parent_id' : ($base . '_id');
    }

    /**
     * withCount alias for a child form.
     *
     * Convention: <child_form_snake>_count
     */
    public static function withCountAliasForForm(string $formName): string
    {
        $base = self::toSnake($formName);
        return $base === '' ? 'items_count' : ($base . '_count');
    }
}
