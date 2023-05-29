<?php

class BitcoinMiner
{
    private $blockTemplate;
    private $blockSubmission;

    public function __construct(BitcoinBlockTemplate $blockTemplate, BitcoinBlockSubmission $blockSubmission)
    {
        $this->blockTemplate = $blockTemplate;
        $this->blockSubmission = $blockSubmission;
    }

    public function int2lehex($value, $width)
    {
        // Convert an unsigned integer to a little endian ASCII hex string.
        $bytes = [];
        for ($i = 0; $i < $width; $i++) {
            $bytes[] = ($value & 0xFF);
            $value >>= 8;
        }
        $bytes = array_reverse($bytes);
        $hex = bin2hex(call_user_func_array('pack', array_merge(['C*'], $bytes)));
        $hex = implode('', array_reverse(str_split($hex, 2)));
        return $hex;
    }

    public function bitcoinaddress2hash160($address)
    {
        $decoded = $this->base58Decode($address);
        $hash160 = substr($decoded, 1, 20);
        return bin2hex($hash160);
    }

    public function base58Decode($base58)
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = '0';
        for ($i = 0; $i < strlen($base58); $i++) {
            $base = bcmul($base, '58', 0);
            $base = bcadd($base, strpos($alphabet, $base58[$i]), 0);
        }
        $base256 = '';
        while (bccomp($base, '0') > 0) {
            $remainder = bcmod($base, '256');
            $base = bcdiv($base, '256', 0);
            $base256 = chr((int) $remainder) . $base256;
        }
        return $base256;
    }

    public function blockMakeHeader($block)
    {
        $header = "";
        $header .= pack("V", $block['version']);
        $previousBlockHash = hex2bin($block['previousblockhash']);
        $header .= strrev($previousBlockHash);
        $merkleRootHash = hex2bin($block['merkleroot']);
        $header .= strrev($merkleRootHash);
        $header .= pack("V", $block['curtime']);
        $targetBits = hex2bin($block['bits']);
        $header .= strrev($targetBits);
        $header .= pack("V", $block['nonce']);
        return $header;
    }

    public function blockComputeRawHash($header)
    {
        $hash1 = hash('sha256', $header, true);
        $hash2 = strrev(hash('sha256', $hash1, true));
        return $hash2;
    }

    public function mineBlock($coinbaseMessage, $address, $extranonceStart, $timeout = null, $debugnonceStart = false)
    {
        $coinbaseMessage = bin2hex($coinbaseMessage);
        $blockTemplate = $this->blockTemplate->getBlockTemplate();
        $coinbaseTx = [];
        array_unshift($blockTemplate['transactions'], $coinbaseTx);
        $blockTemplate['nonce'] = 0;
        $targetHash = bin2hex($this->blockBits2Target($blockTemplate['bits']));
        $timeStart = time();
        $hashRateCount = 0;
        $nonce = $extranonceStart;
        $coinbaseTx = $this->createCoinbaseTransaction($coinbaseMessage, $address, $nonce, $blockTemplate['coinbasevalue'], $blockTemplate['height']);
        $blockTemplate['transactions'][0] = $coinbaseTx;
        $merkleRoot = $this->calculateMerkleRoot($blockTemplate['transactions']);
        $blockTemplate['merkleroot'] = $merkleRoot;
        $blockHeader = $this->blockMakeHeader($blockTemplate);

        while (true) {
            $blockHeader = substr($blockHeader, 0, 76) . pack("V", $nonce);
            $blockHash = $this->blockComputeRawHash($blockHeader);
            $hashRateCount++;
            $currenthash = $this->hashToGmp($blockHash);
            $targHash = $this->hashToGmp(hex2bin($targetHash));
            if (gmp_cmp($currenthash, $targHash) <= 0) {
                file_put_contents('block.json', json_encode($blockTemplate));
                file_put_contents('info.json', json_encode(get_defined_vars()));
                $blockTemplate['nonce'] = $nonce;
                $blockTemplate['hash'] = bin2hex($blockHash);
                $blockSub = $this->buildBlock($blockTemplate);
                $result = $this->blockSubmission->submitBlock($blockSub);
                return $result;
            }
            $nonce++;
            if ($debugnonceStart && $nonce >= $extranonceStart + $debugnonceStart) {
                break;
            }
            if ($timeout !== null && (time() - $timeStart) >= $timeout) {
                echo 'Total hash: ' . $hashRateCount . ' in ' . $timeout . " seconds\n";
                break;
            }
        }

        return [
            'hashRateCount' => $hashRateCount,
            'nonce' => $nonce
        ];
    }
    public function bitLength($value)
    {
        $bin = gmp_strval(gmp_init($value), 2);
        return strlen($bin);
    }
    public function intToHex($value, $width)
    {
        $bytes = pack('C', $width);
        $hex = bin2hex($bytes);
        return $hex;
    }
    public function txEncodeCoinbaseHeight($height)
    {
        $height_lenght = (int) (log($height) / log(2)) + 1;
        $width = floor(($height_lenght + 7) / 8);
        $res = bin2hex(pack('C*', $width)) . $this->int2lehex($height, $width);
        return $res;
    }

    public function int2varinthex($value)
    {
        if ($value < 0xfd) {
            $res = $this->int2lehex($value, 1);
            return $res;
        } elseif ($value <= 0xffff) {
            return "fd" . $this->int2lehex($value, 2);
        } elseif ($value <= 0xffffffff) {
            return "fe" . $this->int2lehex($value, 4);
        } else {
            return "ff" . $this->int2lehex($value, 8);
        }
    }

    public function txMakeCoinbase($coinbase_script, $address, $value, $height)
    {
        $coinbase_script = $this->txEncodeCoinbaseHeight($height) . $coinbase_script;
        // Create a pubkey script
        // OP_DUP OP_HASH160 <len to push> <pubkey> OP_EQUALVERIFY OP_CHECKSIG
        $pubkey_script = "76" . "a9" . "14" . $this->bitcoinaddress2hash160($address) . "88" . "ac";

        $tx = "";
        $tx .= "01000000";
        $tx .= "01";
        $tx .= str_repeat("0", 64);
        $tx .= "ffffffff";
        $tx .= $this->int2varinthex(strlen($coinbase_script) / 2);
        $tx .= $coinbase_script;
        $tx .= "ffffffff";
        $tx .= "01";
        $tx .= $this->int2lehex($value, 8);
        $len = strlen($pubkey_script) / 2;
        $tx .= $this->int2varinthex($len);
        $tx .= $pubkey_script;
        $tx .= "00000000";
        return $tx;
    }

    private function createCoinbaseTransaction($message, $address, $nonce, $coinbaseValue, $height)
    {
        $coinbaseScript = $this->buildCoinbaseScript($message, $nonce);
        $txData = $this->txMakeCoinbase($coinbaseScript, $address, $coinbaseValue, $height);
        $coinbaseTx = [
            'data' => $txData,
            'hash' => '',
            'vin' => [
                [
                    'coinbase' => $coinbaseScript,
                    'sequence' => 0xffffffff
                ]
            ],
            'vout' => []
        ];
        $coinbaseTx['hash'] = $this->calculateTransactionHash($txData);
        return $coinbaseTx;
    }

    private function buildCoinbaseScript($message, $nonce)
    {
        $result = $message . $this->int2lehex($nonce, 4);
        return $result;
    }

    private function hexToLittleEndian($hex)
    {
        $bytes = str_split($hex, 2);
        $bytes = array_reverse($bytes);
        return implode($bytes);
    }

    private function calculateTransactionHash($tx)
    {
        $hash = hash('sha256', hash('sha256', hex2bin($tx), true), true);
        $hash = implode('', array_reverse(str_split(bin2hex($hash), 2)));
        return $hash;
    }

    private function calculateMerkleRoot($transactions)
    {
        if (count($transactions) == 1) {
            return $transactions[0]['hash'];
        }
        $merkle = [];
        foreach ($transactions as $transaction) {
            $merkle[] = $transaction['hash'];
        }
        while (count($merkle) > 1) {
            $level = [];
            for ($i = 0; $i < count($merkle); $i += 2) {
                $a = $merkle[$i];
                $b = isset($merkle[$i + 1]) ? $merkle[$i + 1] : $merkle[$i];
                $hash = hash('sha256', hex2bin($a . $b), true);
                $level[] = strrev(bin2hex(hash('sha256', $hash, true)));
            }
            $merkle = $level;
        }
        return $merkle[0];
    }

    private function hashToGmp($hash)
    {
        $gmp = gmp_init('0x' . bin2hex($hash), 16);
        return $gmp;
    }

    private function blockBits2Target($bits)
    {
        $bits = hex2bin($bits);
        $shift = ord($bits[0]) - 3;
        $value = substr($bits, 1);
        $target = $value . str_repeat("\x00", $shift);
        $target = str_repeat("\x00", 32 - strlen($target)) . $target;
        return $target;
    }

    private function buildBlock($block)
    {
        $submission = "";
        $submission .= bin2hex($this->blockMakeHeader($block));
        $submission .= $this->int2varinthex(count($block['transactions']));
        foreach ($block['transactions'] as $tx) {
            $submission .= $tx['data'];
        }
        return $submission;
    }
}