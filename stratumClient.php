<?php
class Work
{
    public $job_id;
    public $prev_hash;
    public $coinbase1;
    public $coinbase2;
    public $merkle_branch;
    public $version;
    public $bits;
    public $ntime;
    public bool $clean_jobs;

    public $extranonce1;

    public function __construct() {}

    public function setWork($json)
    {
        $this->job_id = $json["params"][0];
        $this->prev_hash = $json["params"][1];
        $this->coinbase1 = $json["params"][2];
        $this->coinbase2 = $json["params"][3];
        $this->merkle_branch = $json["params"][4]; // Array de hashes Merkle
        $this->version = $json["params"][5];
        $this->bits = $json["params"][6];
        $this->ntime = $json["params"][7];
        $this->clean_jobs = $json["params"][8];
        return $this;
    }

    public function setExtranonce1($extranonce1)
    {
        $this->extranonce1 = $extranonce1;
    }
}
class StratumClient
{
    private $socket;
    public $username;
    private $password;
    private Work $work;

    public function __construct($host, $port, $username, $password)
    {
        $this->work = new Work();
        $this->socket = fsockopen($host, $port, $errno, $errstr, 10);
        if (!$this->socket) {
            die("Erro ao conectar à pool: $errstr ($errno)\n");
        }

        $this->username = $username;
        $this->password = $password;
        $this->subscribe();
        $this->authorize();
    }

    private function send($data)
    {
        fwrite($this->socket, json_encode($data) . "\n");
    }

    private function read()
    {
        return json_decode(fgets($this->socket), true);
    }

    private function subscribe()
    {
        $this->send(["id" => 1, "method" => "mining.subscribe", "params" => []]);
        $resp = $this->read();
        if ($resp["id"] == 1 && !empty($resp['result'][1])) {
            $this->work->setExtranonce1($resp['result'][1]);
            return $resp;
        }
        throw new Exception("Erro ao se inscrever na pool");
    }

    private function authorize()
    {
        $this->send(["id" => 2, "method" => "mining.authorize", "params" => [$this->username, $this->password]]);
        return $this->read();
    }

    public function getWork()
    {
        // $data = file_get_contents('blockToMine.json');
        // $json = json_decode($data, true);
        // $work = $this->work->setWork($json);
        // return $work;
        while (!feof($this->socket)) {
            $data = $this->read();
            if ($data) {
                // print_r($data);

                if ($data['method'] == 'mining.notify') {
                    $work = $this->work->setWork($data);
                    return $work;
                }
            }
        }

        return null;
    }

    public function submitWork($jobId, $nonce, $hash)
    {
        $this->send(["id" => 3, "method" => "mining.submit", "params" => [$this->username, $jobId, $nonce, $hash]]);
        $resp = $this->read();
        while ($resp["id"] != 3) {
            $resp = $this->read();
        }
        print_r($resp);
        return $resp;
    }

    function submit_work($job_id, $nonce, $extra_nonce, $ntime)
    {
        $data = [
            "method" => "mining.submit",
            "params" => [
                $this->username,
                $job_id,
                $extra_nonce,
                $ntime,
                $nonce
            ],
            "id" => 4
        ];

        $this->send($data);
        $resp = $this->read();
        while ($resp["id"] != 4) {
            $resp = $this->read();
        }
        print_r($resp);
        return $resp;
    }

    public function close()
    {
        fclose($this->socket);
    }
}
