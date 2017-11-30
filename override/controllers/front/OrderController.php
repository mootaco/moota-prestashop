<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class OrderController extends \OrderControllerCore
{
    public function initContent()
    {
        parent::initContent();

        MootaOverrideUtil::controllerSmartyUniqueCode(
            $this->context->smarty, 'cart'
        );
    }
}
