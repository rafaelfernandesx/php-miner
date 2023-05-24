<?php

require_once 'bitcoinBlockSubmission.php';
require_once 'bitcoinBlockTemplate.php';
require_once 'bitcoinRpcClient.php';
require_once 'bitcoinMiner.php';

$rpcUrl = 'http://localhost:8332';
$rpcUser = 'user';
$rpcPass = 'pass';
$blockTemplate = new BitcoinBlockTemplate(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$blockSubmission = new BitcoinBlockSubmission(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$miner = new BitcoinMiner($blockTemplate, $blockSubmission);

$coinbaseMessage = 'Mined by RafaelFernandes';
$address = '1rafaeLAdmgQhS2i4BR1tRst666qyr9ut';
$extranonceStart = 0;
$timeout = 10; // time in seconds to get a new blcktemplate

$mined = false;

echo "Mining...\n";
while ($mined == false) {
    $result = $miner->mineBlock($coinbaseMessage, $address, $extranonceStart, $timeout);
    if ($result['nonce'] == null) {
        var_dump($result);
        $mined = true;
        break;
    }
}

var_dump($result);