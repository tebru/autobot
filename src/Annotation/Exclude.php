<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Annotation;

use Tebru\Dynamo\Annotation\DynamoAnnotation;

/**
 * Class Exclude
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Exclude implements DynamoAnnotation
{
    const NAME = 'exclude';

    private $excludes;

    public function __construct($values)
    {
        $excludes = $values['value'];
        if (!is_array($excludes)) {
            $excludes = [$excludes];
        }

        $this->excludes = $excludes;
    }

    /**
     * @return array
     */
    public function getExcludes()
    {
        return $this->excludes;
    }

    public function shouldExclude($propertyName)
    {
        return in_array($propertyName, $this->excludes);
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
