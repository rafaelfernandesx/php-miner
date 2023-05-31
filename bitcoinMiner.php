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
        $decoded = bin2hex($this->base58Decode($address));
        $hash160 = substr($decoded, 0, 40);
        return $hash160;
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

    public function mineBlock($blockTemplate, $coinbaseMessage, $address, $extranonceStart, $timeout = null, $debugnonceStart = false)
    {
        $coinbaseTx = [];
        array_unshift($blockTemplate['transactions'], $coinbaseTx);
        $coinbaseMessage = bin2hex($coinbaseMessage);
        $blockTemplate['nonce'] = 0;
        $targetHash = bin2hex($this->blockBits2Target($blockTemplate['bits']));
        $timeStart = time();
        $hashRate = 0;
        $hashRateCount = 0;
        $extraNonce = $extranonceStart;
        while ($extraNonce < 0xffffffff) {
            $coinbaseScript = $this->buildCoinbaseScript($coinbaseMessage, $extraNonce);
            $coinbaseTx = $this->createCoinbaseTransaction($coinbaseScript, $address, $blockTemplate['coinbasevalue'], $blockTemplate['height']);
            $blockTemplate['transactions'][0] = $coinbaseTx;
            $blockTemplate['merkleroot'] = $this->calculateMerkleRoot($blockTemplate['transactions']);
            $blockHeader = $this->blockMakeHeader($blockTemplate);
            $timeStamp = time();
            $nonce = $debugnonceStart ? $debugnonceStart : 0;
            while ($nonce <= 0xffffffff) {
                $blockHeader = substr($blockHeader, 0, 76) . pack("V", $nonce);
                $blockHash = $this->blockComputeRawHash($blockHeader);
                $currenthash = $this->hashToGmp($blockHash);
                $targHash = $this->hashToGmp(hex2bin($targetHash));
                if (gmp_cmp($currenthash, $targHash) <= 0) {
                    file_put_contents('block.json', json_encode($blockTemplate));
                    $blockTemplate['nonce'] = $nonce;
                    $blockTemplate['hash'] = bin2hex($blockHash);
                    $blockSub = $this->buildBlock($blockTemplate);
                    $result = $this->blockSubmission->submitBlock($blockSub);
                    return [
                        'hashRate' => $hashRate,
                        'hashRateCount' => $hashRateCount,
                        'nonce' => $nonce,
                        'extraNonce' => $extraNonce,
                        'blockTemplate' => $blockTemplate,
                        'result' => $result
                    ];
                }
                if ($nonce > 0 && $nonce % 1048576 == 0) {
                    $hashRate = $hashRate + ((1048576 / (time() - $timeStamp)) - $hashRate) / ($hashRateCount + 1);
                    $hashRateCount += 1;

                    $timeStamp = time();
                    if ($timeout && ($timeStamp - $timeStart) > $timeout) {
                        return [
                            'hashRate' => $hashRate,
                            'hashRateCount' => $hashRateCount,
                            'nonce' => $nonce,
                            'extraNonce' => $extraNonce,
                            'blockTemplate' => null
                        ];
                    } else {
                        print_r([
                            'hashRate' => $hashRate,
                            'hashRateCount' => $hashRateCount,
                            'nonce' => $nonce,
                            'extraNonce' => $extraNonce,
                            'blockTemplate' => null
                        ]);
                    }
                }
                $nonce++;
            }
            $extraNonce += 1;
        }

        return [
            'hashRate' => $hashRate,
            'hashRateCount' => $hashRateCount,
            'nonce' => $nonce,
            'extraNonce' => $extraNonce,
            'blockTemplate' => null
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

    private function createCoinbaseTransaction($coinbaseScript, $address, $coinbaseValue, $height)
    {
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

    private function calculateMerkleRoot($tx_hashes)
    {
        # Convert list of ASCII hex transaction hashes into bytes
        $ntx_hashes = [];
        foreach ($tx_hashes as $tx_hash) {
            $ntx_hashes[] = strrev(hex2bin($tx_hash['hash']));
        }
        $tx_hashes = $ntx_hashes;


        // # Iteratively compute the merkle root hash
        while (count($tx_hashes) > 1) {
            # Duplicate last hash if the list is odd
            if (count($tx_hashes) % 2 != 0) {
                $tx_hashes[] = $tx_hashes[count($tx_hashes) - 1];
            }

            $tx_hashes_new = [];
            $count = floor(count($tx_hashes) / 2);
            for ($i = 0; $i < $count; $i++) {
                # Concatenate the next two
                $concat = array_shift($tx_hashes) . array_shift($tx_hashes);
                # Hash them
                $concat_hash = hex2bin(hash('sha256', hex2bin(hash('sha256', $concat))));
                # Add them to our working list
                $tx_hashes_new[] = $concat_hash;
            }
            $tx_hashes = $tx_hashes_new;
        }

        # Format the root in big endian ascii hex
        $tx_hash = bin2hex(strrev($tx_hashes[0]));
        return $tx_hash;
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