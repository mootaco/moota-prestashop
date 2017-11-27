<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/library/moota/moota-sdk/constants.php';

class MootaPay extends PaymentModule
{
    protected $hooks = array('displayCheckoutSubtotalDetails');

    public function __construct()
    {
        $this->name = 'mootapay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Moota';
        $this->bootstrap = true;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Moota');
        $this->description = $this->l('Moota Payment Gateway.');

        $this->confirmUninstall = $this->l(
            'Are you sure you want to uninstall?'
        );
    }

    public function install()
    {
        if ( ! Configuration::get( MOOTA_SETTINGS ) ) {
            Configuration::updateValue(MOOTA_SETTINGS, serialize([
                MOOTA_API_KEY => null,
                MOOTA_API_TIMEOUT => 30,
                MOOTA_ENV => 'production',
                MOOTA_USE_UQ_CODE => false,
                MOOTA_UQ_PREFFIX => 0,
                MOOTA_UQ_SUFFIX => 0,
            ]));
        }


        $installed = parent::install();

        foreach ($this->hooks as $hookName) {
            $installed = $installed && $this->registerHook($hookName);
        }

        return $installed;
    }

    public function uninstall()
    {
        Configuration::deleteByName(MOOTA_SETTINGS);
        parent::uninstall();

        return true;
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit' . $this->name)) {
            $configValues = [
                MOOTA_API_KEY => strval(
                    Tools::getValue( MOOTA_API_KEY )
                ),
                MOOTA_API_TIMEOUT => strval(
                    Tools::getValue( MOOTA_API_TIMEOUT )
                ),
                MOOTA_ENV => strval( Tools::getValue( MOOTA_ENV ) ),
                MOOTA_USE_UQ_CODE => strval(
                    Tools::getValue( MOOTA_USE_UQ_CODE )
                ),
                MOOTA_UQ_PREFFIX => strval(
                    Tools::getValue( MOOTA_UQ_PREFFIX )
                ),
                MOOTA_UQ_SUFFIX => strval(
                    Tools::getValue( MOOTA_UQ_SUFFIX )
                ),
            ];

            Configuration::updateValue(
                MOOTA_SETTINGS, serialize($configValues)
            );

            $output .= $this->displayConfirmation(
                $this->l('Settings updated')
            );
        }

        return $output . $this->displayForm();
    }

    public function displayForm()
    {
        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => 'API Key',
                    'desc' => $this->l('Dapatkan API Key melalui: ')
                        . '<a href="https://app.moota.co/settings?tab=api" '
                        . 'target="_new">https://app.moota.co/settings?'
                        . 'tab=api</a>'
                    ,
                    'name' => MOOTA_API_KEY,
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'text',
                    'label' => 'API Timeout',
                    'name' => MOOTA_API_TIMEOUT,
                    'size' => 20,
                    'required' => true
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Environment'),
                    'desc' => $this->l('Only change when asked by Moota'),
                    'name' => MOOTA_ENV,
                    'size' => 20,

                    // If set to true, this option must be set.
                    'required' => true,

                    // The content of the 'class' attribute of the <label> tag
                    // for the <input> tag.
                    'class'     => 'col-xs-2',
                    'is_bool'   => false, 
                    'values' => array(
                        array(
                            'id' => MOOTA_ENV . '_production',
                            'value' => 'production',
                            'label' => 'Live',
                        ), array(
                            'id' => MOOTA_ENV . '_testing',
                            'value' => 'testing',
                            'label' => 'Sandbox',
                        )
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l(
                        'Moota Push Notification URL (display-only)'
                    ),
                    'name' => 'PUSH_NOTIF_URL',
                    'desc' => $this->l(
                        'Masuk halaman edit bank di moota > tab notifikasi '
                        . '> edit "API Push Notif" '
                        . '> lalu masukkan url ini'
                    ),
                    'size' => 20,
                    'readonly' => true,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex
            . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
         
        // Title and toolbar
        $helper->title = $this->displayName;

        // false -> remove toolbar
        $helper->show_toolbar = true;

        // yes - > Toolbar is always visible on the top of the screen.
        $helper->toolbar_scroll = true;

        $helper->submit_action = 'submit' . $this->name;

        $helper->toolbar_btn = [
            'save' => [
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex
                    . '&configure=' . $this->name
                    . '&save' . $this->name
                    . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            ],
            'back' => [
                'href' => AdminController::$currentIndex
                    . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            ]
        ];

        $paths = explode('/', dirname( $_SERVER['SCRIPT_NAME'] ));
        array_pop($paths);
        $paths = implode('/', $paths);
        $baseUri = $_SERVER['SERVER_NAME'] . realpath(
            dirname( $_SERVER['REQUEST_URI'] ) . '/..'
        ) . $paths;
        unset($paths);

        // Load current value
        $config = unserialize( Configuration::get( MOOTA_SETTINGS ) );

        $helper->fields_value = array(
            MOOTA_API_KEY => $config[ MOOTA_API_KEY ],
            MOOTA_API_TIMEOUT => $config[
                MOOTA_API_TIMEOUT
            ],
            MOOTA_ENV => $config[ MOOTA_ENV ],
            // MOOTA_USE_UQ_CODE => $config[ MOOTA_USE_UQ_CODE ],
            // MOOTA_UQ_PREFFIX => $config[ MOOTA_UQ_PREFFIX ],
            // MOOTA_UQ_SUFFIX => $config[ MOOTA_UQ_SUFFIX ],
            'PUSH_NOTIF_URL' => $baseUri . '/modules/mootapay/notification.php',
        );

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayCheckoutSubtotalDetails()
    {
        return false;
    }
}
