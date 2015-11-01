<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Listener;

use ReflectionClass;
use ReflectionProperty;
use Tebru;
use Tebru\Autobot\Annotation\Exclude;
use Tebru\Autobot\Annotation\GetterTransform;
use Tebru\Autobot\Annotation\Map;
use Tebru\Autobot\Annotation\SetterTransform;
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

    /**
     * @var NameTransformer[]
     */
    private $setterNameTransformers = [];

    public function setGetterNameTransformer($key, NameTransformer $transformer)
    {
        $this->getterNameTransformers[$key] = $transformer;
    }

    public function setSetterNameTransformer($key, NameTransformer $transformer)
    {
        $this->setterNameTransformers[$key] = $transformer;
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

        $toIsArray = ('array' === $toParameter->getTypeHint()) ? true : false;
        $fromIsArray = ('array' === $fromParameter->getTypeHint()) ? true : false;

        Tebru\assertFalse($toIsArray && $fromIsArray, 'Unable to do Array-to-Array transformations');

        $toClass = ($toIsArray) ? null : new ReflectionClass($toParameter->getTypeHint());
        $fromClass = ($fromIsArray) ? null : new ReflectionClass($fromParameter->getTypeHint());

        $body = [];

        if ($toParameter->isOptional() && !$toIsArray) {
            $body[] = sprintf('if (null === $%s) {', $toParameter->getName());
            $body[] = sprintf('$%s = new %s();', $toParameter->getName(), $toClass->getName());
            $body[] = sprintf('}');
        }

        $body = $this->parseClassProperties($body, $toIsArray, $toClass, $event->getAnnotationCollection(), $fromIsArray, $fromParameter, $fromParameter->getName(), $toParameter->getName(), $fromClass);

        $body[] = sprintf('return $%s;', $toParameter->getName());

        $methodModel->setBody(implode($body));
    }

    private function parseClassProperties(
        array $body,
        $toIsArray,
        ReflectionClass $toClass = null,
        AnnotationCollection $annotationCollection,
        $fromIsArray,
        ParameterModel $fromParameter,
        $fromParameterName,
        $toParameterName,
        ReflectionClass $fromClass = null,
        array $parentKeys = []
    ) {
        /** @var ReflectionClass $owningClass */
        $owningClass = ($toIsArray) ? $fromClass : $toClass;

        foreach ($owningClass->getProperties() as $property) {
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
            $setMethod = 'set' . ucfirst($propertyName);

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
            $setFormat = self::FORMAT_OBJECT;

            if (empty($getter) && $annotationCollection->exists(GetterTransform::NAME)) {
                /** @var GetterTransform $annotation */
                $annotation = $annotationCollection->get(GetterTransform::NAME);

                if (isset($this->getterNameTransformers[$annotation->getTransformer()])) {
                    $getter = $this->getterNameTransformers[$annotation->getTransformer()]->transform($propertyName);
                }
            }

            if (empty($setter) && $annotationCollection->exists(SetterTransform::NAME)) {
                /** @var SetterTransform $annotation */
                $annotation = $annotationCollection->get(SetterTransform::NAME);

                if (isset($this->setterNameTransformers[$annotation->getTransformer()])) {
                    $setter = $this->setterNameTransformers[$annotation->getTransformer()]->transform($propertyName);
                }
            }

            if (!$fromIsArray && $fromClass->hasMethod($getter)) {
                $getString = self::FORMAT_GETTER_METHOD;
            } elseif (!$fromIsArray && $fromClass->hasMethod('get' . ucfirst($propertyName))) {
                $getString = self::FORMAT_GETTER_METHOD;
                $getter = 'get' . ucfirst($propertyName);
            } elseif (!$fromIsArray && $fromClass->hasMethod('is' . ucfirst($propertyName))) {
                $getString = self::FORMAT_GETTER_METHOD;
                $getter = 'is' . ucfirst($propertyName);
            } elseif (!$fromIsArray && $fromClass->hasMethod('has' . ucfirst($propertyName))) {
                $getString = self::FORMAT_GETTER_METHOD;
                $getter = 'has' . ucfirst($propertyName);
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

            if ($owningClass->hasMethod($setter) || $owningClass->hasMethod($setMethod)) {
                $setterCheck = ($owningClass->hasMethod($setter)) ? $setter : $setMethod;
                $setterMethod = $owningClass->getMethod($setterCheck);
                $parameter = $setterMethod->getParameters()[0];
                $parameterType = $parameter->getClass();
            }

            if (null === $parameterType) {
                $parameterType = $this->getType($annotationCollection, $property);
            }

            if (!$toIsArray && ($owningClass->hasMethod($setter) || $owningClass->hasMethod($setMethod))) {
                $setter = ($owningClass->hasMethod($setter)) ? $setter : $setMethod;
                $setString = self::FORMAT_SETTER_METHOD;
            } elseif (!$toIsArray && $property->isPublic()) {
                $setString = self::FORMAT_SETTER_PUBLIC;
                $setter = empty($setter) ? $propertyName : $setter;
            } elseif ($toIsArray) {
                $setString = self::FORMAT_SETTER_ARRAY;
                $setter = empty($setter) ? $propertyName : $setter;
                $setFormat = self::FORMAT_ARRAY;
            } else {
                throw new UnexpectedValueException('Unable to resolve setter');
            }

            if ($toIsArray && null !== $parameterType) {
                $nestedClass = new ReflectionClass($parameterType->getName());
                $parentKeys[] = $setter;

                $nestedClassVariableName = 'autobot' . $parameterType->getShortName() . uniqid();
                $body[] = sprintf(
                    '$%s = %s;',
                    $nestedClassVariableName,
                    sprintf(
                        $getString,
                        sprintf(
                            $getFormat,
                            $fromParameterName,
                            $getter
                        )
                    )
                );


                $body = $this->parseClassProperties($body, $toIsArray, $toClass, $annotationCollection, $fromIsArray, $fromParameter, $nestedClassVariableName, $toParameterName, $nestedClass, $parentKeys);

                $parentKeys = [];

                continue;
            }

            if ($fromIsArray && null !== $parameterType) {
                $nestedClass = new ReflectionClass($parameterType->getName());
                $parentKeys[] = $getter;

                $nestedClassVariableName = 'autobot' . $nestedClass->getShortName() . uniqid();
                $body[] = sprintf('$%s = new %s();', $nestedClassVariableName, $nestedClass->getName());

                $body = $this->parseClassProperties($body, $toIsArray, $nestedClass, $annotationCollection, $fromIsArray, $fromParameter, $fromParameterName, $nestedClassVariableName, $fromClass, $parentKeys);

                $body[] = sprintf(
                    $setString . ';',
                    sprintf(
                        $setFormat,
                        $toParameterName,
                        $setter
                    ),
                    '$' . $nestedClassVariableName
                );


                $parentKeys = [];

                continue;
            }

            if ($fromIsArray) {
                $getFormat = $this->getArrayAccessString($parentKeys);
                $body[] = sprintf('if (isset(%s)) {', sprintf($getFormat, $fromParameterName, $getter));
            }

            if ($toIsArray) {
                $setFormat = $this->getArrayAccessString($parentKeys);
            }

            $body[] = sprintf(
                $setString . ';',
                sprintf(
                    $setFormat,
                    $toParameterName,
                    $setter
                ),
                sprintf(
                    $getString,
                    sprintf(
                        $getFormat,
                        $fromParameterName,
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
