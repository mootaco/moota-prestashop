<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaCartUtil.php';

use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter;

class Ps_ShoppingcartOverride extends \Ps_Shoppingcart
{
    public function getWidgetVariables($hookName, array $params)
    {
        $wxVars = parent::getWidgetVariables($hookName, $params);

        MootaCartUtil::addUniqueCode($wxVars['cart']);

        return $wxVars;
    }

    public function renderModal(Cart $cart, $id_product, $id_product_attribute)
    {
        $cartData = (new CartPresenter)->present($cart);
        $product = null;

        foreach ($cartData['products'] as $p) {
            if ($p['id_product'] == $id_product && $p['id_product_attribute'] == $id_product_attribute) {
                $product = $p;
                break;
            }
        }

        MootaCartUtil::addUniqueCode($cartData);

        $this->smarty->assign(array(
            'product' => $product,
            'cart' => $cartData,
            'cart_url' => $this->context->link->getPageLink(
                'cart',
                null,
                $this->context->language->id,
                array('action' => 'show'),
                false,
                null,
                true
            ),
        ));

        return $this->fetch('module:ps_shoppingcart/modal.tpl');
    }
}
