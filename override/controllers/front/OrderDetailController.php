<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class OrderDetailController extends \OrderDetailControllerCore
{
    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        MootaOverrideUtil::controllerSmartyUniqueCode(
            $this->context->smarty, 'order'
        );

        $this->setTemplate('customer/order-detail');
    }
}
