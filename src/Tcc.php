<?php


namespace Fengxin2017\Tcc;


use Exception;
use Hyperf\Utils\Context;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * Class Tcc
 * @package Fengxin2017\Tcc
 *
 * @method static Transaction connections($connections)
 * @method static transaction(callable $callable)
 */
class Tcc
{
    /**
     * @param string $method
     * @param array $params
     * @return Transaction|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    public static function __callStatic(string $method, array $params)
    {
        if (!in_array($method, ['connections', 'transaction'])) {
            throw new Exception('Call to undefine method ' . $method);
        }

        $transaction = make(Transaction::class);
        $transaction->setTransactionId()
            ->setIsFirstNode(
                rpc_context_get(Transaction::TRANSACTION_TYPE)
            )
            ->setTransctionType(
                rpc_context_get(Transaction::TRANSACTION_TYPE)
            )
            ->setTransactionParentReq(
                rpc_context_get(Transaction::TRANSACTION_PARENT_REQ)
            )
            ->setTransactionReq(
                $transaction->getServiceIndex()
            );

        if ($method == 'connections') {
            $transaction->setConnections(...$params);
        } else {
            $transaction->setConnections('default');
        }

        Context::set(Transaction::TRANSACTION_OBJECT, $transaction);

        return $transaction->transaction(...$params);
    }

    /**
     * @return Transaction|null
     */
    public static function getTransaction(): ?Transaction
    {
        return Context::get(Transaction::TRANSACTION_OBJECT);
    }
}