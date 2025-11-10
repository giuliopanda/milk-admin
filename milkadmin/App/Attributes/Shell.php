<?php
namespace App\Attributes;

!defined('MILK_DIR') && die();

/**
 * Shell Command Attribute
 *
 * Defines a CLI command for a module method.
 *
 * @param string $command The command name (e.g., 'build-version')
 * @param bool $system If true, registers as system command without module prefix
 *
 * @example
 * ```php
 * #[Shell('build-version', system: true)]
 * public function buildVersion() {
 *     // This will be registered as 'build-version' (system command)
 * }
 *
 * #[Shell('my-command')]
 * public function myCommand() {
 *     // This will be registered as 'modulename:my-command' (module command)
 * }
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Shell {

    public function __construct(
        public string $command,
        public bool $system = false,
    ) {
    }
}