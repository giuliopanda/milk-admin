<?php
namespace App\Abstracts\Services\AbstractModel;

!defined('MILK_DIR') && die();

class ExtensionRegistryService
{
    public function normalize(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = [];
                continue;
            }

            $normalized[$key] = is_array($value) ? $value : [];
        }

        return $normalized;
    }

    public function merge(array $original, array $new): array
    {
        $original = $this->normalize($original);
        $new = $this->normalize($new);

        foreach ($new as $extensionName => $params) {
            if (isset($original[$extensionName])) {
                $original[$extensionName] = array_merge($original[$extensionName], $params);
                continue;
            }

            $original[$extensionName] = $params;
        }

        return $original;
    }
}
