<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class HistoryController extends \HistoryControllerCore
{
    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        $orders = $this->getTemplateVarOrders();

        foreach ($orders as $order) {
            MootaOverrideUtil::addUniqueCode($order);
        }

        $this->context->smarty->assign(array('orders' => $orders));

        $this->setTemplate('customer/history');
    }
}
