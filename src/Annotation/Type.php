<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Annotation;

use Tebru;
use Tebru\Dynamo\Annotation\DynamoAnnotation;

/**
 * Class Type
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Type implements DynamoAnnotation
{
    const NAME = 'type';

    private $property;
    private $type;

    public function __construct(array $values)
    {
        Tebru\assertThat(isset($values['value']), '@Type must be passed a property name as the first value');
        Tebru\assertThat(isset($values['type']), '@Type must be passed a type');

        $this->property = $values['value'];
        $this->type = $values['type'];
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
    public function getType()
    {
        return $this->type;
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
