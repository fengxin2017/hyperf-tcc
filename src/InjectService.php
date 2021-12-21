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
        $this->transation       = $transation;
        $this->serviceInterface = $serviceInterface;
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

        // 执行成功添加到执行成功列表
        $this->transation->addServiceExecSucceed(
            $this->serviceInterface,
            $method,
            $params,
            );


        return $result;
    }

    /**
     * @return mixed|string
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function rpcServiceClient()
    {
        return ApplicationContext::getContainer()
                                 ->get($this->serviceInterface);
    }
}