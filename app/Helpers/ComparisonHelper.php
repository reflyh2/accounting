<?php

if (!function_exists('floatsEqual')) {
    function floatsEqual($a, $b, $delta = 0.0001)
    {
        return abs($a - $b) < $delta;
    }
}

if (!function_exists('floatsGreaterThan')) {
    function floatsGreaterThan($a, $b, $delta = 0.0001)
    {
        return $a > $b + $delta;
    }
}

if (!function_exists('floatsLessThan')) {
    function floatsLessThan($a, $b, $delta = 0.0001)
    {
        return $a < $b - $delta;
    }
}

if (!function_exists('floatsGreaterThanOrEqual')) {
    function floatsGreaterThanOrEqual($a, $b, $delta = 0.0001)
    {
        return floatsEqual($a, $b, $delta) || floatsGreaterThan($a, $b, $delta);
    }
}

if (!function_exists('floatsLessThanOrEqual')) {
    function floatsLessThanOrEqual($a, $b, $delta = 0.0001)
    {
        return floatsEqual($a, $b, $delta) || floatsLessThan($a, $b, $delta);
    }
}