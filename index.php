<?php

require_once 'bitcoinBlockSubmission.php';
require_once 'bitcoinBlockTemplate.php';
require_once 'bitcoinRpcClient.php';
require_once 'bitcoinMiner.php';

$rpcUrl = 'http://localhost:8332';
$rpcUser = 'user';
$rpcPass = 'password';
$blockTemplate = new BitcoinBlockTemplate(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$blockSubmission = new BitcoinBlockSubmission(new BitcoinRpcClient($rpcUrl, $rpcUser, $rpcPass));
$miner = new BitcoinMiner($blockTemplate, $blockSubmission);

$coinbaseMessage = 'abc';
$address = '2N4ktXsHBMJeHmHP2wgcdvJV5S3atGETBKx';
$extranonceStart = 0;
$timeout = 60; // 60 segundos

$result = $miner->mineBlock($coinbaseMessage, $address, $extranonceStart, $timeout);

var_dump($result);