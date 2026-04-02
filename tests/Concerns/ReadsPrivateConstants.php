<?php

namespace Tests\Concerns;

use ReflectionClass;

/**
 * Reads private class constants in tests (keeps catalogues DRY with production code).
 */
trait ReadsPrivateConstants
{
    /**
     * @template T of object
     *
     * @param  class-string<T>  $class
     */
    protected function privateClassConstant(string $class, string $name): mixed
    {
        $reflection = new ReflectionClass($class);
        $constant = $reflection->getReflectionConstant($name);
        if ($constant === false) {
            $this->fail("Missing constant {$class}::{$name}");
        }

        return $constant->getValue();
    }
}
