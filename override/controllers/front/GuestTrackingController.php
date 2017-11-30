<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class GuestTrackingController extends \GuestTrackingControllerCore
{
    protected $order;

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();

        MootaOverrideUtil::controllerSmartyUniqueCode(
            $this->context->smarty, 'order'
        );

        return $this->setTemplate('customer/guest-tracking');
    }
}
