<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Provider;

use ReflectionClass;
use ReflectionProperty;

/**
 * Class Schema
 *
 * @author Nate Brunette <n@tebru.net>
 */
class Schema
{
    private $toClass;
    private $fromClass;

    /**
     * Constructor
     *
     * @param ReflectionClass $toClass
     * @param ReflectionClass $fromClass
     */
    public function __construct(ReflectionClass $toClass = null, ReflectionClass $fromClass = null)
    {
        $this->toClass = $toClass;
        $this->fromClass = $fromClass;
    }

    /**
     * @return ReflectionProperty[]
     */
    public function getProperties()
    {
        return $this->getOwningClass()->getProperties();
    }

    public function hasMethod($methodName)
    {
        return $this->getOwningClass()->hasMethod($methodName);
    }

    public function hasMethodsOr(array $methodNames)
    {
        $hasMethod = false;

        foreach ($methodNames as $methodName) {
            if ($this->hasMethod($methodName)) {
                $hasMethod = true;
                break;
            }
        }

        return $hasMethod;
    }

    public function getMethod($methodName)
    {
        return $this->getOwningClass()->getMethod($methodName);
    }

    public function mapToArray()
    {
        return null === $this->toClass;
    }

    public function mapFromArray()
    {
        return null === $this->fromClass;
    }

    /**
     * @return ReflectionClass
     */
    public function getToClass()
    {
        return $this->toClass;
    }

    /**
     * @return ReflectionClass
     */
    public function getFromClass()
    {
        return $this->fromClass;
    }

    private function getOwningClass()
    {
        return (null === $this->toClass) ? $this->fromClass : $this->toClass;
    }
}
