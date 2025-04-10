<?php

namespace SlicedUpload;

abstract class Request
{
    public static function post($key, $required = true, $default = null)
    {
        if (!isset($_POST[$key])) {

            if ($required) {
                throw new \Exception("Invalid request: missing {$key}");
            }

            return $default;
        }

        return $_POST[$key];
    }

}