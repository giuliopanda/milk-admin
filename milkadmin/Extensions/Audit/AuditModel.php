<?php
namespace Extensions\Audit;
use App\Attributes\{Validate, ToDisplayValue};
use App\Abstracts\AbstractModel;
use App\{Hooks, Get};

class AuditModel extends AbstractModel
{

    protected function configure($rule): void {
        Hooks::run('AuditModel.configure', $rule);
    }

    /**
     * Get formatted audit_user_id value - shows username instead of ID
     *
     * @param object $current_record_obj Current record object
     * @return string Formatted value
     */
    #[ToDisplayValue('audit_user_id')]
    public function getAuditUserIdFormatted($current_record_obj)
    {
        $value = $current_record_obj->audit_user_id;
        $user = Get::make('Auth')->getUser($value);
        if ($user) {
            return $user->username;
        }
        return 'User ID: ' . $value;
    }

    /**
     * Get formatted audit_timestamp value - converts unix timestamp to readable date
     *
     * @param object $current_record_obj Current record object
     * @return string Formatted value
     */
    #[ToDisplayValue('audit_timestamp')]
    public function getAuditTimestampFormatted($current_record_obj)
    {
        $value = $current_record_obj->audit_timestamp;
        return Get::formatDate(date('Y-m-d H:i:s', $value), 'dateTime', true);
    }

}