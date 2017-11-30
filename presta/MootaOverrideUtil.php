<?php

require_once _PS_MODULE_DIR_ . '/mootapay/library/moota/moota-sdk/constants.php';
require_once _PS_MODULE_DIR_ . '/mootapay/constants.php';

use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;

class MootaOverrideUtil
{
    public static function calculateUniqueCode($shouldAddUqCode)
    {
        $cookie = \ContextCore::getContext()->cookie;

        $config = unserialize( \Configuration::get( MOOTA_SETTINGS ) );

        $shouldAddUqCode = $shouldAddUqCode && $config[ MOOTA_USE_UQ_CODE ];
        $uniqueCode = 0;

        if ($shouldAddUqCode) {
            if (
                isset($subTotalsContainer['subtotals'])
                && isset($subTotalsContainer['subtotals']['moota_uq'])
            ) {
                return;
            }

            if ( isset($cookie->{ MOOTA_UQ }) ) {
                $uniqueCode = (int) $cookie->{ MOOTA_UQ };
            } else {
                $uniqueCode = (int) mt_rand(
                    $config[ MOOTA_UQ_MIN ],
                    $config[ MOOTA_UQ_MAX ]
                );

                $cookie->{ MOOTA_UQ } = $uniqueCode;
            }
        }

        return $uniqueCode;
    }

    public static function addUniqueCode(&$subTotalsContainer) {
        $shouldAddUqCode = count($subTotalsContainer['products']) > 0;

        $cookie = \ContextCore::getContext()->cookie;

        $config = unserialize( \Configuration::get( MOOTA_SETTINGS ) );

        $shouldAddUqCode = $shouldAddUqCode && $config[ MOOTA_USE_UQ_CODE ];

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode && isset($subTotalsContainer['totals']);
        }

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode && isset(
                    $subTotalsContainer['totals']['total']
                );
        }

        if ($shouldAddUqCode) {
            $shouldAddUqCode = $shouldAddUqCode
                && isset( $subTotalsContainer['totals']['total']['amount'] )
                && $subTotalsContainer['totals']['total']['amount'] > 0
            ;
        }

        $uniqueCode = self::calculateUniqueCode($shouldAddUqCode);

        if ($shouldAddUqCode) {
            $subTotalsContainer['subtotals']['moota_uq'] = array(
                'type' => 'payment',
                'label' => $config['uqCodeLabel'],
                'amount' => $uniqueCode,
                'value' => (new PriceFormatter)->format($uniqueCode),
            );
        }

        if (
            (
                !$shouldAddUqCode
                && isset($subTotalsContainer['subtotals']['moota_uq'])
            )
            || count($subTotalsContainer['products']) < 1
        ) {
            unset($subTotalsContainer['subtotals']['moota_uq']);
            unset($cookie->{ MOOTA_UQ });
        }
    }

    public static function controllerSmartyUniqueCode(
        $smarty, $smartyTplKey
    )
    {
        $subTotalContainer = $smarty->getTemplateVars($smartyTplKey);

        MootaOverrideUtil::addUniqueCode($subTotalContainer);

        $smarty->assign(array($smartyTplKey => $subTotalContainer));
    }
}
