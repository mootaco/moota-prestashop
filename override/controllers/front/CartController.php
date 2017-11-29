<?php

// This file will be copied to <PS_ROOT>/<OVERRIDE_PATH>
// so this require path only makes sense over there
require_once __DIR__
    . '/../../../modules/mootapay/library/moota/moota-sdk/constants.php';

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class CartController extends \CartControllerCore
{
    public function initContent()
    {
        parent::initContent();

        $smarty = $this->context->smarty;
        $cart = $smarty->getTemplateVars('cart');

        $shouldAddUqCode = count($cart['products']) > 0;

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode && isset($cart['totals']);
        }

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode && isset(
                $cart['totals']['total']
            );
        }

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode && isset(
                $cart['totals']['total']['amount']
            ) && $cart['totals']['total']['amount'] > 0;
        }

        $config = unserialize( \Configuration::get( MOOTA_SETTINGS ) );
        if ( $shouldAddUqCode && $config[ MOOTA_USE_UQ_CODE ] ) {
            $uniqueCode = mt_rand(
                $config[ MOOTA_UQ_MIN ],
                $config[ MOOTA_UQ_MAX ]
            );

            $cart['subtotals']['moota_uq'] = array(
                'type' => 'payment',
                'label' => $config['uqCodeLabel'],
                'amount' => $uniqueCode,
                'value' => (new PriceFormatter)->format($uniqueCode),
            );
        } else {
            if (isset($cart['subtotals']['moota_uq'])) {
                unset($cart['subtotals']['moota_uq']);
            }
        }

        $smarty->assign(['cart' => $cart]);
    }
}
