<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot;

/**
 * Interface TypeHandler
 *
 * @author Nate Brunette <n@tebru.net>
 */
interface TypeHandler
{
    /**
     * Get the type
     *
     * @return string
     */
    public function getTypeName();

    /**
     * Get the method name that should be called when converting to an array
     *
     * @return string
     */
    public function getMethod();

    /**
     * Get the method arguments for the method
     *
     * @return array
     */
    public function getMethodArguments();
}
