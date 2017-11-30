<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class Ps_ShoppingcartOverride extends \Ps_Shoppingcart
{
    public function getWidgetVariables($hookName, array $params)
    {
        $wxVars = parent::getWidgetVariables($hookName, $params);

        MootaOverrideUtil::addUniqueCode($wxVars['cart']);

        return $wxVars;
    }

    public function renderModal(Cart $cart, $id_product, $id_product_attribute)
    {
        parent::renderModal($cart, $id_product, $id_product_attribute);

        MootaOverrideUtil::controllerSmartyUniqueCode(
            $this->context->smarty, 'cart'
        );

        return $this->fetch('module:ps_shoppingcart/modal.tpl');
    }
}
