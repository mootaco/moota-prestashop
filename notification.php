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

$unfinishedStates = array(
    AWAITING_CHECK_PAYMENT, AWAITING_BANK_WIRE_PAYMENT,
    ON_BACKORDER_NOT_PAID, REMOTE_PAYMENT_ACCEPTED,
);

if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
    http_response_code(405);
    echo 'Only POST is allowed';
} else {
    $config = unserialize(Configuration::get(MOOTA_SDK_SETTINGS));

    Moota\SDK\Config::fromArray(array(
        MOOTA_SDK_API_KEY => $config[ MOOTA_SDK_API_KEY ],
        MOOTA_SDK_API_TIMEOUT => $config[ MOOTA_SDK_API_TIMEOUT ],
        MOOTA_SDK_ENV => strtolower( $config[ MOOTA_SDK_ENV ] ),
    ));

    $mootaInflows = Moota\SDK\PushCallbackHandler::decodeInflows();

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
    
    if ( ! empty($invoices) && count($invoices) > 0 ) {
        // match whmcs invoice with moota transactions
        // apply unique code transformation over here
        foreach ($invoices as $invoice) {
            $transAmount = (int) str_replace('.00', '', $invoice->total . '');
            $tmpPayment = null;
    
            foreach ($mootaInflows as $mootaInflow) {
                if ($mootaInflow['amount'] === $transAmount) {
                    $tmpPayment = $mootaInflow;
                    break;
                }
            }
    
            $payments[]  = [
                // transactionId:
                //   { invoiceId }-{ moota:id }-{ moota:account_number }
                'transactionId' => implode('-', [
                    $invoice->id, $tmpPayment['id'], $tmpPayment['account_number']
                ]),
                'invoiceId' => $invoice->id,
                'mootaId' => $tmpPayment['id'],
                'mootaAccNo' => $tmpPayment['account_number'],
                'amount' => $tmpPayment['amount'],
                'mootaAmount' => $tmpPayment['amount'],
                'invoiceAmount' => $invoice->total,
            ];
        }
    
        $pushReplyData['data'] = [
            'dataCount' => count($transactions),
            'inflowCount' => count($mootaInflows),
            'payments' => $payments,
        ];
    
        if ( count($payments) > 0 ) {
            // finally add payment and log to gateway logs
            foreach ($payments as $payment) {
            }
    
            $pushReplyData['status'] = 'ok';
        } else {
            $pushReplyData['status'] = 'not-ok';
            $pushReplyData['status'] = 'No unpaid invoice matches current push'
                . ' data';
        }
    } else {
        $pushReplyData['status'] = 'not-ok';
        $pushReplyData['error'] = 'No unpaid invoice found';
    }
    
    header('Content-Type: application/json');
    
    die( json_encode( $pushReplyData ) );
}
