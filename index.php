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
$timeout = 120; // time in seconds to get a new blocktemplate or false to infinitely mine the same block until mined

$mined = false;

while ($mined == false) {
    try {
        $block = $blockTemplate->getBlockTemplate();
        // $blockTemplate = json_decode(file_get_contents('block.json'), true);
        if (!empty($block)) {
            echo "Mining block template, height " . $block['height'] . "\n";
            $result = $miner->mineBlock($block, $coinbaseMessage, $address, $block['extraNonce'] ?? $extranonceStart, $timeout, $block['nonce'] ?? 0);

            $result['hashRatePerSeconds'] = ($result['hashRate'] / 1000.0) . " KH/s\n";
            print_r($result);
            if (empty($result['nonce'])) {
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