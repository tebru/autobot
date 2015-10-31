<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot;

/**
 * Interface NameTransformer
 *
 * @author Nate Brunette <n@tebru.net>
 */
interface NameTransformer
{
    /**
     * Transforms from the property name
     *
     * @param string $name
     * @return string
     */
    public function transform($name);
}
