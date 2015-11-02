<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\TypeHandler;

use Tebru\Autobot\TypeHandler;

/**
 * Class ArrayCollectionTypeHandler
 *
 * @author Nate Brunette <n@tebru.net>
 */
class ArrayCollectionTypeHandler implements TypeHandler
{

    /**
     * Get the type
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'Doctrine\Common\Collections\ArrayCollection';
    }

    /**
     * Get the method name that should be called when converting to an array
     *
     * @return string
     */
    public function getMethod()
    {
        return 'toArray';
    }

    /**
     * Get the method arguments for the method
     *
     * @return array
     */
    public function getMethodArguments()
    {
        return [];
    }
}
