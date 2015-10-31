<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Annotation;

use Tebru;
use Tebru\Dynamo\Annotation\DynamoAnnotation;

/**
 * Class Map
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Map implements DynamoAnnotation
{
    const NAME = 'map';

    private $property;
    private $getter;
    private $setter;

    public function __construct(array $values)
    {
        Tebru\assertThat(isset($values['value']), '@Map must be passed a property name as the first argument');

        $this->property = $values['value'];
        $this->getter = isset($values['getter']) ? $values['getter'] : 'get' . ucfirst($this->property);
        $this->setter = isset($values['setter']) ? $values['setter'] : 'set' . ucfirst($this->property);
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @return string
     */
    public function getGetter()
    {
        return $this->getter;
    }

    /**
     * @return string
     */
    public function getSetter()
    {
        return $this->setter;
    }

    /**
     * The name of the annotation or class of annotations
     *
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * Whether or not multiple annotations of this type can
     * be added to a method
     *
     * @return bool
     */
    public function allowMultiple()
    {
        return true;
    }
}
