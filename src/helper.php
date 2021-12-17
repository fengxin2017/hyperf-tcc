<?php

use Hyperf\Rpc\Context;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

if (!function_exists('get_inject_obj')) {
    /**
     * @param $key
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function get_inject_obj($key)
    {
        return ApplicationContext::getContainer()
                                 ->get($key);
    }
}

if (!function_exists('rpc_context_get')) {
    /**
     * @param $key
     * @param $val
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function rpc_context_set($key, $val)
    {
        if (class_exists(Context::class)) {
            get_inject_obj(Context::class)->set($key, $val);
            return true;
        }
        return false;
    }
}


if (!function_exists('rpc_context_get')) {
    /**
     * @param $key
     * @param null $default
     * @return null
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function rpc_context_get($key, $default = null)
    {
        if (class_exists(Context::class)) {
            $val = get_inject_obj(Context::class)->get($key);
            $val = $val ?? $default;
            return $val;
        }
        return $default;
    }
}