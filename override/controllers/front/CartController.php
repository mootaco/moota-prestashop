<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaCartUtil.php';

use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter;

class CartController extends \CartControllerCore
{
    public function initContent()
    {
        parent::initContent();

        $smarty = $this->context->smarty;
        $cart = $smarty->getTemplateVars('cart');

        MootaCartUtil::addUniqueCode($cart);

        $smarty->assign(['cart' => $cart]);
    }

    public function displayAjaxUpdate()
    {
        if (Configuration::isCatalogMode()) {
            return;
        }

        $productsInCart = $this->context->cart->getProducts();
        $updatedProducts = array_filter($productsInCart, array(
            $this, 'productInCartMatchesCriteria'
        ));

        list(, $updatedProduct) = each($updatedProducts);

        $productQuantity = $updatedProduct['quantity'];

        if (!$this->errors) {
            $cart = (new CartPresenter)->present($this->context->cart);

            MootaCartUtil::addUniqueCode($cart);

            $this->ajaxDie(json_encode([
                'success' => true,
                'id_product' => $this->id_product,
                'id_product_attribute' => $this->id_product_attribute,
                'quantity' => $productQuantity,
                'cart' => $cart,
            ]));
        } else {
            $this->ajaxDie(json_encode([
                'hasError' => true,
                'errors' => $this->errors,
                'quantity' => $productQuantity,
            ]));
        }
    }
}
