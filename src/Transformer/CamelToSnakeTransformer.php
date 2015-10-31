<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Transformer;

use Tebru\Autobot\NameTransformer;

/**
 * Class CamelToSnakeTransformer
 *
 * @author Nate Brunette <n@tebru.net>
 */
class CamelToSnakeTransformer implements NameTransformer
{
    /**
     * Transforms from the property name
     *
     * @param string $name
     * @return string
     */
    public function transform($name)
    {
        $name = preg_replace('/([A-Z])/', '_$1', $name);
        $name = strtolower($name);

        return $name;
    }
}
