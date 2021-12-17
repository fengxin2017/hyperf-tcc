<?php


namespace Fengxin2017\Tcc;


use Fengxin2017\Tcc\Exception\TransactionCancelException;
use Fengxin2017\Tcc\Exception\TransactionCommitException;
use Fengxin2017\Tcc\Exception\TransactionMethodException;
use Fengxin2017\Tcc\Exception\TransactionTypeException;
use Fengxin2017\Tcc\Model\TransactionModel;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Context;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Swoole\Coroutine;
use Throwable;

/**
 * Class Tcc
 * @package Fengxin2017\Tcc
 */
class Transaction
{
    /**
     * @var string $transactionType
     */
    protected $transactionType = self::TRANSACTION_TYPE_TRY;

    /**
     * @var bool $isFirstNode
     */
    protected $isFirstNode;

    /**
     * @var TryedService[] $serviceTryed
     */
    protected $serviceTryed = [];

    /**
     * local db connections
     * @var array $connections
     */
    protected $connections = [];

    /**
     * local transaction number
     * @var int
     */
    protected static $transactionNo = 0;

    /**
     * transaction ID
     * @var string $transactionId
     */
    protected $transactionId;


    const TRANSACTION_OBJECT     = '_TRANSACTION_OBJECT_';//事务对象在协程上下文的KEY
    const TRANSACTION_TYPE       = '_TRANSACTION_TYPE_';//事务类型在RPC上下文的KEY
    const TRANSACTION_REQ        = '_TRANSACTION_REQ_';//事务服务请求号在RPC上下文的KEY
    const TRANSACTION_ID         = '_TRANSACTION_ID_';//事务ID在RPC上下文的KEY,整个链路使用同一个ID
    const TRANSACTION_LOCAL_REQ  = '_TRANSACTION_LOCAL_REQ_';
    const TRANSACTION_FIRST_NODE = '_TRANSACTION_FIRST_NODE_';
    const TRANSACTION_PARENT_REQ = '_TRANSACTION_PARENT_REQ_';

    //TCC类型
    const TRANSACTION_TYPE_TRY     = 'Try';//尝试
    const TRANSACTION_TYPE_CONFIRM = 'Confirm';//确认
    const TRANSACTION_TYPE_CANCEL  = 'Cancel';//取消

    //服务事务执行状态
    const SERVICE_TRANSACTION_STATUS_WAIT    = 0;//未执行
    const SERVICE_TRANSACTION_STATUS_FAIL    = 1;//执行失败
    const SERVICE_TRANSACTION_STATUS_SUCCESS = 2;//执行成功

    //服务重试最大次数
    const SERVICE_TRANSACTION_MAX_RETRY = 5;

    const CACHE_KEY_PREFIX = 'TCC:';

    /**
     * @param callable $callable
     * @return mixed
     * @throws Throwable
     */
    public function transaction(callable $callable)
    {
        try {
            foreach ($this->getConnections() as $connection) {
                Db::connection($connection)
                  ->beginTransaction();
            }

            $result = $callable($this);

            foreach ($this->getConnections() as $connection) {
                Db::connection($connection)
                  ->commit();
            }

            return $result;
        } catch (Throwable $throwable) {
            foreach ($this->getConnections() as $connection) {
                Db::connection($connection)
                  ->rollBack();
            }

            throw $throwable;
        }
    }

    /**
     * @param string $class
     * @param string $func
     * @param array $params
     * @param callable $callable
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     * @throws TransactionMethodException
     * @throws TransactionTypeException
     */
    public static function execServiceMethod(string $class, string $func, array $params, callable $callable)
    {
        $transactionType = rpc_context_get(Transaction::TRANSACTION_TYPE);

        if (empty($transactionType)) {
            throw new TransactionTypeException($class . '::' . $func . ' TRANSACTION_TYPE is empty!');
        }
        if (!method_exists($class, $func . $transactionType)) {
            throw new TransactionMethodException($class . '::' . $func . ' Method is not found!');
        }

        /** @var Transaction $transaction */
        $transaction = Context::get(Transaction::TRANSACTION_OBJECT);
        $uniData     = [
            'transaction_id'   => $transaction->transactionId,
            'transaction_type' => $transactionType,
            'service_class'    => $class,
            'service_fun'      => $func,
            'service_params'   => json_encode($params),
        ];
        try {
            TransactionModel::create(array_merge($uniData, ['status' => self::SERVICE_TRANSACTION_STATUS_WAIT]));

            $result = call_user_func($callable, $func . $transactionType, $params);

            // 如果是头节点先确认Try过的服务
            if ($transaction->isFirstNode()) {
                $transaction->comfirmOrCancel(Transaction::TRANSACTION_TYPE_CONFIRM);
            }

            TransactionModel::where($uniData)
                            ->update(
                                [
                                    'status'         => self::SERVICE_TRANSACTION_STATUS_SUCCESS,
                                    'service_result' => json_encode($result)
                                ]
                            );

            return $result;
        } catch (Throwable $throwable) {
            TransactionModel::where($uniData)
                            ->update(['status' => self::SERVICE_TRANSACTION_STATUS_FAIL]);
            // 本地事务Try期间发生的异常回滚
            if ($transactionType == Transaction::TRANSACTION_TYPE_TRY) {
                try {
                    $transaction->comfirmOrCancel(Transaction::TRANSACTION_TYPE_CANCEL);
                } catch (Throwable $throwable) {
                    throw $throwable;
                }
            }

            throw $throwable;
        }
    }

    /**
     * @param $connections
     * @return $this
     */
    public function setConnections($connections)
    {
        if (is_string($connections)) {
            $this->connections = Arr::wrap($connections);
        } else {
            $this->connections = $connections;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setTransactionId()
    {
        self::$transactionNo++;

        if (rpc_context_get(self::TRANSACTION_ID)) {
            $this->transactionId = rpc_context_get(self::TRANSACTION_ID);
        } else {
            $transactionId = microtime() . rand(100000000, 999999999);
            $transactionId .= '-' . getmypid();
            $transactionId .= '-' . Coroutine::getCid();
            $transactionId .= '-' . self::$transactionNo;
            rpc_context_set(self::TRANSACTION_ID, $transactionId);
            $this->transactionId = $transactionId;
        }

        return $this;
    }

    /**
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setIsFirstNode()
    {
        $this->isFirstNode = rpc_context_get(self::TRANSACTION_TYPE) ? false : true;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFirstNode()
    {
        return $this->isFirstNode === true;
    }

    /**
     * @param string $transactionType
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setTransctionType(string $transactionType)
    {
        rpc_context_set(self::TRANSACTION_TYPE, $transactionType);
        $this->transactionType = $transactionType;
        return $this;
    }

    /**
     * @param string $class
     * @param string $method
     * @param array $params
     */
    public function addTryedService(string $class, string $method, array $params)
    {
        array_push(
            $this->serviceTryed,
            make(TryedService::class)
                ->setClass($class)
                ->setMethod($method)
                ->setParams($params)
        );
    }

    /**
     * @return array
     */
    public function getTryedService()
    {
        return $this->serviceTryed;
    }

    /**
     * @param string $class
     * @return InjectService
     */
    public function getService(string $class)
    {
        return new InjectService($this, $class);
    }

    /**
     * @param string $type
     * @throws Throwable
     */
    public function comfirmOrCancel(string $type)
    {
        foreach ($this->serviceTryed as $service) {
            try {
                $injectService = $this->setTransctionType($type)
                                      ->getService($service->getClass())
                                      ->setTransaction($this);

                call_user_func_array([$injectService, $service->getMethod()], $service->getParams());
            } catch (Throwable $throwable) {
                if ($type == self::TRANSACTION_TYPE_CONFIRM) {
                    throw new TransactionCommitException($service->getClass() . '::' . $service->getMethod());
                }

                throw new TransactionCancelException($service->getClass() . '::' . $service->getMethod());
            }
        }
    }
}