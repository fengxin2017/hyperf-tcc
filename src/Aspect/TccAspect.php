<?php

namespace Fengxin2017\Tcc\Aspect;

use Fengxin2017\Tcc\Annotation\TccCallAnnotation;
use Fengxin2017\Tcc\Tcc;
use Fengxin2017\Tcc\Transaction;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;

/**
 * @Aspect()
 */
class TccAspect extends AbstractAspect
{
    // 要切入的类或 Trait，可以多个，亦可通过 :: 标识到具体的某个方法，通过 * 可以模糊匹配
    public $classes = [];

    // 要切入的注解，具体切入的还是使用了这些注解的类，仅可切入类注解和类方法注解
    public $annotations = [
        TccCallAnnotation::class,
    ];

    /**
     * @param ProceedingJoinPoint $proceedingJoinPoint
     * @return mixed
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $class   = $proceedingJoinPoint->className;
        $method  = $proceedingJoinPoint->methodName;
        $params  = $proceedingJoinPoint->getArguments();
        $service = $proceedingJoinPoint->getInstance();

        return Tcc::transaction(
            function (Transaction $transaction) use ($class, $method, $params, $service) {
                return $transaction->execServiceMethod(
                    $class,
                    $method,
                    $params,
                    function ($func, $params) use ($service) {
                        return $service->{$func}(...$params);
                    }
                );
            }
        );
    }
}