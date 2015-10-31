<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Annotation;

use Tebru;
use Tebru\Dynamo\Annotation\DynamoAnnotation;

/**
 * Class GetterTransform
 *
 * @author Nate Brunette <n@tebru.net>
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class GetterTransform implements DynamoAnnotation
{
    const NAME = 'getter_transform';

    private $transformer;

    /**
     * Constructor
     *
     * @param array $values
     */
    public function __construct(array $values)
    {
        Tebru\assertThat(isset($values['value']), '@GetterTransform must be passed a transformer name as the first value');

        $this->transformer = $values['value'];
    }

    /**
     * @return string
     */
    public function getTransformer()
    {
        return $this->transformer;
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
