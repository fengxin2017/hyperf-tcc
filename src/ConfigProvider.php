<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Fengxin2017\Tcc;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'publish'      => [
                [
                    'id'          => 'migrations',
                    'description' => 'create transaction table',
                    'source'      => __DIR__ . '/../migrations/CreateTransactionTable.php',
                    'destination' => BASE_PATH . '/migrations/' . date('Y_m_d_his') . '_create_transaction_table.php'
                ]
            ],
            'dependencies' => [
            ],
            'commands'     => [
            ],
            'annotations'  => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
}
