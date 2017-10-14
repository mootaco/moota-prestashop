<?php

$rootDir = str_replace(
    'modules/mootapay', '', dirname($_SERVER['SCRIPT_FILENAME'])
);

require_once $rootDir . '/config/config.inc.php';
require_once __DIR__ . '/library/autoload.php';
require_once __DIR__ . '/mootapay.php';

const AWAITING_CHECK_PAYMENT = 1;
const AWAITING_BANK_WIRE_PAYMENT = 10;
const ON_BACKORDER_NOT_PAID = 12;
const REMOTE_PAYMENT_ACCEPTED = 11;

$unfinishedStates = [
    AWAITING_CHECK_PAYMENT, AWAITING_BANK_WIRE_PAYMENT,
    ON_BACKORDER_NOT_PAID, REMOTE_PAYMENT_ACCEPTED,
];

if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    http_response_code(405);
    echo 'Only POST is allowed';
} else {
    $config = unserialize(Configuration::get(MOOTA_SDK_SETTINGS));

    Moota\SDK\Config::fromArray([
        'apiKey' => $config['apiKey'],
        'apiTimeout' => $config['apiTimeout'],
        'sdkMode' => strtolower( $config['sdkMode'] ),
        'serverAddress' => $config['serverAddress'],
    ]);
    
    $transactions = Moota\SDK\PushCallbackHandler::createDefault()->decode();

    // only CR
    foreach ($transactions as $trans) {
        if ($trans['type'] === 'CR') {
            $mootaInflows[] = $trans;
            $whereInflowAmounts[] = $trans['amount'];
        }
    }

    $query = (new DbQuery)
        ->select(
            '`id_order`, `current_state`, `total_paid_real`, `date_upd`'
            . ', `reference`'
        )
        ->from('orders')
        ->where(
            '`current_state` IN ('. implode(',', $unfinishedStates) . ')'
        )
        ->where('`total_paid_real` < 1')
    ;

    $orders = Db::getInstance()->executeS($query);

    header('Content-Type: application/json');
    die( json_encode( $orders, true ) );
}
