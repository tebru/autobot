<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Transformer;

use Tebru\Autobot\NameTransformer;

/**
 * Class SnakeToCamelTransformer
 *
 * @author Nate Brunette <n@tebru.net>
 */
class SnakeToCamelTransformer implements NameTransformer
{
    /**
     * Transforms from the property name
     *
     * @param string $name
     * @return string
     */
    public function transform($name)
    {
        $parts = explode('_', $name);
        $parts = array_map(function ($value) { ucfirst($value); }, $parts);

        return lcfirst(implode($parts));
    }
}
