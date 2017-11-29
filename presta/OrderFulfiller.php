<?php namespace Moota\Prestashop;

require_once __DIR__ . '/../constants.php';

use Moota\SDK\Contracts\Push\FulfillsOrder;

class OrderFulfiller implements FulfillsOrder
{
    public function fulfill($order)
    {
        $config = unserialize( \Configuration::get( MOOTA_SETTINGS ) );
        $orderStateId = (int) $config[ MOOTA_COMPLETED_STATUS ];

        /** @var \OrderCore $orderModel */
        $orderModel = $order['orderModel'];
        $orderId = (int) $orderModel->id;

        $paymentAdded = $orderModel->addOrderPayment(
            $order['amount'],
            'mootapay',
            $order['transactionId'],
            $order['currency'],
            date('Y-m-d H:i:s'),
            null // order_invoice
        );

        $paymentAdded = $paymentAdded !== null || $paymentAdded === true;

        if ($paymentAdded && empty($orderStateId)) {
            $history = new \OrderHistory();
            $history->id_order = $orderId;
            $history->id_employee = 0;

            $history->changeIdOrderState($orderStateId, $orderModel);

            $res = \Db::getInstance()->getRow("
                SELECT `invoice_number`, `invoice_date`
                  , `delivery_number` , `delivery_date`
                FROM `". _DB_PREFIX_ ."orders`
                WHERE `id_order` = {$orderId}
            ");

            $orderModel->invoice_date = $res['invoice_date'];
            $orderModel->invoice_number = $res['invoice_number'];
            $orderModel->delivery_date = $res['delivery_date'];
            $orderModel->delivery_number = $res['delivery_number'];
            $orderModel->update();

            // MOOTA_COMPLETE_SENDMAIL
            if ( $config[ MOOTA_COMPLETE_SENDMAIL ] ) {
                $history->addWithEmail();
            } else {
                $history->add();
            }

            (new \Moota\SDK\Api)->linkOrderWithMoota(
                $order['mootaId'], $order['orderId']
            );
        }

        return $paymentAdded;
    }
}
