<?php namespace Moota\Prestashop;

use Moota\SDK\Contracts\Push\FullfilsOrder;

class OrderFulfiller implements FullfilsOrder
{
    public function fullfil($order)
    {
        $result = $order['orderModel']->addOrderPayment(
            $order['amount'],
            'mootapay',
            $order['transactionId'],
            $order['currency'],
            date('Y-m-d H:i:s'),
            null // order_invoice
        );

        return $result !== null || $result === true;
    }
}
