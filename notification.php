<?php

require_once _PS_ROOT_DIR_ . '/config/config.inc.php';
require_once __DIR__ . '/library/autoload.php';
require_once __DIR__ . '/mootapay.php';

if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    http_response_code(405);
    echo 'Only POST is allowed';
} else {
    $config = unserialize(Configuration::get(MOOTA_SETTINGS));

    Moota\SDK\Config::fromArray(array(
        MOOTA_API_KEY => $config[ MOOTA_API_KEY ],
        MOOTA_API_TIMEOUT => $config[ MOOTA_API_TIMEOUT ],
        MOOTA_ENV => strtolower( $config[ MOOTA_ENV ] ),
    ));

    $handler = Moota\SDK\PushCallbackHandler::createDefault()
        ->setOrderFetcher(new Moota\Prestashop\OrderFetcher)
        ->setOrderMatcher(new Moota\Prestashop\OrderMatcher)
        ->setOrderFulfiller(new Moota\Prestashop\OrderFulfiller)
        ->setDupeFinder(new Moota\Prestashop\DuplicateFinder)
    ;

    $statusData = $handler->handle();
    $responseCode = Moota\SDK\PushCallbackHandler::statusDataToHttpCode(
        $statusData
    );

    header('Content-Type: application/json');
    http_response_code($responseCode);

    echo json_encode($statusData);
}
