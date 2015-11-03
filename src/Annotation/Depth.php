<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Annotation;

use Tebru;
use Tebru\Dynamo\Annotation\DynamoAnnotation;

/**
 * Class Depth
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Depth implements DynamoAnnotation
{
    const NAME = 'depth';

    private $depth;

    /**
     * Constructor
     *
     * @param array $values
     */
    public function __construct(array $values)
    {
        Tebru\assertThat(isset($values['value']) || isset($values['depth']), 'Depth must be passed in as first argument');

        $this->depth = isset($values['value']) ? $values['value'] : $values['depth'];
    }

    /**
     * @return int
     */
    public function getDepth()
    {
        return $this->depth;
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
        return false;
    }
}
