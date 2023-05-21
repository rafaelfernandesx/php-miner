<?php

class BitcoinBlockSubmission
{
    private $rpcClient;

    public function __construct(RpcClientInterface $rpcClient)
    {
        $this->rpcClient = $rpcClient;
    }

    public function submitBlock($blockSubmission)
    {
        return $this->rpcClient->rpc('submitblock', [$blockSubmission]);
    }
}