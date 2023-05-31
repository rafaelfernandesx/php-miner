<?php

interface RpcClientInterface
{
    public function rpc($method, $params = null);
}

class BitcoinRpcClient implements RpcClientInterface
{
    private $rpcUrl;
    private $rpcUser;
    private $rpcPass;

    public function __construct($rpcUrl, $rpcUser, $rpcPass)
    {
        $this->rpcUrl = $rpcUrl;
        $this->rpcUser = $rpcUser;
        $this->rpcPass = $rpcPass;
    }

    public function rpc($method, $params = null)
    {
        $rpcId = random_int(0, 2147483647);
        $data = json_encode([
            'id' => $rpcId,
            'method' => $method,
            'params' => $params
        ]);

        $auth = base64_encode($this->rpcUser . ':' . $this->rpcPass);
        $headers = ['Authorization: Basic ' . $auth];

        $options = [
            // CURLOPT_URL => $this->rpcUrl,
            CURLOPT_URL => 'https://btc.getblock.io/d2f0f5e3-43e5-4919-8aec-dac1176b69a8/mainnet/',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);

        $jsonResponse = json_decode($response, true);

        if ($jsonResponse['id'] != $rpcId) {
            throw new Exception('Invalid response id: got ' . $jsonResponse['id'] . ', expected ' . $rpcId);
        } elseif ($jsonResponse['error'] !== null) {
            throw new Exception('RPC error: ' . json_encode($jsonResponse['error']));
        }

        return $jsonResponse['result'];
    }
}