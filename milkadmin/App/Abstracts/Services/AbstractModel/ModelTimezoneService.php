<?php
namespace App\Abstracts\Services\AbstractModel;

use App\Abstracts\AbstractModel;
use App\Config;
use App\Get;

!defined('MILK_DIR') && die();

class ModelTimezoneService
{
    public function convertToUserTimezone(AbstractModel $model, ?array $records, bool $datesInUserTimezone): array
    {
        if ($datesInUserTimezone || !Config::get('use_user_timezone', false) || empty($records)) {
            return [$records, $datesInUserTimezone];
        }

        $userTimezone = trim(Get::userTimezone());
        if ($userTimezone === '') {
            $userTimezone = 'UTC';
        }

        return [
            $this->convertRecordsTimezone($model, $records, new \DateTimeZone($userTimezone), ['datetime', 'date'], 'convertDatesToUserTimezone'),
            true,
        ];
    }

    public function convertToUtc(AbstractModel $model, ?array $records, bool $datesInUserTimezone): array
    {
        if (!$datesInUserTimezone || !Config::get('use_user_timezone', false) || empty($records)) {
            return [$records, $datesInUserTimezone];
        }

        return [
            $this->convertRecordsTimezone($model, $records, new \DateTimeZone('UTC'), ['datetime', 'date', 'time'], 'convertDatesToUTC'),
            false,
        ];
    }

    private function convertRecordsTimezone(
        AbstractModel $model,
        ?array $records,
        \DateTimeZone $timezone,
        array $allowedTypes,
        string $recursiveMethod
    ): ?array {
        if ($records === null) {
            return null;
        }

        $rules = $model->getRules();

        foreach ($records as &$record) {
            foreach ($record as $fieldName => $fieldValue) {
                if ($fieldName === '___action') {
                    continue;
                }

                if ($fieldValue instanceof AbstractModel) {
                    $fieldValue->{$recursiveMethod}();
                    continue;
                }

                if (!isset($rules[$fieldName])) {
                    continue;
                }

                $rule = $rules[$fieldName];
                if (!in_array($rule['type'] ?? null, $allowedTypes, true)) {
                    continue;
                }

                if (($rule['timezone_conversion'] ?? false) !== true) {
                    continue;
                }

                if (!$fieldValue instanceof \DateTimeInterface) {
                    continue;
                }

                $record[$fieldName]->setTimezone($timezone);
            }
        }
        unset($record);

        return $records;
    }
}
