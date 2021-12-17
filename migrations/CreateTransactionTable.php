<?php

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

class CreateTransactionTable extends Migration
{
    public function up()
    {
        Schema::create(
            '__transaction__',
            function (Blueprint $table) {
                $table->string('transaction_id')
                      ->comment('事务ID');
                $table->string('transaction_type')
                      ->comment('事务类型');
                $table->string('service_class')
                      ->comment('服务类名');
                $table->string('service_func')
                      ->comment('服务方法名');
                $table->text('service_params')
                      ->comment('服务参数');
                $table->text('service_result')
                      ->comment('服务返回结果');
                $table->tinyInteger('status')
                      ->comment('事务状态0待执行1失败2成功');
                $table->timestamps();
            }
        );
    }

    public function down()
    {
        Schema::dropIfExists('__transaction__');
    }
}