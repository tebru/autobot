<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Provider;

use ReflectionClass;
use Tebru\Autobot\Annotation\Exclude;
use Tebru\Autobot\Annotation\GetterTransform;
use Tebru\Autobot\Annotation\Map;
use Tebru\Autobot\Annotation\SetterTransform;
use Tebru\Autobot\Annotation\Type;
use Tebru\Dynamo\Collection\AnnotationCollection;

/**
 * Class AnnotationProvider
 *
 * @author Nate Brunette <n@tebru.net>
 */
class AnnotationProvider
{
    /**
     * @var AnnotationCollection
     */
    private $annotationCollection;

    /**
     * Constructor
     *
     * @param AnnotationCollection $annotationCollection
     */
    public function __construct(AnnotationCollection $annotationCollection)
    {
        $this->annotationCollection = $annotationCollection;
    }

    public function shouldExclude($propertyName)
    {
        if (!$this->annotationCollection->exists(Exclude::NAME)) {
            return false;
        }

        /** @var Exclude $annotation */
        $annotation = $this->annotationCollection->get(Exclude::NAME);

        if (!$annotation->shouldExclude($propertyName)) {
            return false;
        }

        return true;
    }

    public function getMappedGetter($propertyName)
    {
        if (!$this->annotationCollection->exists(Map::NAME)) {
            return null;
        }

        /** @var Map $mapAnnotation */
        foreach ($this->annotationCollection->get(Map::NAME) as $mapAnnotation) {
            // skip processing if not for property
            if ($mapAnnotation->getProperty() !== $propertyName) {
                continue;
            }

            return $mapAnnotation->getGetter();
        }

        return null;
    }

    public function getMappedSetter($propertyName)
    {
        if (!$this->annotationCollection->exists(Map::NAME)) {
            return null;
        }

        /** @var Map $mapAnnotation */
        foreach ($this->annotationCollection->get(Map::NAME) as $mapAnnotation) {
            // skip processing if not for property
            if ($mapAnnotation->getProperty() !== $propertyName) {
                continue;
            }

            return $mapAnnotation->getSetter();
        }

        return null;
    }

    public function getGetterTransformer()
    {
        if (!$this->annotationCollection->exists(GetterTransform::NAME)) {
            return null;
        }

        /** @var GetterTransform $annotation */
        $annotation = $this->annotationCollection->get(GetterTransform::NAME);

        return $annotation->getTransformer();
    }

    public function getSetterTransformer()
    {
        if (!$this->annotationCollection->exists(SetterTransform::NAME)) {
            return null;
        }

        /** @var SetterTransform $annotation */
        $annotation = $this->annotationCollection->get(SetterTransform::NAME);

        return $annotation->getTransformer();
    }

    public function getType($propertyName)
    {
        if (!$this->annotationCollection->exists(Type::NAME)) {
            return null;
        }

        /** @var Type $typeAnnotation */
        foreach ($this->annotationCollection->get(Type::NAME) as $typeAnnotation) {
            if ($typeAnnotation->getProperty() !== $propertyName) {
                continue;
            }

            return new ReflectionClass($typeAnnotation->getType());
        }

        return null;
    }
}
