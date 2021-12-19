<?php


namespace Fengxin2017\Tcc;


use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\HigherOrderTapProxy;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class InjectService
{
    /**
     * @var Transaction $transation
     */
    protected $transation;

    /**
     * @var string $serviceinterface
     */
    protected $serviceInterface;

    /**
     * InjectService constructor.
     * @param Transaction $transation
     * @param string $serviceInterface
     */
    public function __construct(Transaction $transation, string $serviceInterface)
    {
        $this->transation = $transation;
        $this->serviceInterface = $serviceInterface;
    }

    /**
     * @param Transaction $transation
     * @return $this
     */
    public function setTransaction(Transaction $transation)
    {
        $this->transation = $transation;
        return $this;
    }

    /**
     * @param string $method
     * @param array $params
     * @return HigherOrderTapProxy|mixed
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __call(string $method, array $params)
    {
        $result = call_user_func_array([$this->rpcServiceClient(), $method], $params);

        if (rpc_context_get(Transaction::TRANSACTION_TYPE) == Transaction::TRANSACTION_TYPE_TRY) {
            $this->transation->addTryedService(
                $this->serviceInterface,
                $method,
                $params,
                );
        }


        return $result;
    }

    /**
     * @return mixed|string
     */
    protected function rpcServiceClient()
    {
        return ApplicationContext::getContainer()
            ->get($this->serviceInterface);
    }
}