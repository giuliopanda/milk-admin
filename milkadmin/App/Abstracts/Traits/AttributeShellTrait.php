<?php
namespace App\Abstracts\Traits;

use App\{Hooks, Cli};
use ReflectionClass;
use ReflectionMethod;
use App\Attributes\Shell;

!defined('MILK_DIR') && die();

trait AttributeShellTrait {


    /**
     * Set up CLI hooks
     *
     * Registers shell commands for installing, updating, and uninstalling the module
     * if the model has the necessary methods. Also automatically registers any method
     * that starts with 'shell_' as a CLI command.
     *
     * @example
     * ```php
     * public function setupCliHooks() {
     *     parent::setupCliHooks();
     *     // Add another command to the shell
     *     Cli::set($this->page.":my_command", [$this, 'myCommand']);
     * }
     *
     * public function myCommand($param1, $param2) {
     *     Cli::echo("My command called with params ".$param1." ".$param2);
     * }
     * ```
     */
    public function setupAttributeShellTraitCliHooks() {
        // Aggiungo tutti i metodi che iniziano con shell_ o hanno l'attributo #[Shell()]
        $methods = $this->getShellMethods();

        foreach ($methods as $method) {
            // Se è un comando di sistema, registra senza il prefisso del modulo
            if (isset($method['system']) && $method['system'] === true) {
                Cli::set($method['name'], [$this, $method['fn']]);
            } else {
                // Comando modulo normale con prefisso modulo:comando
                Cli::set($this->page.":". $method['name'], [$this, $method['fn']]);
            }
        }

    }

    /**
     * Get all shell methods from the child class
     *
     * Finds all methods that start with 'shell_' in the child class,
     * excluding the standard install/uninstall/update methods.
     *
     * @return array List of shell method names
     */

    protected function getShellMethods() {
        $exclude = ['shellInstallModule', 'shellUninstallModule', 'shellUpdateModule'];
        $childClass = get_called_class();
        $reflection = new \ReflectionClass($childClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);
        $shellMethods = [];

        foreach ($methods as $method) {
            // Trova metodi che iniziano con 'shell' (esclusi install/uninstall/update)
            if (strpos($method->getName(), 'shell') === 0) {
                if (in_array($method->getName(), $exclude))  continue;
                $shellMethods[] = [
                    'name' => $method->getName(),
                    'fn' => $method->getName(),
                    'system' => false
                ];
            }

            // Trova metodi con attributo #[Shell()]
            $attributes = $method->getAttributes(Shell::class);
            foreach ($attributes as $attribute) {
                $shell = $attribute->newInstance();
                $shellMethods[] = [
                    'name' => $shell->command,
                    'fn' => $method->getName(),
                    'system' => $shell->system
                ];
            }
        }
        return array_merge($shellMethods);
    }

}
