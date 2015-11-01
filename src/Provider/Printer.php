<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Autobot\Provider;

/**
 * Class Printer
 *
 * @author Nate Brunette <n@tebru.net>
 */
class Printer
{
    const SET_FORMAT_METHOD = '$%s->%s(%s);';
    const SET_FORMAT_PUBLIC = '$%s->%s = %s;';
    const SET_FORMAT_ARRAY = '$%s["%s"] = %s;';
    const SET_FORMAT_VARIABLE = '$%s%s = %s;';

    const GET_FORMAT_METHOD = '$%s->%s()';
    const GET_FORMAT_PUBLIC = '$%s->%s';
    const GET_FORMAT_ARRAY = '$%s["%s"]';
    const GET_FORMAT_VARIABLE = '$%s%s';

    public function printLine($setFormat, $getFormat, $setVariable, $setProperty, $getVariable, $getProperty)
    {
        return sprintf(
            $setFormat,
            $setVariable,
            $setProperty,
            sprintf($getFormat, $getVariable, $getProperty)
        );
    }

    public function getGetFormatForMultipleArrayKeys(array $parentKeys = [])
    {
        $getter = ['$%s'];

        foreach ($parentKeys as $key) {
            $getter[] = sprintf('["%s"]', $key);
        }

        $getter[] = '["%s"]';

        return implode($getter);
    }

    public function getSetFormatForMultipleArrayKeys(array $parentKeys = [])
    {
        $getFormat = $this->getGetFormatForMultipleArrayKeys($parentKeys);

        return $getFormat . '=%s;';
    }
}
