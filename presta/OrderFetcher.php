<?php namespace Moota\Prestashop;

use Moota\SDK\Contracts\Push\FetchesOrders;

class OrderFetcher implements FetchesOrders
{
    const AWAITING_CHECK_PAYMENT = 1;
    const AWAITING_BANK_WIRE_PAYMENT = 10;
    const REMOTE_PAYMENT_ACCEPTED = 11;
    const ON_BACKORDER_NOT_PAID = 12;

    protected $unfinishedStates = array(
        self::AWAITING_CHECK_PAYMENT, self::AWAITING_BANK_WIRE_PAYMENT,
        self::REMOTE_PAYMENT_ACCEPTED, self::ON_BACKORDER_NOT_PAID,
    );

    /**
     * Fetches currently available transaction in storage.
     * Plugin specific implementation.
     *
     * @param array $inflowAmounts
     *
     * @return array
     */
    public function fetch(array $inflowAmounts)
    {
        $query = (new \DbQuery)
            ->select('`id_order`')
            ->from('orders')
            ->where('`current_state` IN ('
                . implode(',', $this->unfinishedStates)
            . ')')
            ->where('`total_paid_real` < `total_paid`')
        ;

        if (!empty($inflowAmounts)) {
            $query = $query->where('`total_paid` IN (' . implode(
                ',', $inflowAmounts
            ) . ')');
        }

        $tmp = \Db::getInstance()->executeS($query);

        $orders = [];

        foreach ($tmp as $order) {
            $orders[] = new \Order($order['id_order']);
        }

        return $orders;
    }
}
