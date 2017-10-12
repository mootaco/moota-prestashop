<?php

if ( strtolower($_SERVER['REQUEST_METHOD']) !== 'post' ) {
    http_response_code(405);
    echo 'Only POST is allowed';
} else {
    $rootDir = str_replace(
        'modules/mootapay', '', dirname($_SERVER['SCRIPT_FILENAME'])
    );

    require_once $rootDir . '/config/config.inc.php';
    require_once __DIR__ . '/library/moota-sdk/bootstrap.php';

    $config = unserialize( Configuration::get( self::MOOTA_SDK_SETTINGS ) );

    Moota\SDK\Config::fromArray([
        'apiKey' => $config['apiKey'],
        'apiTimeout' => $config['apiTimeout'],
        'sdkMode' => strtolower( $config['sdkMode'] ),
        'serverAddress' => $config['serverAddress'],
    ]);

    $transactions = Moota\SDK\PushCallbackHandler::createDefault()->decode();
}
