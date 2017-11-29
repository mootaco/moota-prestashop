<?php

require_once _PS_MODULE_DIR_ . '/mootapay/library/moota/moota-sdk/constants.php';

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class MootaCartUtil
{
    public static function addUniqueCode(&$cart) {
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
            $shouldAddUqCode = $shouldAddUqCode
                && isset( $cart['totals']['total']['amount'] )
                && $cart['totals']['total']['amount'] > 0
            ;
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
    }
}
