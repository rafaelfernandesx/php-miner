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
            CURLOPT_URL => 'https://go.getblock.io/key',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            print_r($error_msg);
        }
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
