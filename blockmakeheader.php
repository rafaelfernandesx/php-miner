<?php

function blockMakeHeader($version, $previousblockhash, $merkleroot, $curtime, $bits, $nonce)
{
    $header = "";
    $header .= pack("V", $version);
    $previousBlockHash = hex2bin($previousblockhash);
    $header .= strrev($previousBlockHash);
    $merkleRootHash = hex2bin($merkleroot);
    $header .= strrev($merkleRootHash);
    $header .= pack("V", $curtime);
    $targetBits = hex2bin($bits);
    $header .= strrev($targetBits);
    $header .= pack("V", $nonce);
    return $header;
}

$hash = "000000004ebadb55ee9096c9a2f8880e09da59c0d68b1c228da88e48844a1485";
$version =1;
$previousblockhash= "0000000082b5015589a3fdf2d4baff403e6f0be035a5d9742c1cae6295464449";
$merkleroot ="df2b060fa2e5e9c8ed5eaf6a45c13753ec8c63282b2688322eba40cd98ea067a";
$time =1231470988;
$nonce= 2850094635;
$bits ="1705ae3a";

echo $header = blockMakeHeader($version, $previousBlockHash, $merkleroot, $time, $nonce, $bits);
echo "\n";
echo bin2hex($header);
echo "\n";
echo implode(', 0x', str_split(bin2hex($header), 2)) ."\n";
