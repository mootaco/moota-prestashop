<?php namespace Moota\Prestashop\Presenter;


use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter as PSCartPresenter;
use PrestaShop\PrestaShop\Core\Foundation\Templating\PresenterInterface;

class CartPresenter extends PSCartPresenter implements PresenterInterface
{
    protected $priceFormatter;
    protected $link;
    protected $translator;
    protected $imageRetriever;
    protected $taxConfiguration;

    /**
     * @param $cart
     * @param bool $shouldSeparateGifts
     * @return array
     * @throws \Exception
     */
    public function present($cart, $shouldSeparateGifts = false)
    {
        $config = unserialize( Configuration::get( MOOTA_SETTINGS ) );
        $cart = parent::present($cart, $shouldSeparateGifts);

        if ( $config[ MOOTA_USE_UQ_CODE ] ) {
            $uniqueCode = mt_rand(
                $config[ MOOTA_UQ_MIN ],
                $config[ MOOTA_UQ_MAX ]
            );

            $cart['subtotals']['moota_uq'] = array(
                'type' => 'payment',
                'label' => $config['uqCodeLabel'],
                'amount' => $uniqueCode,
                'value' => $this->priceFormatter->format($uniqueCode),
            );
        }

        return $cart;
    }
}
