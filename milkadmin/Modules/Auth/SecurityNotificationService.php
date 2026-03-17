<?php
namespace Modules\Auth;

/**
 * Security notification orchestration.
 *
 * Notification sending is intentionally disabled for now to avoid noisy emails.
 */
class SecurityNotificationService
{
    public function notifySystemLockdown(int $attempts_count, int $attempts_window): void
    {
        unset($attempts_count, $attempts_window);

        /*
        // Example implementation:
        // - Throttle notifications (cache timestamp)
        // - Fetch active admins
        // - Build summary (attempt count/window, top attacker IPs)
        // - Send email using platform mailer
        */
    }
}
