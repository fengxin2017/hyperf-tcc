## Installation

```
composer create-project fengxin2017/tcc
```

// 发布数据迁移文件
>php bin/hyperf.php vendor:publish fengxin2017/tcc

// 创建事务表
>php bin/hyperf.php migrate

## Basic Usage

```
<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;

use App\JsonRpc\Tcc1Interface;
use Fengxin2017\Tcc\Tcc;
use Fengxin2017\Tcc\Transaction;

class IndexController extends AbstractController
{
    public function index()
    {
        // 使用默认数据库连接
        return Tcc::transaction(
            function (Transaction $transaction) {
                Tcc::getService(Tcc1Interface::class)
                   ->test(1, 2);
                Tcc::getService(Tcc1Interface::class)
                   ->test(1, 2);
                Tcc::getService(Tcc1Interface::class)
                   ->test(1, 2);
                return 'done';
            }
        );
        // 本地事务使用指定数据库连接
//        return Tcc::connections('foo')
//                  ->transaction(
//                      function (Transaction $transaction) {
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          return 'done';
//                      }
//                  );

        // 本地事务使用多个数据库连接
//        return Tcc::connections(['foo', 'bar'])
//                  ->transaction(
//                      function (Transaction $transaction) {
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          Tcc::getService(Tcc1Interface::class)
//                             ->test(1, 2);
//                          return 'done';
//                      }
//                  );
    }
}
```

Tcc1Service 

```BTC
<?php


namespace App\JsonRpc;


use Fengxin2017\Tcc\Annotation\TccCallAnnotation;
use Fengxin2017\Tcc\Tcc;
use Hyperf\RpcServer\Annotation\RpcService;

/**
 * Class Tcc1Service
 * @package App\JsonRpc
 * @RpcService(name="Tcc1Service", protocol="jsonrpc-http", server="jsonrpc-http", publishTo="consul")
 */
class Tcc1Service implements Tcc1Interface
{
    /**
     * @param int $a
     * @param int $b
     * @TccCallAnnotation()
     */
    public function test(int $a, int $b)
    {
    }

    public function testTry(int $a, int $b)
    {
        var_dump('tcc1 try');
        $res  = Tcc::getService(Tcc2Interface::class)
                   ->test($a, $b);

        User::create([
            'name' => 'foo',
            'password' => '123456'
        ]);

        $res1 = Tcc::getService(Tcc2Interface::class)
                   ->test($a, $b);

        return [
            'tcc2-0' => $res,
            'tcc2-1' => $res1
        ];
    }

    public function testConfirm(int $a, int $b)
    {
        var_dump('tcc1 confirm');
        Tcc::getService(Tcc2Interface::class)
           ->test($a, $b);
        Tcc::getService(Tcc2Interface::class)
           ->test($a, $b);
    }

    public function testCancel(int $a, int $b)
    {
        var_dump('tcc1 cancel');
        Tcc::getService(Tcc2Interface::class)
           ->test($a, $b);
        User::where(['name' => 'foo','password' => '123456'])->delete();
        Tcc::getService(Tcc2Interface::class)
           ->test($a, $b);
    }
}
```

Tcc2Service

```
<?php


namespace App\JsonRpc;


use Fengxin2017\Tcc\Annotation\TccCallAnnotation;
use Hyperf\RpcServer\Annotation\RpcService;

/**
 * Class Tcc1Service
 * @package App\JsonRpc
 * @RpcService(name="Tcc2Service", protocol="jsonrpc-http", server="jsonrpc-http", publishTo="consul")
 */
class Tcc2Service implements Tcc2Interface
{
    /**
     * @param int $a
     * @param int $b
     * @TccCallAnnotation()
     */
    public function test(int $a, int $b)
    {
    }

    public function testTry(int $a, int $b)
    {
        var_dump('tcc2 try');
//        $int = random_int(1, 6);
//        if ($int < 2) {
//            throw new \Exception();
//        } else {
//            var_dump('tcc2 try');
//        }
    }

    public function testConfirm(int $a, int $b)
    {
        var_dump('tcc2 confirm');
    }

    public function testCancel(int $a, int $b)
    {
        var_dump('tcc2 cancel');
    }
}
```

## Notice

> 服务在Confirm/Cancel期间将不会抛出异常，务必确保代码层面在Confirm/Cancel期间正确性。