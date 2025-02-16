<?php

require_once("stratumClient.php");

class StratumMiner
{
    public StratumClient $client;

    function __construct($host, $port, $username, $password)
    {
        $this->client = new StratumClient($host, $port, $username, $password);
    }

    function build_coinbase_tx(Work $work, $extra_nonce2)
    {
        $cb = $work->coinbase1 . $work->extranonce1 . $extra_nonce2 . $work->coinbase2;
        return $cb;
    }
    function doubleSha256($data)
    {
        return hash('sha256', hash('sha256', $data, true), true);
    }
    function merkle_root($coinbase_tx, $merkle_branch)
    {
        $hash = $this->doubleSha256(hex2bin($coinbase_tx));

        foreach ($merkle_branch as $branch) {
            $hash = $this->doubleSha256($hash . hex2bin($branch));
        }

        $merkle = bin2hex($hash); // A Merkle Root precisa estar em little-endian
        return $merkle;
    }

    function build_block_header($version, $previousblock, $merkleroot, $time, $bits, $nonce)
    {
        $version = implode('', array_reverse(str_split($version, 2)));
        $previousblock = implode('', array_reverse(str_split($previousblock, 2)));
        $merkleroot = implode('', array_reverse(str_split($merkleroot, 2)));
        $time = implode('', array_reverse(str_split(dechex($time), 2)));
        $bits = implode('', array_reverse(str_split($bits, 2)));
        $nonce = implode('', array_reverse(str_split(str_pad(dechex($nonce), 8, '0', STR_PAD_LEFT), 2)));
        $blockheader = $version . $previousblock . $merkleroot . $time . $bits . $nonce;
        $bytes = hex2bin($blockheader);
        $hash1 = hash('sha256', $bytes, true);
        $hash2 = hash('sha256', $hash1, true);
        $blockhash = bin2hex($hash2);
        $reversed_blockhash = implode('', array_reverse(str_split($blockhash, 2)));
        return $reversed_blockhash;
    }

    function bitstotarget($bits)
    {
        $exponent = hexdec(substr($bits, 0, 2));
        $coefficient = substr($bits, 2, 8);
        $target = str_pad(str_pad($coefficient, ($exponent * 2), '0', STR_PAD_RIGHT), 64, '0', STR_PAD_LEFT);
        return $target;
    }

    function mine(Work $work)
    {
        $extra_nonce2 = bin2hex(random_bytes(4)); // Gerar um nonce aleatório
        $coinbase_tx = $this->build_coinbase_tx($work, $extra_nonce2);
        $merkle_root = $this->merkle_root($coinbase_tx, $work->merkle_branch);

        $previousblock = implode('', array_reverse(str_split($work->prev_hash, 8)));
        $time = hexdec($work->ntime);
        $target = $this->bitstotarget($work->bits); // Convertendo target da pool
        $nonce = 0;

        $start_time = microtime(true);
        $last_report_time = $start_time;
        $last_check_time = $start_time;
        $hash_count = 0;

        while (true) {
            $current_time = microtime(true);
            // Verificar se há um novo bloco a cada 1 minuto
            if ($current_time - $last_check_time >= 60) {
                echo "Checking for new block...\n";
                $newBlock = $this->client->getWork();
                if ($newBlock->prev_hash != $work->prev_hash && $newBlock->clean_jobs == true) {
                    print_r($newBlock);
                    return $this->mine($newBlock);
                }
                $last_check_time = $current_time;
            }


            $hash = $this->build_block_header($work->version, $previousblock, $merkle_root, $time, $work->bits, $nonce);
            $gmphash = gmp_init($hash, 16);
            $gmptarget = gmp_init($target, 16);

            $hash_count++;


            if ($current_time - $last_report_time >= 5) {
                $elapsed_time = $current_time - $start_time;
                $hash_rate = $hash_count / $elapsed_time;
                echo intval($hash_rate) . " H/s | Previous Block: " . $work->prev_hash . ' | Last Nonce: ' . number_format(intval($nonce), 0, ',', '.') .  "\n";
                $last_report_time = $current_time;
            }

            if (gmp_cmp($gmphash, $gmptarget) < 0) {
                $nonce = str_pad(dechex($nonce), 8, '0', STR_PAD_LEFT);
                $payload = '{"params": ["' . $this->client->username . '", "' . $work->job_id . '", "' . $extra_nonce2 . '", "' . $work->ntime . '", "' . $nonce . '"], "id": 1, "method": "mining.submit"}';
                $response = $this->client->submit_work($work->job_id, $nonce, $extra_nonce2, $work->ntime);
                return [$payload, $response];
            }

            $nonce++;

            if ($nonce > 0xFFFFFFFF) {
                break; // Sai do loop caso ultrapasse o limite do nonce
            }
        }

        return false; // Nenhum hash válido encontrado
    }
}

$miner = new StratumMiner("sha256.poolbinance.com", 443, "worker", "password"); //binance example
while (true) {

    $work = $miner->client->getWork();
    print_r($work);
    $result = $miner->mine($work);

    if ($result) {
        print_r($result);
    }
}
