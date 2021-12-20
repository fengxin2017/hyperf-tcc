<?php


namespace Fengxin2017\Tcc;


use Fengxin2017\Tcc\Exception\TransactionMethodException;
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
     * 事务类型
     * @var string $transactionType
     */
    protected $transactionType = self::TRANSACTION_TYPE_TRY;

    /**
     * 是否为头结点
     * @var bool $isFirstNode
     */
    protected $isFirstNode;

    /**
     * Try成功的服务
     * @var TryedService[] $serviceTryed
     */
    protected $serviceTryed = [];

    /**
     * 本地数据库连接
     * @var array $connections
     */
    protected $connections = [];

    /**
     * 执行索引
     * @var int $serviceIndex
     */
    protected $serviceIndex = 0;

    /**
     * 父级请求号
     * @var string $transactionParentReq
     */
    protected $transactionParentReq = '';

    /**
     * 请求号
     * @var $transactionReq
     */
    protected $transactionReq;

    /**
     * 事务编号
     * @var int
     */
    protected static $transactionNo = 0;

    /**
     * 事务ID
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
            $result = $callable($this);

            // 如果是头节点依次确认Try过的服务
            if ($this->isFirstNode()) {
                $this->comfirmOrCancel(Transaction::TRANSACTION_TYPE_CONFIRM);
            }

            // 本地数据库事务提交
            foreach ($this->getConnections() as $connection) {
                Db::connection($connection)
                  ->commit();
            }

            return $result;
        } catch (Throwable $throwable) {
            // 本地事务Try期间发生的异常
            if ($this->transactionType == self::TRANSACTION_TYPE_TRY) {
                $this->comfirmOrCancel(Transaction::TRANSACTION_TYPE_CANCEL);
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
     * @throws Throwable
     * @throws TransactionMethodException
     */
    public function execServiceMethod(string $class, string $func, array $params, callable $callable)
    {
        if (!method_exists($class, $func . $this->transactionType)) {
            throw new TransactionMethodException($class . '::' . $func . ' Method is not found!');
        }

        $uniData = [
            'transaction_id'   => $this->transactionId,
            'transaction_type' => $this->transactionType,
            'service_class'    => $class,
            'service_func'     => $func,
            'service_params'   => json_encode($params),
            'service_req'      => $this->transactionReq
        ];

        TransactionModel::create(array_merge($uniData, ['status' => self::SERVICE_TRANSACTION_STATUS_WAIT]));

        foreach ($this->getConnections() as $connection) {
            Db::connection($connection)
              ->beginTransaction();
        }

        try {
            $result = call_user_func($callable, $func . $this->transactionType, $params);

            TransactionModel::where($uniData)
                            ->update(
                                [
                                    'status'         => self::SERVICE_TRANSACTION_STATUS_SUCCESS,
                                    'service_result' => json_encode($result)
                                ]
                            );
            return $result;
        } catch (Throwable $throwable) {
            foreach ($this->getConnections() as $connection) {
                Db::connection($connection)
                  ->rollBack();
            }

            TransactionModel::where($uniData)
                            ->update(
                                [
                                    'status' => self::SERVICE_TRANSACTION_STATUS_FAIL,
                                ]
                            );
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
            $transactionId = microtime(true) . '-' . rand(100000000, 999999999);
            $transactionId .= '-' . getmypid();
            $transactionId .= '-' . Coroutine::getCid();
            $transactionId .= '-' . self::$transactionNo;
            rpc_context_set(self::TRANSACTION_ID, $transactionId);
            $this->transactionId = $transactionId;
        }

        return $this;
    }

    /**
     * @param string|null $transactionType
     * @return $this
     */
    public function setIsFirstNode(?string $transactionType)
    {
        if (!is_null(Context::get(self::TRANSACTION_FIRST_NODE))) {
            return $this;
        } else {
            if (is_null($transactionType)) {
                $this->isFirstNode = true;
                Context::set(self::TRANSACTION_FIRST_NODE, true);
            } else {
                $this->isFirstNode = false;
                Context::set(self::TRANSACTION_FIRST_NODE, false);
            }

            return $this;
        }
    }

    /**
     * @return bool
     */
    public function isFirstNode()
    {
        return $this->isFirstNode === true;
    }

    /**
     * @return int
     */
    public function getServiceIndex()
    {
        return $this->serviceIndex;
    }

    /**
     * @param string $transactionType
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setTransctionType(?string $transactionType)
    {
        if (is_null($transactionType)) {
            $transactionType = self::TRANSACTION_TYPE_TRY;
        }

        $this->transactionType = $transactionType;

        rpc_context_set(self::TRANSACTION_TYPE, $transactionType);

        return $this;
    }

    /**
     * @param string|null $transactionParentReq
     * @return $this
     */
    public function setTransactionParentReq(?string $transactionParentReq = null)
    {
        if (!is_null($transactionParentReq)) {
            $this->transactionParentReq = $transactionParentReq;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTransactionParentReq()
    {
        return $this->transactionParentReq;
    }

    /**
     * @param int $serviceIndex
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function setTransactionReq(int $serviceIndex)
    {
        $transactionReq = ($this->transactionParentReq !== '' ? $this->transactionParentReq . '-' : '') . $serviceIndex;
        // 当前请求号 -> 后面被调用服务的父级请求号
        rpc_context_set(self::TRANSACTION_PARENT_REQ, $transactionReq);
        $this->transactionReq = $transactionReq;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTransactionReq()
    {
        return $this->transactionReq;
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
     * @param string $class
     * @param string $method
     * @param array $params
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
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

        $this->setTransactionReq(++$this->serviceIndex);
    }

    /**
     * @param string $type
     */
    public function comfirmOrCancel(string $type)
    {
        foreach ($this->serviceTryed as $index => $service) {
            try {
                $injectService = $this->setTransctionType($type)
                                      ->setTransactionReq($index)
                                      ->getService($service->getClass());

                call_user_func_array([$injectService, $service->getMethod()], $service->getParams());
            } catch (Throwable $throwable) {
                // Confirm or Cancel never throw exception.
            }
        }
    }
}