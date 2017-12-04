<?php

require_once _PS_MODULE_DIR_ . '/mootapay/library/autoload.php';

use Moota\Prestashop\OrderFetcher;
use Moota\Prestashop\OrderMatcher;
use Moota\Prestashop\OrderFulfiller;
use Moota\Prestashop\DuplicateFinder;
use Moota\SDK\Config as MootaConfig;
use Moota\SDK\PushCallbackHandler;

class MootapayPushModuleFrontController extends \ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;

        parent::initContent();
    }

    public function postProcess()
    {
        header('Content-Type: application/json');

        if (strtolower($_SERVER['REQUEST_METHOD']) !== 'post') {
            http_response_code(405);

            $this->ajaxDie( json_encode( array(
                'status' => 'error',
                'message' => 'POST only'
            ) ) );

            return;
        }

        $config = unserialize(\Configuration::get(MOOTA_SETTINGS));

        MootaConfig::fromArray(array(
            MOOTA_API_KEY => $config[ MOOTA_API_KEY ],
            MOOTA_API_TIMEOUT => $config[ MOOTA_API_TIMEOUT ],
            MOOTA_ENV => strtolower( $config[ MOOTA_ENV ] ),
        ));

        $handler = PushCallbackHandler::createDefault()
            ->setOrderFetcher(new OrderFetcher)
            ->setOrderMatcher(new OrderMatcher)
            ->setOrderFulfiller(new OrderFulfiller)
            ->setDupeFinder(new DuplicateFinder)
        ;

        $statusData = $handler->handle();

        $responseCode = PushCallbackHandler::statusDataToHttpCode(
            $statusData
        );

        http_response_code($responseCode);

        $this->ajaxDie(json_encode($statusData));
    }
}
