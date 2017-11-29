<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaCartUtil.php';

class OrderController extends \OrderControllerCore
{
    public function initContent()
    {
        parent::initContent();

        $smarty = $this->context->smarty;
        $cart = $smarty->getTemplateVars('cart');

        MootaCartUtil::addUniqueCode($cart);

        $smarty->assign(['cart' => $cart]);
    }
}
