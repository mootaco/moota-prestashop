<?php namespace Moota\Prestashop;

use Moota\SDK\Config as MootaConfig;
use Moota\SDK\Contracts\Push\FindsDuplicate;

class DuplicateFinder implements FindsDuplicate
{
    protected function rpFormat($money, $withCurr = false)
    {
        $formatted = number_format(
            $money, 2, ',', '.'
        );

        return ($withCurr ? 'Rp. ' : '') . $formatted;
    }

    public function findDupes(array &$mootaInflows, array &$orders)
    {
        $dupes = array();
        $dupedOrderIds = array();
        $idsToRemove = array();
        $dupedCount = 0;

        // for each inflow, find all orders that has the same total
        foreach ($mootaInflows as $inflow) {
            if (
                !empty($inflow['tags'])
                && !empty($inflow['tags']['order_id'])
            ) {
                continue;
            }

            $dupeKey = $inflow['amount'] . '';

            $dupes[ $dupeKey ] = array_filter($orders, function ($order) use (
                $inflow, &$dupedOrderIds, $dupeKey
            ) {
                /** @var \Order $order */
                $isDuped =
                    (float) $order->total_paid_real === (float) $inflow['amount'];

                // group ids from orders with the same amount
                if ($isDuped) {
                    if ( ! isset($dupedOrderIds[ $dupeKey ]) ) {
                        $dupedOrderIds[ $dupeKey ] = array();
                    }

                    $dupedOrderIds[ $dupeKey ][] = $order->id;
                }

                return $isDuped;
            });
        }

        $message = '';

        foreach ($dupedOrderIds as $amount => $orderIds) {
            if (count($orderIds) <= 1) {
                continue;
            }

            $idsToRemove = array_merge($idsToRemove, $orderIds);

            $dupedCount += count($orderIds) - 1;

            $message .= PHP_EOL . sprintf(
                    'Ada order yang sama untuk nominal %s',
                    $this->rpFormat( (float) $amount, true )
                ) . PHP_EOL;

            $message .= sprintf(
                    'Berikut Order ID yang bersangkutan: %s',
                    PHP_EOL . '- ' . implode(PHP_EOL . '- ', $orderIds)
                )
                . PHP_EOL . PHP_EOL;
        }

        if ($dupedCount > 0) {
            $message = 'Hai Admin.' . PHP_EOL . PHP_EOL . $message;
            $message .= 'Mohon dicek manual.' . PHP_EOL
                . PHP_EOL;

            if ( MootaConfig::isLive() ) {
                \Mail::Send(
                    // default language id
                    (int) \Configuration::get('PS_LANG_DEFAULT'),

                    // email template file to be use
                    _PS_MODULE_DIR_
                        . '/mootapay/mails/id/empty.txt',

                    // email subject
                    'Ada nominal order yang sama - Moota',

                    // template vars
                    array('body' => $message),

                    // receiver email address
                    \Configuration::get('PS_SHOP_EMAIL'),
                    NULL, NULL, NULL
                );
            }
        }

        // change the duplicates in $orders into nulls
        foreach ($orders as $idx => $order) {
            if ( !empty($order) && in_array($order->id, $idsToRemove) ) {
                $orders[ $idx ] = null;
            }
        }

        // filter out all nulls;
        $orders = array_filter($orders);

        return $orders;
    }
}
