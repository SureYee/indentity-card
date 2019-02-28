<?php

namespace Sureyee\IdentityCard;


use Throwable;

class InvalidIdCardException extends \Exception
{
    protected $id;

    public function __construct($idCard, $code = 0, Throwable $previous = null)
    {
        $this->id = $idCard;
        parent::__construct("identity card {$this->id} is invalid", $code, $previous);
    }
}