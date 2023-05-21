<?php

class BitcoinBlockTemplate
{
    private $rpcClient;

    public function __construct(RpcClientInterface $rpcClient)
    {
        $this->rpcClient = $rpcClient;
    }

    public function getBlockTemplate()
    {
        try {
            $result = $this->rpcClient->rpc('getblocktemplate', [["rules" => ["segwit"]]]);
            $result = json_decode($result, true);
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
}