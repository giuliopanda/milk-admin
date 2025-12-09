<?php

namespace Builders\Traits\GetDataBuilder;

use App\{Get, Route};
use Builders\Exceptions\BuilderException;

!defined('MILK_DIR') && die();

/**
 * FieldFirstTrait - Field-first style API methods
 *
 * Provides fluent interface: ->field('name')->label('...')->type('...')
 */
trait FieldFirstTrait
{
    /**
     * Select a field for configuration
     */
    public function field(string $key): static
    {
        $this->columns->setCurrentField($key);
        return $this;
    }

    /**
     * Set label for current field
     */
    public function label(string $label): static
    {
        $key = $this->columns->requireCurrentField('label');
        $this->columns->setLabel($key, $label);
        return $this;
    }

    /**
     * Set type for current field
     */
    public function type(string $type): static
    {
        $key = $this->columns->requireCurrentField('type');
        $this->columns->setType($key, $type);
        return $this;
    }

    /**
     * Set options for current field (select type)
     */
    public function options(array $options): static
    {
        $key = $this->columns->requireCurrentField('options');
        $this->columns->setOptions($key, $options);
        return $this;
    }

    /**
     * Set custom formatter function for current field
     */
    public function fn(callable $fn): static
    {
        $key = $this->columns->requireCurrentField('fn');
        $this->columns->setFunction($key, $fn);
        return $this;
    }

    /**
     * Convert current field to a clickable link
     */
    public function link(string $link, array $options = []): static
    {
        $key = $this->columns->requireCurrentField('link');

        // Auto-add fetch attribute in fetch mode
        if ($this->context->isFetchMode() && !isset($options['data-fetch'])) {
            $options['data-fetch'] = 'post';
        }

        $this->columns->setType($key, 'html');
        $this->columns->setFunction($key, $this->createLinkFormatter($key, $link, $options));

        return $this;
    }

    /**
     * Convert current field to file download links
     */
    public function file(array $options = []): static
    {
        $key = $this->columns->requireCurrentField('file');

        $this->columns->setType($key, 'html');
        $this->columns->setFunction($key, $this->createFileFormatter($key, $options));

        return $this;
    }

    /**
     * Convert current field to image thumbnails
     */
    public function image(array $options = []): static
    {
        $key = $this->columns->requireCurrentField('image');

        $this->columns->setType($key, 'html');
        $this->columns->setFunction($key, $this->createImageFormatter($key, $options));

        return $this;
    }

    /**
     * Set truncate for current field
     */
    public function truncate(int $length, string $suffix = '...'): static
    {
        $key = $this->columns->requireCurrentField('truncate');
        $this->columns->setTruncate($key, $length, $suffix);
        return $this;
    }

    /**
     * Hide current field
     */
    public function hide(): static
    {
        $key = $this->columns->requireCurrentField('hide');
        $this->columns->hide($key);
        return $this;
    }

    /**
     * Disable sorting for current field
     */
    public function noSort(): static
    {
        $key = $this->columns->requireCurrentField('noSort');
        $this->columns->setDisableSort($key, true);
        return $this;
    }

    /**
     * Map sort field for current field
     */
    public function sortBy(string $realField): static
    {
        $key = $this->columns->requireCurrentField('sortBy');
        $this->context->addSortMapping($key, $realField);
        return $this;
    }

    /**
     * Set conditional visibility based on filters
     */
    public function showIfFilter(array $condition): static
    {
        $key = $this->columns->requireCurrentField('showIfFilter');
        $this->columns->setShowIfFilter($key, $condition);
        return $this;
    }

    /**
     * Set CSS class for current field (base - override in subclasses)
     */
    public function class(string $classes): static
    {
        $this->columns->requireCurrentField('class');
        return $this;
    }

    /**
     * Set CSS class based on field value (base - override in subclasses)
     */
    public function classValue($value, string $classes, string $comparison = '=='): static
    {
        $this->columns->requireCurrentField('classValue');
        return $this;
    }

    /**
     * Set CSS class based on another field's value (base - override in subclasses)
     */
    public function classOtherValue(string $checkField, $value, string $classes, string $comparison = '=='): static
    {
        $this->columns->requireCurrentField('classOtherValue');
        return $this;
    }

    // ========================================================================
    // FORMATTERS
    // ========================================================================

    private function createLinkFormatter(string $key, string $link, array $options): callable
    {
        return function ($rowArray) use ($key, $link, $options) {
            $finalLink = $this->replaceUrlPlaceholders($link, $rowArray);
            $attributes = $this->buildHtmlAttributes($options);
            $displayText = $this->getDisplayText($rowArray, $key);

            return '<a href="' . Route::url($finalLink) . '"' . $attributes . '>' . $displayText . '</a>';
        };
    }

    private function createFileFormatter(string $key, array $options): callable
    {
        return function ($rowArray) use ($key, $options) {
            $value = $this->dataProcessor->extractDotNotationValue($rowArray, $key);
            $files = $this->parseJsonArray($value);

            if (empty($files)) {
                return '';
            }

            $class = $options['class'] ?? 'js-file-download';
            $target = $options['target'] ?? '_blank';
            $output = '';

            foreach ($files as $file) {
                $url = $this->extractFileProperty($file, 'url');
                $name = $this->extractFileProperty($file, 'name');

                if ($url && $name) {
                    $output .= sprintf(
                        '<a href="%s" target="%s" class="%s">%s</a><br>',
                        htmlspecialchars($url),
                        htmlspecialchars($target),
                        htmlspecialchars($class),
                        htmlspecialchars($name)
                    );
                }
            }

            return $output;
        };
    }

    private function createImageFormatter(string $key, array $options): callable
    {
        return function ($rowArray) use ($key, $options) {
            $value = $this->dataProcessor->extractDotNotationValue($rowArray, $key);
            $files = $this->parseJsonArray($value);

            if (empty($files)) {
                return is_string($value) ? '' : $value;
            }

            $size = $options['size'] ?? 50;
            $class = $options['class'] ?? '';
            $lightbox = $options['lightbox'] ?? false;
            $maxImages = $options['max_images'] ?? null;

            $output = '<div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">';
            $count = 0;

            foreach ($files as $file) {
                $url = $this->extractFileProperty($file, 'url');
                $name = $this->extractFileProperty($file, 'name') ?? '';

                if (!$url) {
                    continue;
                }

                if ($maxImages !== null && $count >= $maxImages) {
                    $remaining = count($files) - $count;
                    $output .= $this->createMoreIndicator($size, $remaining);
                    break;
                }

                $imgHtml = $this->createImageTag($url, $name, $size, $class);
                $output .= $lightbox ? $this->wrapInLightbox($imgHtml, $url, $key) : $imgHtml;
                $count++;
            }

            $output .= '</div>';

            return $output;
        };
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function replaceUrlPlaceholders(string $link, $row): string
    {
        $properties = is_array($row) ? $row : get_object_vars($row);
        $flat = [];

        foreach ($properties as $key => $value) {
            if ($value instanceof \DateTime) {
                $flat[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_scalar($value)) {
                $flat[$key] = (string) $value;
            }
        }

        return Route::replaceUrlPlaceholders($link, ['id' => $flat['id'] ?? null, ...$flat]);
    }

    private function buildHtmlAttributes(array $options): string
    {
        $attributes = [];

        foreach ($options as $key => $value) {
            $attributes[] = $key . '="' . _r($value) . '"';
        }

        return $attributes ? ' ' . implode(' ', $attributes) : '';
    }

    private function getDisplayText($row, string $key): string
    {
        $value = $this->dataProcessor->extractDotNotationValue($row, $key);
        $rules = $this->context->getModel()->getRules();
        $rule = $rules[$key] ?? null;

        if (!$rule || !in_array($rule['type'], ['datetime', 'date', 'time'])) {
            return (string) $value;
        }

        if ($value instanceof \DateTime) {
            $formatted = Get::formatDate($value, $rule['type']);
            return $formatted !== '' ? $formatted : $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            $formatted = Get::formatDate($value, $rule['type']);
            return $formatted !== '' ? $formatted : $value;
        }

        return (string) $value;
    }

    private function parseJsonArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($value) ? $value : [];
    }

    private function extractFileProperty($file, string $property): ?string
    {
        if (is_array($file)) {
            return $file[$property] ?? null;
        }

        if (is_object($file)) {
            return $file->{$property} ?? null;
        }

        return null;
    }

    private function createImageTag(string $url, string $name, int $size, string $class): string
    {
        return sprintf(
            '<img src="%s" alt="%s" style="width: %dpx; height: %dpx; object-fit: cover; border-radius: 4px;" class="%s">',
            htmlspecialchars($url),
            htmlspecialchars($name),
            $size,
            $size,
            htmlspecialchars($class)
        );
    }

    private function createMoreIndicator(int $size, int $remaining): string
    {
        return sprintf(
            '<div style="width: %dpx; height: %dpx; display: flex; align-items: center; justify-content: center; background: #e9ecef; border-radius: 4px; font-size: 0.8rem;">+%d</div>',
            $size,
            $size,
            $remaining
        );
    }

    private function wrapInLightbox(string $imgHtml, string $url, string $key): string
    {
        return sprintf(
            '<a href="%s" target="_blank" data-lightbox="%s">%s</a>',
            htmlspecialchars($url),
            htmlspecialchars($key),
            $imgHtml
        );
    }
}
