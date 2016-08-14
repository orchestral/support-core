<?php

namespace Orchestra\Support;

use RuntimeException;

abstract class Morph
{
    /**
     * Method prefix.
     *
     * @var string
     */
    public static $prefix = '';

    /**
     * Magic method to call passtru PHP functions.
     *
     * @param  string  $method
     * @param  array   $parameters
     *
     * @throws \RuntimeException
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        if (! static::isCallable($method)) {
            throw new RuntimeException("Unable to call [{$method}].");
        }

        $callback = static::$prefix.snake_case($method);

        return $callback(...$parameters);
    }

    /**
     * Determine if method is callable.
     *
     * @param  string  $method
     *
     * @return bool
     */
    public static function isCallable($method)
    {
        return is_callable(static::$prefix.snake_case($method));
    }
}
