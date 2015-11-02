<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Listener;

use ReflectionClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tebru;
use Tebru\Autobot\Event\AccessorTransformerEvent;
use Tebru\Autobot\Provider\AnnotationProvider;
use Tebru\Autobot\Provider\Printer;
use Tebru\Autobot\Provider\Schema;
use Tebru\Autobot\Provider\TypeProvider;
use Tebru\Autobot\TypeHandler;
use Tebru\Dynamo\Event\MethodEvent;
use Tebru\Dynamo\Model\ParameterModel;

/**
 * Class DynamoMethodListener
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DynamoMethodListener
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var AnnotationProvider
     */
    private $annotationProvider;

    /**
     * @var Printer
     */
    private $printer;

    /**
     * @var TypeHandler[]
     */
    private $typeHandlers = [];

    /**
     * Constructor
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function addTypeHandler(TypeHandler $typeHandler)
    {
        $this->typeHandlers[$typeHandler->getTypeName()] = $typeHandler;

        return $this;
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

        $schema = new Schema($toClass, $fromClass);

        $body = [];

        if ($toParameter->isOptional() && !$toIsArray) {
            $body[] = sprintf('if (null === $%s) {', $toParameter->getName());
            $body[] = sprintf('$%s = new %s();', $toParameter->getName(), $toClass->getName());
            $body[] = sprintf('}');
        }

        $this->annotationProvider = new AnnotationProvider($event->getAnnotationCollection());
        $this->printer = new Printer();

        $body = $this->parseClassProperties($schema, $body, $toParameter->getName(), $fromParameter->getName());

        $body[] = sprintf('return $%s;', $toParameter->getName());

        $methodModel->setBody(implode($body));
    }

    private function parseClassProperties(
        Schema $schema,
        array $body,
        $toParameterName,
        $fromParameterName,
        array $parentKeys = []
    ) {
        $typeProvider = new TypeProvider($schema, $this->annotationProvider);

        foreach ($schema->getProperties() as $property) {
            $propertyName = $property->getName();

            if ($this->annotationProvider->shouldExclude($propertyName)) {
                continue;
            }

            // check if a map exists
            $getter = $this->annotationProvider->getMappedGetter($propertyName);
            $setter = $this->annotationProvider->getMappedSetter($propertyName);

            $getterTransformer = $this->annotationProvider->getGetterTransformer();
            if (null !== $getterTransformer) {
                $event = new AccessorTransformerEvent($propertyName, $getter, $schema->getFromClass());
                $this->eventDispatcher->dispatch('accessor_transform.' . $getterTransformer, $event);
                $getter = $event->getAccessor();
            }

            // dispatch the default event after a transformer
            $event = new AccessorTransformerEvent($propertyName, $getter, $schema->getFromClass());
            $this->eventDispatcher->dispatch('accessor_transform.getter_default', $event);

            $getter = $event->getAccessor();
            $getPrintFormat = $event->getGetPrintFormat();

            if (null === $getter) {
                continue;
            }

            $setterTransformer = $this->annotationProvider->getSetterTransformer();
            if (null !== $setterTransformer) {
                $event = new AccessorTransformerEvent($propertyName, $setter, $schema->getToClass());
                $this->eventDispatcher->dispatch('accessor_transform.' . $setterTransformer, $event);
                $setter = $event->getAccessor();
            }

            // dispatch the default event after a transformer
            $event = new AccessorTransformerEvent($propertyName, $setter, $schema->getToClass());
            $this->eventDispatcher->dispatch('accessor_transform.setter_default', $event);

            $setter = $event->getAccessor();
            $setPrintFormat = $event->getSetPrintFormat();

            if (null === $setter) {
                continue;
            }

            $parameterType = $typeProvider->getType($setter, $propertyName);

            if ($schema->mapToArray() && null !== $parameterType) {
                if (isset($this->typeHandlers[$parameterType->getName()])) {
                    $handler = $this->typeHandlers[$parameterType->getName()];
                    $method = $handler->getMethod();
                    $arguments = $handler->getMethodArguments();
                    $format = (empty($arguments))
                        ? sprintf('->%s()', $method)
                        : sprintf('->%s("%s")', $method, implode($arguments));
                    $format = $getPrintFormat . $format;
                    $body[] = $this->printer->printLine($setPrintFormat, $format, $toParameterName, $setter, $fromParameterName, $getter);
                    continue;
                }

                $nestedClass = new ReflectionClass($parameterType->getName());
                $nestedClassVariableName = $parameterType->getShortName() . '_' . uniqid();
                $body[] = $this->printer->printLine(
                    Printer::SET_FORMAT_VARIABLE,
                    $getPrintFormat,
                    $nestedClassVariableName,
                    '',
                    $fromParameterName,
                    $getter
                );

                $body = $this->parseClassProperties(
                    new Schema($schema->getToClass(), $nestedClass),
                    $body,
                    $toParameterName,
                    $nestedClassVariableName,
                    array_merge($parentKeys, [$setter])
                );

                continue;
            }

            if ($schema->mapFromArray() && null !== $parameterType) {
                if (isset($this->typeHandlers[$parameterType->getName()])) {
                    $getPrintFormat = $this->printer->getGetFormatForMultipleArrayKeys($parentKeys);
                    $body[] = sprintf('if (isset(%s)) {', sprintf($getPrintFormat, $fromParameterName, $getter));
                    $classFormat = sprintf('new %s(%s)', $parameterType->getName(), $getPrintFormat);
                    $body[] = $this->printer->printLine($setPrintFormat, $classFormat, $toParameterName, $setter, $fromParameterName, $getter);
                    $body[] = sprintf('}');

                    continue;
                }

                $nestedClass = new ReflectionClass($parameterType->getName());
                $nestedClassVariableName = $nestedClass->getShortName() . '_' . uniqid();
                $body[] = sprintf('$%s = new %s();', $nestedClassVariableName, $nestedClass->getName());

                $body = $this->parseClassProperties(
                    new Schema($nestedClass, $schema->getFromClass()),
                    $body,
                    $nestedClassVariableName,
                    $fromParameterName,
                    array_merge($parentKeys, [$getter])
                );

                $body[] = $this->printer->printLine(
                    $setPrintFormat,
                    Printer::GET_FORMAT_VARIABLE,
                    $toParameterName,
                    $setter,
                    $nestedClassVariableName,
                    ''
                );

                continue;
            }

            if ($schema->mapFromArray()) {
                $getPrintFormat = $this->printer->getGetFormatForMultipleArrayKeys($parentKeys);
                $body[] = sprintf('if (isset(%s)) {', sprintf($getPrintFormat, $fromParameterName, $getter));
            }

            if ($schema->mapToArray()) {
                $setPrintFormat = $this->printer->getSetFormatForMultipleArrayKeys($parentKeys);
            }

            $body[] = $this->printer->printLine($setPrintFormat, $getPrintFormat, $toParameterName, $setter, $fromParameterName, $getter);

            if ($schema->mapFromArray()) {
                $body[] = sprintf('}');
            }
        }

        return $body;
    }
}
