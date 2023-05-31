<?php

require_once 'bitcoinBlockSubmission.php';
require_once 'bitcoinBlockTemplate.php';
require_once 'bitcoinRpcClient.php';
require_once 'bitcoinMiner.php';

$rpcUrl = 'http://localhost:18443';
$rpcUser = 'user';
$rpcPass = 'pass';
$blockTemplate = new BitcoinBlockTemplate(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$blockSubmission = new BitcoinBlockSubmission(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$miner = new BitcoinMiner($blockTemplate, $blockSubmission);

$coinbaseMessage = 'Mined by RafaelFernandes';
$address = '1rafaeLAdmgQhS2i4BR1tRst666qyr9ut';
$extranonceStart = 0;
$timeout = false; // time in seconds to get a new blocktemplate or false to infinitely mine the same block until mined

$mined = false;

while ($mined == false) {
    // $blockTemplate = $this->blockTemplate->getBlockTemplate();
    $blockTemplate = json_decode(file_get_contents('block.json'), true);
    echo "Mining block template, height " . $blockTemplate['height'] . "\n";
    $result = $miner->mineBlock($blockTemplate, $coinbaseMessage, $address, $extranonceStart, $timeout);

    $result['hashRatePerSeconds'] = ($result['hashRate'] / 1000.0) . " KH/s\n";
    print_r($result);
    if (empty($result['nonce'])) {
        $mined = true;
        break;
    }
}

var_dump($result);