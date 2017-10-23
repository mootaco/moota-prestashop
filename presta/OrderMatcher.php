<?php namespace Moota\Prestashop;

use Moota\SDK\Contracts\MatchPayments;

class OrderMatcher implements MatchPayments
{
    /**
     * Matches payments sent by Moota to available transactions in storage.
     * Plugin specific implementation.
     *
     * @param array $payments
     * @param array $orders
     *
     * @return array
     */
    public function match(array $payments, array $orders)
    {
        $matchedPayments = [];

        $query = (new \DbQuery)
            ->select('`id_currency`')
            ->from('currency')
            ->where("`iso_code` = 'IDR'")
        ;

        $guardedPayments = $payments;

        $currency = \Db::getInstance()->getRow($query)['id_currency'];
        $currency = new \Currency($currency);

        if ( ! empty($orders) && count($orders) > 0 ) {
            // match whmcs invoice with moota transactions
            // TODO: apply unique code transformation over here
            foreach ($orders as $order) {
                $transAmount = (int) $order->total_paid;
                $tmpPayment = null;

                foreach ($guardedPayments as $i => $mootaInflow) {
                    if (empty($guardedPayments[ $i ])) continue;

                    if ( ( (int) $mootaInflow['amount'] ) === $transAmount ) {
                        $tmpPayment = $mootaInflow;

                        $guardedPayments[ $i ] = null;

                        break;
                    }
                }

                if (!empty($tmpPayment)) {
                    $matchedPayments[]  = [
                        // transactionId:
                        //   { orderId }-{ moota:id }-{ moota:account_number }
                        'transactionId' => implode('-', [
                            $order->id,
                            $tmpPayment['id'],
                            $tmpPayment['account_number']
                        ]),
    
                        'orderId' => $order->id,
                        'mootaId' => $tmpPayment['id'],
                        'mootaAccNo' => $tmpPayment['account_number'],
                        'amount' => $tmpPayment['amount'],
                        'mootaAmount' => $tmpPayment['amount'],
                        'invoiceAmount' => $order->total_paid,
                        'currency' => $currency,
                        'orderModel' => $order,
                    ];
                }
            }
        }

        return $matchedPayments;
    }
}
