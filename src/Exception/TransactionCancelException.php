<?php


namespace Fengxin2017\Tcc\Exception;

use Exception;

class TransactionCancelException extends Exception
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}