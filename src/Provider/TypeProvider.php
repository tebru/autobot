<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Provider;

use ReflectionProperty;
use Tebru\Dynamo\Collection\AnnotationCollection;

/**
 * Class TypeProvider
 *
 * @author Nate Brunette <n@tebru.net>
 */
class TypeProvider
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var AnnotationCollection
     */
    private $annotationProvider;

    /**
     * Constructor
     *
     * @param Schema $schema
     * @param AnnotationProvider $annotationProvider
     */
    public function __construct(Schema $schema, AnnotationProvider $annotationProvider)
    {
        $this->schema = $schema;
        $this->annotationProvider = $annotationProvider;
    }

    public function getType($setter, $propertyName, array $parentKeys)
    {
        $type = $this->annotationProvider->getType($propertyName, $parentKeys);

        if (null !== $type) {
            return $type;
        }

        $setMethod = 'set' . ucfirst($propertyName);

        if (!$this->schema->hasMethodsOr([$setter, $setMethod])) {
            return null;
        }

        $setterCheck = ($this->schema->hasMethod($setter)) ? $setter : $setMethod;
        $setterMethod = $this->schema->getMethod($setterCheck);
        $parameter = $setterMethod->getParameters()[0];
        $parameterType = $parameter->getClass();

        return $parameterType;
    }
}
