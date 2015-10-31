<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Listener;

use ReflectionClass;
use ReflectionParameter;
use ReflectionProperty;
use Tebru;
use Tebru\Autobot\Annotation\Exclude;
use Tebru\Autobot\Annotation\GetterTransform;
use Tebru\Autobot\Annotation\Map;
use Tebru\Autobot\Annotation\Type;
use Tebru\Autobot\NameTransformer;
use Tebru\Dynamo\Collection\AnnotationCollection;
use Tebru\Dynamo\Event\MethodEvent;
use Tebru\Dynamo\Model\ParameterModel;
use UnexpectedValueException;

/**
 * Class DynamoMethodListener
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DynamoMethodListener
{
    const FORMAT_OBJECT = '$%s->%s';
    const FORMAT_ARRAY = '$%s["%s"]';
    const FORMAT_GETTER_METHOD = '%s()';
    const FORMAT_GETTER_PUBLIC = '%s';
    const FORMAT_GETTER_ARRAY = '%s';
    const FORMAT_SETTER_METHOD = '%s(%s)';
    const FORMAT_SETTER_PUBLIC = '%s = %s';
    const FORMAT_SETTER_ARRAY = '%s = %s';

    /**
     * @var NameTransformer[]
     */
    private $getterNameTransformers = [];

    public function setGetterNameTransformer($key, NameTransformer $transformer)
    {
        $this->getterNameTransformers[$key] = $transformer;
    }

    public function __invoke(MethodEvent $event)
    {
        $methodModel = $event->getMethodModel();
        $methodParameters = $methodModel->getParameters();

        // currently only supports exactly 2 parameters
        Tebru\assertCount(2, $methodParameters);

        /** @var ParameterModel $fromParameter */
        $fromParameter = array_shift($methodParameters);

        /** @var ParameterModel $toParameter */
        $toParameter = array_shift($methodParameters);

        $fromIsArray = ('array' === $fromParameter->getTypeHint()) ? true : false;

        $toClass = new ReflectionClass($toParameter->getTypeHint());
        $fromClass = ($fromIsArray) ? null : new ReflectionClass($fromParameter->getTypeHint());

        $body = [];

        if ($toParameter->isOptional()) {
            $body[] = sprintf('if (null === $%s) {', $toParameter->getName());
            $body[] = sprintf('$%s = new %s();', $toParameter->getName(), $toClass->getName());
            $body[] = sprintf('}');
        }

        $body = $this->parseClassProperties($body, $toClass, $event->getAnnotationCollection(), $fromIsArray, $fromParameter, $toParameter->getName(), $fromClass);

        $body[] = sprintf('return $%s;', $toParameter->getName());

        $methodModel->setBody(implode($body));
    }

    private function parseClassProperties(
        array $body,
        ReflectionClass $toClass,
        AnnotationCollection $annotationCollection,
        $fromIsArray,
        ParameterModel $fromParameter,
        $toParameterName,
        ReflectionClass $fromClass = null,
        array $parentKeys = []
    ) {
        foreach ($toClass->getProperties() as $property) {
            $propertyName = $property->getName();

            if ($annotationCollection->exists(Exclude::NAME)) {
                /** @var Exclude $annotation */
                $annotation = $annotationCollection->get(Exclude::NAME);

                if ($annotation->shouldExclude($propertyName)) {
                    continue;
                }
            }

            $getter = '';
            $setter = '';

            // check to see if there's a mapping that will override the default accessor
            if ($annotationCollection->exists(Map::NAME)) {
                /** @var Map $mapAnnotation */
                foreach ($annotationCollection->get(Map::NAME) as $mapAnnotation) {
                    // skip processing if not for property
                    if ($mapAnnotation->getProperty() !== $propertyName) {
                        continue;
                    }

                    $getter = $mapAnnotation->getGetter();
                    $setter = $mapAnnotation->getSetter();
                }
            }

            $publicProperty = (empty($getter)) ? $propertyName : $getter;
            $getFormat = self::FORMAT_OBJECT;

            if (empty($getter) && $annotationCollection->exists(GetterTransform::NAME)) {
                /** @var GetterTransform $annotation */
                $annotation = $annotationCollection->get(GetterTransform::NAME);

                if (isset($this->getterNameTransformers[$annotation->getTransformer()])) {
                    $getter = $this->getterNameTransformers[$annotation->getTransformer()]->transform($propertyName);
                }
            }

            if (!$fromIsArray && ($fromClass->hasMethod($getter) || $fromClass->hasMethod('get' . ucfirst($propertyName)))) {
                $getString = self::FORMAT_GETTER_METHOD;
                $getter = (empty($getter)) ? 'get' . ucfirst($propertyName) : $getter;
            } elseif (!$fromIsArray && ($fromClass->hasProperty($publicProperty) && $fromClass->getProperty($publicProperty)->isPublic())) {
                $getString = self::FORMAT_GETTER_PUBLIC;
                $getter = (empty($getter)) ? $propertyName : $getter;
            } elseif ($fromIsArray) {
                $getString = self::FORMAT_GETTER_ARRAY;
                $getter = (empty($getter)) ? $propertyName : $getter;
                $getFormat = self::FORMAT_ARRAY;
            } else {
                throw new UnexpectedValueException('Unable to resolve getter');
            }

            $parameterType = null;

            if ($toClass->hasMethod($setter) || $toClass->hasMethod('set' . ucfirst($propertyName))) {
                $setString = self::FORMAT_SETTER_METHOD;
                $setter = (empty($setter)) ? 'set' . ucfirst($propertyName) : $setter;

                // get the type
                $setterMethod = $toClass->getMethod($setter);

                /** @var ReflectionParameter $parameter */
                $parameter = $setterMethod->getParameters()[0];
                $parameterType = $parameter->getClass();

                if (null === $parameterType) {
                    $parameterType = $this->getType($annotationCollection, $property);
                }
            } elseif ($property->isPublic()) {
                $setString = self::FORMAT_SETTER_PUBLIC;
                $setter = $propertyName;
                $parameterType = $this->getType($annotationCollection, $property);
            } else {
                throw new UnexpectedValueException('Unable to resolve setter');
            }

            if ($fromIsArray && null !== $parameterType) {
                $nestedClass = new ReflectionClass($parameterType->getName());
                $parentKeys[] = $getter;

                $nestedClassName = 'autobot' . $nestedClass->getShortName() . uniqid();
                $body[] = sprintf('$%s = new %s();', $nestedClassName, $nestedClass->getName());

                $body = $this->parseClassProperties($body, $nestedClass, $annotationCollection, $fromIsArray, $fromParameter, $nestedClassName, $fromClass, $parentKeys);

                $body[] = sprintf(
                    $setString . ';',
                    sprintf(
                        self::FORMAT_OBJECT,
                        $toParameterName,
                        $setter
                    ),
                    '$' . $nestedClassName
                );


                $parentKeys = [];

                continue;
            }

            if ($fromIsArray) {
                $getFormat = $this->getArrayAccessString($parentKeys);
                $body[] = sprintf('if (isset(%s)) {', sprintf($getFormat, $fromParameter->getName(), $getter));
            }

            $body[] = sprintf(
                $setString . ';',
                sprintf(
                    self::FORMAT_OBJECT,
                    $toParameterName,
                    $setter
                ),
                sprintf(
                    $getString,
                    sprintf(
                        $getFormat,
                        $fromParameter->getName(),
                        $getter
                    )
                )
            );

            if ($fromIsArray) {
                $body[] = sprintf('}');
            }
        }

        return $body;
    }

    private function getArrayAccessString(array $parentKeys = [])
    {
        $getter = ['$%s'];

        foreach ($parentKeys as $key) {
            $getter[] = sprintf('["%s"]', $key);
        }

        $getter[] = '["%s"]';

        return implode($getter);
    }

    private function getType(AnnotationCollection $annotationCollection, ReflectionProperty $property)
    {
        if (!$annotationCollection->exists(Type::NAME)) {
            return null;
        }

        /** @var Type $typeAnnotation */
        foreach ($annotationCollection->get(Type::NAME) as $typeAnnotation) {
            if ($typeAnnotation->getProperty() !== $property->getName()) {
                continue;
            }

            return new ReflectionClass($typeAnnotation->getType());
        }

        return null;
    }
}
