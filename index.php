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

$coinbaseMessage = 'RafaelFernandes';
$address = '1rafaeLAdmgQhS2i4BR1tRst666qyr9ut';
$timeout = 120; // time in seconds to get a new blocktemplate or false to infinitely mine the same block until mined

$mined = false;

while ($mined == false) {
    try {
        $block = $blockTemplate->getBlockTemplate();
        if (!empty($block)) {
            echo "Mining block template, height " . $block['height'] . "\n";
            $result = $miner->mineBlock($block, $coinbaseMessage, $address, $timeout);

            print_r($result);
            if (!empty($result['blockTemplate'])) {
                $mined = true;
                break;
            }
            sleep(1);
        }
    } catch (Exception $e) {
        print($e);
    }
}

var_dump($result);