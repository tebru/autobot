<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Subscriber\Accessor;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tebru\Autobot\Event\AccessorTransformerEvent;

/**
 * Class SnakeToCamelSubscriber
 *
 * @author Nate Brunette <n@tebru.net>
 */
class SnakeCamelSubscriber implements EventSubscriberInterface
{
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
            'accessor_transformer.snake_to_camel' => 'transformSnakeToCamel',
            'accessor_transformer.camel_to_snake' => 'transformCamelToSnake',
        ];
    }

    public function transformSnakeToCamel(AccessorTransformerEvent $event)
    {
        $parts = explode('_', $event->getPropertyName());
        $parts = array_map(function ($value) { ucfirst($value); }, $parts);

        $name = lcfirst(implode($parts));

        $event->setAccessor($name);

        return null;
    }

    public function transformCamelToSnake(AccessorTransformerEvent $event)
    {
        $name = preg_replace('/([A-Z])/', '_$1', $event->getPropertyName());
        $name = strtolower($name);

        $event->setAccessor($name);

        return null;
    }
}
