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

        if ($paymentAdded && !empty($orderStateId)) {
            $history = new \OrderHistory();
            $history->id_order = $orderId;
            $history->id_employee = 0;

            $orderModel->current_state = $orderStateId;
            $orderModel->update();

            $useExistingPayment = !$orderModel->hasInvoice() ? true : false;

            $history->changeIdOrderState(
                $orderStateId,
                $orderModel,
                $useExistingPayment
            );

            // MOOTA_COMPLETE_SENDMAIL
            $addMethod = $config[ MOOTA_COMPLETE_SENDMAIL ]
                ? 'addWithEmail' : 'add';
            $history->{ $addMethod }();

            (new \Moota\SDK\Api)->linkOrderWithMoota(
                $order['mootaId'], $order['orderId']
            );
        }

        return $paymentAdded;
    }
}
