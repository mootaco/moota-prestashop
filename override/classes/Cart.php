<?php

require_once _PS_MODULE_DIR_ . '/mootapay/presta/MootaOverrideUtil.php';

class Cart extends \CartCore
{
    /**
     * This function returns the total cart amount
     *
     * @param bool $with_taxes With or without taxes
     * @param int  $type      Total type enum
     *                        - Cart::ONLY_PRODUCTS
     *                        - Cart::ONLY_DISCOUNTS
     *                        - Cart::BOTH
     *                        - Cart::BOTH_WITHOUT_SHIPPING
     *                        - Cart::ONLY_SHIPPING
     *                        - Cart::ONLY_WRAPPING
     *                        - Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING
     *                        - Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING
     * @param array $products
     * @param int   $id_carrier
     * @param bool $use_cache Allow using cache of the method CartRule::getContextualValue
     *
     * @return float Order total
     */
    public function getOrderTotal(
        $with_taxes = true,
        $type = Cart::BOTH,
        $products = null,
        $id_carrier = null,
        $use_cache = true
    ) {
        $computePrecision = $this->configuration
            ->get('_PS_PRICE_COMPUTE_PRECISION_');

        $orderTotal = (float) parent::getOrderTotal(
            $with_taxes, $type, $products, $id_carrier, $use_cache
        );

        if ($orderTotal === 0) {
            return $orderTotal;
        }

        $uniqueCode = (float) MootaOverrideUtil::calculateUniqueCode(
            $orderTotal > 0
        );
        $orderTotal += $uniqueCode;

        return Tools::ps_round($orderTotal, $computePrecision);
    }
}
