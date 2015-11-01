<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Event;

use ReflectionClass;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class AccessorTransformerEvent
 *
 * @author Nate Brunette <n@tebru.net>
 */
class AccessorTransformerEvent extends Event
{
    /**
     * @var string
     */
    private $propertyName;

    /**
     * @var string
     */
    private $accessor;


    private $getPrintFormat;

    /**
     * @var string
     */
    private $setPrintFormat;
    /**
     * @var ReflectionClass
     */
    private $reflectionClass;

    /**
     * Constructor
     *
     * @param string $propertyName
     * @param string $accessor
     * @param ReflectionClass $reflectionClass
     */
    public function __construct($propertyName, $accessor, ReflectionClass $reflectionClass = null)
    {
        $this->propertyName = $propertyName;
        $this->accessor = $accessor;
        $this->reflectionClass = $reflectionClass;
    }

    /**
     * @return string
     */
    public function getPropertyName()
    {
        return $this->propertyName;
    }

    /**
     * @param string $propertyName
     * @return $this
     */
    public function setPropertyName($propertyName)
    {
        $this->propertyName = $propertyName;

        return $this;
    }

    /**
     * @return string
     */
    public function getAccessor()
    {
        return $this->accessor;
    }

    /**
     * @param string $accessor
     * @return $this
     */
    public function setAccessor($accessor)
    {
        $this->accessor = $accessor;

        return $this;
    }

    /**
     * @return ReflectionClass
     */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }

    /**
     * @return string
     */
    public function getGetPrintFormat()
    {
        return $this->getPrintFormat;
    }

    /**
     * @param string $getPrintFormat
     * @return $this
     */
    public function setGetPrintFormat($getPrintFormat)
    {
        $this->getPrintFormat = $getPrintFormat;

        return $this;
    }

    /**
     * @return string
     */
    public function getSetPrintFormat()
    {
        return $this->setPrintFormat;
    }

    /**
     * @param string $setPrintFormat
     * @return $this
     */
    public function setSetPrintFormat($setPrintFormat)
    {
        $this->setPrintFormat = $setPrintFormat;

        return $this;
    }
}
