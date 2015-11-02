<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\TypeHandler;

use DateTime;
use Tebru\Autobot\TypeHandler;

/**
 * Class DateTimeTypeHandler
 *
 * @author Nate Brunette <n@tebru.net>
 */
class DateTimeTypeHandler implements TypeHandler
{
    /**
     * Get the type
     *
     * @return string
     */
    public function getTypeName()
    {
        return 'DateTime';
    }

    /**
     * Get the method name that should be called when converting to an array
     *
     * @return string
     */
    public function getMethod()
    {
        return 'format';
    }

    /**
     * Get the method arguments for the method
     *
     * @return array
     */
    public function getMethodArguments()
    {
        return [DateTime::ISO8601];
    }
}
