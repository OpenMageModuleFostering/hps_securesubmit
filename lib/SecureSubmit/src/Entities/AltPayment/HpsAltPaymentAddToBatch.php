<?php

class HpsAltPaymentAddToBatch extends HpsAuthorization
{
    public $status = null;
    public $statusMessage = null;

    public static function fromDict($rsp, $txnType, $returnType = 'HpsAltPaymentAddToBatch')
    {
        $addToBatch = $rsp->Transaction->$txnType;

        $capture = parent::fromDict($rsp, $txnType, $returnType);

        $capture->status = isset($addToBatch->Status) ? (string)$addToBatch->Status : null;
        $capture->statusMessage = isset($addToBatch->StatusMessage) ? (string)$addToBatch->StatusMessage : null;

        return $capture;
    }
}
