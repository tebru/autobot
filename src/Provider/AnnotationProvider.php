<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Provider;

use ReflectionClass;
use Tebru\Autobot\Annotation\Depth;
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
    const DEFAULT_DEPTH = 10;

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

    public function shouldExclude($propertyName, array $parentKeys)
    {
        if (!$this->annotationCollection->exists(Exclude::NAME)) {
            return false;
        }

        /** @var Exclude $annotation */
        $annotation = $this->annotationCollection->get(Exclude::NAME);

        if (!$annotation->shouldExclude($this->getNameParts($propertyName, $parentKeys))) {
            return false;
        }

        return true;
    }

    public function getMappedGetter($propertyName, array $parentKeys)
    {
        if (!$this->annotationCollection->exists(Map::NAME)) {
            return null;
        }

        /** @var Map $mapAnnotation */
        foreach ($this->annotationCollection->get(Map::NAME) as $mapAnnotation) {
            // skip processing if not for property
            if (!in_array($mapAnnotation->getProperty(), $this->getNameParts($propertyName, $parentKeys))) {
                continue;
            }

            return $mapAnnotation->getGetter();
        }

        return null;
    }

    public function getMappedSetter($propertyName, array $parentKeys)
    {
        if (!$this->annotationCollection->exists(Map::NAME)) {
            return null;
        }

        /** @var Map $mapAnnotation */
        foreach ($this->annotationCollection->get(Map::NAME) as $mapAnnotation) {
            // skip processing if not for property
            if (!in_array($mapAnnotation->getProperty(), $this->getNameParts($propertyName, $parentKeys))) {
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

    public function getType($propertyName, array $parentKeys)
    {
        if (!$this->annotationCollection->exists(Type::NAME)) {
            return null;
        }

        /** @var Type $typeAnnotation */
        foreach ($this->annotationCollection->get(Type::NAME) as $typeAnnotation) {
            if (!in_array($typeAnnotation->getProperty(), $this->getNameParts($propertyName, $parentKeys))) {
                continue;
            }

            return $typeAnnotation->getType();
        }

        return null;
    }

    public function getDepth()
    {
        if (!$this->annotationCollection->exists(Depth::NAME)) {
            return self::DEFAULT_DEPTH;
        }


        /** @var Depth $annotation */
        $annotation = $this->annotationCollection->get(Depth::NAME);

        return $annotation->getDepth();
    }

    private function getNameParts($propertyName, array $parentKeys)
    {
        $allParts = array_merge($parentKeys, [$propertyName]);

        // remove loop variable from chain
        $allParts = array_filter($allParts, function ($value) {
            return false === strstr($value, '$key');
        });

        $arrayLength = sizeof($allParts);
        $offset = $arrayLength - 1;
        $length = 1;
        $parts = [];

        while ($length <= $arrayLength) {
            $parts[] = implode('.', array_slice($allParts, $offset, $length));

            $offset--;
            $length++;
        }

        return $parts;
    }
}
