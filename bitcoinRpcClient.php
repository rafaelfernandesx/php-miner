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
            CURLOPT_URL => 'https://go.getblock.io/6edfe3170d224f748ae86b14e4df45da',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        curl_close($curl);

        $jsonResponse = json_decode($response, true);

        if ($jsonResponse['id'] != $rpcId) {
            throw new Exception('Invalid response: ' . $jsonResponse);
        } elseif ($jsonResponse['error'] !== null) {
            throw new Exception('RPC error: ' . json_encode($jsonResponse));
        }

        return $jsonResponse['result'];
    }
}