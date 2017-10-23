<?php

header('Content-Type: application/json');

$rootDir = str_replace(
    'modules/mootapay', '', dirname($_SERVER['SCRIPT_FILENAME'])
);

require_once $rootDir . '/config/config.inc.php';
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
        ->setTransactionFetcher(new Moota\Prestashop\OrderFetcher)
        ->setPaymentMatcher(new Moota\Prestashop\OrderMatcher)
    ;

    $payments = $handler->handle();
    $statusData = array(
        'status' => 'not-ok', 'error' => 'No matching order found'
    );

    if ( count( $payments ) > 0 ) {
        foreach ($payments as $payment) {
            // Taken from prestashop core: `AdminOrdersController#postProcess`,
            // inside `elseif (submitAddPayment)`,
            // find for: `$order->addOrderPayment`
            $payment['orderModel']->addOrderPayment(
                $payment['amount'],
                'mootapay',
                $payment['transactionId'],
                $payment['currency'],
                date('Y-m-d H:i:s'),
                null // order_invoice
            );
        }

        $statusData = array('status' => 'ok', 'count' => count($payments));
    }

    header('Content-Type: application/json');
    echo json_encode( $statusData );
}
