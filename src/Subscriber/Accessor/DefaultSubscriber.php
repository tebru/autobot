<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Subscriber\Accessor;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tebru\Autobot\Event\AccessorTransformerEvent;
use Tebru\Autobot\Provider\Printer;

/**
 * Class DefaultSubscriber
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DefaultSubscriber implements EventSubscriberInterface
{
    private $getterPrefixes = ['get', 'has', 'is', 'should'];

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'accessor_transform.getter_default' => 'transformGetterDefault',
            'accessor_transform.setter_default' => 'transformSetterDefault',
        ];
    }

    public function transformGetterDefault(AccessorTransformerEvent $event)
    {
        $fromClass = $event->getReflectionClass();
        $getter = $event->getAccessor();
        $propertyName = $event->getPropertyName();
        $name = ucfirst($propertyName);
        $publicProperty = (empty($getter)) ? $propertyName : $getter;

        // if there isn't a 'from' class, we can assume we're mapping from an array
        if (null === $fromClass) {
            $accessor = (null === $getter) ? $propertyName : $getter;
            $event->setAccessor($accessor);
            $event->setGetPrintFormat(Printer::GET_FORMAT_ARRAY);

            return null;
        }


        // check to see if the method already exists
        if ($fromClass->hasMethod($getter)) {
            $event->setAccessor($getter);
            $event->setGetPrintFormat(Printer::GET_FORMAT_METHOD);

            return null;
        }

        // attempt to find method by using camel case prefixes
        foreach ($this->getterPrefixes as $prefix) {
            if ($fromClass->hasMethod($prefix . $name)) {
                $event->setAccessor($prefix . $name);
                $event->setGetPrintFormat(Printer::GET_FORMAT_METHOD);

                return null;
            }
        }

        // check for a public property
        if ($fromClass->hasProperty($publicProperty) && $fromClass->getProperty($publicProperty)->isPublic()) {
            $event->setAccessor($publicProperty);
            $event->setGetPrintFormat(Printer::GET_FORMAT_PUBLIC);

            return null;
        }

        return null;
    }

    public function transformSetterDefault(AccessorTransformerEvent $event)
    {
        $toClass = $event->getReflectionClass();
        $setter = $event->getAccessor();
        $propertyName = $event->getPropertyName();
        $name = ucfirst($propertyName);
        $publicProperty = (empty($setter)) ? $propertyName : $setter;

        // if there isn't a 'to' class, we can assume we're mapping from an array
        if (null === $toClass) {
            $accessor = (null === $setter) ? $propertyName : $setter;
            $event->setAccessor($accessor);
            $event->setSetPrintFormat(Printer::SET_FORMAT_ARRAY);

            return null;
        }

        // check to see if the method already exists
        if ($toClass->hasMethod($setter)) {
            $event->setAccessor($setter);
            $event->setSetPrintFormat(Printer::SET_FORMAT_METHOD);

            return null;
        }

        // check to see if setter exists
        if ($toClass->hasMethod('set' . $name)) {
            $event->setAccessor('set' . $name);
            $event->setSetPrintFormat(Printer::SET_FORMAT_METHOD);

            return null;
        }

        // check for a public property
        if ($toClass->hasProperty($publicProperty) && $toClass->getProperty($publicProperty)->isPublic()) {
            $event->setAccessor($publicProperty);
            $event->setSetPrintFormat(Printer::SET_FORMAT_PUBLIC);

            return null;
        }

        return null;
    }
}
