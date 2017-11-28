<?php

use Moota\Prestashop\Presenter\CartPresenter;

class CartController extends \CartControllerCore
{
    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        if (
            Configuration::isCatalogMode()
            && Tools::getValue('action') === 'show'
        ) {
            Tools::redirect('index.php');
        }

        $cart = (new CartPresenter)->present(
            $this->context->cart, $shouldSeparateGifts = true
        );

        $this->context->smarty->assign([
            'cart' => $cart,
            'static_token' => Tools::getToken(false),
        ]);

        if (count($cart['products']) > 0) {
            $this->setTemplate('checkout/cart');
        } else {
            $this->context->smarty->assign(array(
                'allProductsLink' => $this->context->link->getCategoryLink(
                    Configuration::get('PS_HOME_CATEGORY')
                ),
            ));

            $this->setTemplate('checkout/cart-empty');
        }

        parent::initContent();
    }
}
