<?php

$json = json_decode(file_get_contents('blockheader.json'), true);
$blockHeader = MineBlockHeader::blockMakeHeader(
    $json["result"]["version"],
    $json["result"]["previousblockhash"],
    $json["result"]["merkleroot"],
    $json["result"]["curtime"] ?? $json["result"]["time"],
    $json["result"]["bits"],
    $json["result"]["nonce"],
);
$result = MineBlockHeader::mineBlock(
    '010000004944469562ae1c2c74d9a535e00b6f3e40ffbad4f2fda3895501b582000000007a06ea98cd40ba2e3288262b28638cec5337c1456aaf5eedc8e9e5a20f062bdf8cc16649ffff001d2bfee0a9',
    2850093634
);
echo "------------------------------MINDED BLOCK HEADER---------------------------------\n";
print_r($result);

class MineBlockHeader
{
    static public function mineBlock($blockHeader, $debugnonceStart = false, $extranonceStart = 0, $timeout = null)
    {
        $bits = substr($blockHeader, strlen($blockHeader) - 16, 8);
        $bits = self::hexToLittleEndian($bits);
        $targetHash = bin2hex(self::blockBits2Target($bits));
        $timeStart = time();
        $hashRate = 0;
        $hashRateCount = 0;
        $extraNonce = $extranonceStart;
        $blockHeader = hex2bin($blockHeader);
        while ($extraNonce < 0xffffffff) {
            $timeStamp = time();
            $nonce = $debugnonceStart ? $debugnonceStart : 0;
            while ($nonce <= 0xffffffff) {
                $blockHeader = substr($blockHeader, 0, 76) . pack("V", $nonce);
                $blockHash = self::blockComputeRawHash($blockHeader);
                $currenthash = self::hashToGmp($blockHash);
                $targHash = self::hashToGmp(hex2bin($targetHash));
                if (gmp_cmp($currenthash, $targHash) <= 0) {
                    $bHash = bin2hex($blockHash);
                    return [
                        'hashRate' => $hashRate,
                        'hashRateCount' => $hashRateCount,
                        'nonce' => $nonce,
                        'extraNonce' => $extraNonce,
                        'bHash' => $bHash
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
                            'bHash' => null
                        ];
                    } else {
                        print_r([
                            'hashRate' => $hashRate,
                            'hashRateCount' => $hashRateCount,
                            'nonce' => $nonce,
                            'extraNonce' => $extraNonce,
                            'bHash' => null
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
            'bHash' => null
        ];
    }
    public static function blockMakeHeader($version, $previousblockhash, $merkleroot, $time, $bits, $nonce)
    {
        $version = self::littleEndian($version);
        $prevBlockHash = self::SwapOrder($previousblockhash);
        $rootHash = self::SwapOrder($merkleroot);
        $time = self::littleEndian($time);
        $bits = self::littleEndian(hexdec($bits));
        $nonce = self::littleEndian($nonce);

        //concat it all
        $header_hex = $version . $prevBlockHash . $rootHash . $time . $bits . $nonce;
        return hex2bin($header_hex);
    }
    static private function SwapOrder($in)
    {
        $Split = str_split(strrev($in));
        $x = '';
        for ($i = 0; $i < count($Split); $i += 2) {
            $x .= $Split[$i + 1] . $Split[$i];
        }
        return $x;
    }

    //makes the littleEndian
    static private function littleEndian($value)
    {
        return implode(unpack('H*', pack("V*", $value)));
    }


    static private function blockComputeRawHash($header)
    {
        $hash1 = hash('sha256', $header, true);
        $hash2 = strrev(hash('sha256', $hash1, true));
        return $hash2;
    }

    static private function hexToLittleEndian($hex)
    {
        $bytes = str_split($hex, 2);
        $bytes = array_reverse($bytes);
        return implode($bytes);
    }

    static private function hashToGmp($hash)
    {
        $gmp = gmp_init('0x' . bin2hex($hash), 16);
        return $gmp;
    }

    static private function blockBits2Target($bits)
    {
        $bits = hex2bin($bits);
        $shift = ord($bits[0]) - 3;
        $value = substr($bits, 1);
        $target = $value . str_repeat("\x00", $shift);
        $target = str_repeat("\x00", 32 - strlen($target)) . $target;
        return $target;
    }
}