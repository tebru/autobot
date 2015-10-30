<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Listener;

use ReflectionClass;
use Tebru;
use Tebru\Autobot\Annotation\Exclude;
use Tebru\Autobot\Annotation\Map;
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
    const FORMAT_GETTER_METHOD = '%s()';
    const FORMAT_GETTER_PUBLIC = '%s';
    const FORMAT_SETTER_METHOD = '%s(%s)';
    const FORMAT_SETTER_PUBLIC = '%s = %s';

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

        // both parameters must be objects
        $toClass = new ReflectionClass($toParameter->getTypeHint());
        $fromClass = new ReflectionClass($fromParameter->getTypeHint());

        $body = [];

        if ($toParameter->isOptional()) {
            $body[] = sprintf('if (null === $%s) {', $toParameter->getName());
            $body[] = sprintf('$%s = new %s();', $toParameter->getName(), $toClass->getName());
            $body[] = sprintf('}');
        }

        foreach ($toClass->getProperties() as $property) {
            $propertyName = $property->getName();

            if ($event->getAnnotationCollection()->exists(Exclude::NAME)) {
                /** @var Exclude $annotation */
                $annotation = $event->getAnnotationCollection()->get(Exclude::NAME);

                if ($annotation->shouldExclude($propertyName)) {
                    continue;
                }
            }

            $getter = '';
            $setter = '';

            // check to see if there's a mapping that will override the default accessor
            if ($event->getAnnotationCollection()->exists(Map::NAME)) {
                /** @var Map $mapAnnotation */
                foreach ($event->getAnnotationCollection()->get(Map::NAME) as $mapAnnotation) {
                    // skip processing if not for property
                    if ($mapAnnotation->getProperty() !== $propertyName) {
                        continue;
                    }

                    $getter = $mapAnnotation->getGetter();
                    $setter = $mapAnnotation->getSetter();
                }
            }

            $publicProperty = (empty($getter)) ? $propertyName : $getter;

            if ($fromClass->hasMethod($getter) || $fromClass->hasMethod('get' . ucfirst($propertyName))) {
                $getString = self::FORMAT_GETTER_METHOD;
                $getter = (empty($getter)) ? 'get' . ucfirst($propertyName) : $getter;
            } elseif ($fromClass->hasProperty($publicProperty) && $fromClass->getProperty($publicProperty)->isPublic()) {
                $getString = self::FORMAT_GETTER_PUBLIC;
                $getter = (empty($getter)) ? $propertyName : $getter;
            } else {
                throw new UnexpectedValueException('Unable to resolve getter');
            }

            if ($toClass->hasMethod($setter) || $toClass->hasMethod('set' . ucfirst($propertyName))) {
                $setString = self::FORMAT_SETTER_METHOD;
                $setter = (empty($setter)) ? 'set' . ucfirst($propertyName) : $setter;
            } elseif ($property->isPublic()) {
                $setString = self::FORMAT_SETTER_PUBLIC;
                $setter = $propertyName;
            } else {
                throw new UnexpectedValueException('Unable to resolve setter');
            }

            $body[] = sprintf(
                $setString . ';',
                sprintf(
                    self::FORMAT_OBJECT,
                    $toParameter->getName(),
                    $setter
                ),
                sprintf(
                    $getString,
                    sprintf(
                        self::FORMAT_OBJECT,
                        $fromParameter->getName(),
                        $getter
                    )
                )
            );
        }

        $body[] = sprintf('return $%s;', $toParameter->getName());

        $methodModel->setBody(implode($body));
    }
}
