<?php


namespace Fengxin2017\Tcc;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * Class TccCall
 * @package Fengxin2017\Tcc
 */
abstract class TccCall
{
    /**
     * @param string $method
     * @param array $params
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public function __call(string $method, array $params)
    {
        return Transaction::execServiceMethod(
            __CLASS__,
            $method,
            $params,
            function ($func, $params) {
                return $this->{$func}(...$params);
            }
        );
    }
}