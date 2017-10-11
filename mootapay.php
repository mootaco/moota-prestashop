<?php

if (!defined('_PS_VERSION_')) exit;

class MootaPay extends PaymentModule
{
    const MOOTA_SDK_SETTINGS = 'MOOTA_SDK_SETTINGS';
    const MOOTA_MODULE = 'mootapay';

    const MOOTA_SDK_API_KEY = 'apiKey';
    const MOOTA_SDK_API_TIMEOUT = 'apiTimeout';
    const MOOTA_SDK_ENV = 'sdkMode';
    const MOOTA_SDK_SERVER_ADDRESS = 'serverAddress';

    public function __construct()
    {
        $this->name = self::MOOTA_MODULE;
        $this->tab = 'payments_gateways';
        $this->version = '1.0';
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
        if ( ! Configuration::get( self::MOOTA_SDK_SETTINGS ) ) {
            Configuration::updateValue(self::MOOTA_SDK_SETTINGS, serialize([
                'apiKey' => null,
                'apiTimeout' => 30,
                'sdkMode' => 'production',
                'serverAddress' => 'app.moota.co',
            ]));
        }

        parent::install();

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName(self::MOOTA_SDK_SETTINGS);
        parent::uninstall();

        return true;
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit' . $this->name)) {
            $configValues = [
                self::MOOTA_SDK_API_KEY => strval(
                    Tools::getValue( self::MOOTA_SDK_API_KEY )
                ),
                self::MOOTA_SDK_API_TIMEOUT => strval(
                    Tools::getValue( self::MOOTA_SDK_API_TIMEOUT )
                ),
                self::MOOTA_SDK_ENV => strval(
                    Tools::getValue( self::MOOTA_SDK_ENV )
                ),
                self::MOOTA_SDK_SERVER_ADDRESS => strval(
                    Tools::getValue( self::MOOTA_SDK_SERVER_ADDRESS )
                ),
            ];

            Configuration::updateValue(
                self::MOOTA_SDK_SETTINGS, serialize($configValues)
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
        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('Settings'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => 'API Key',
                    'name' => self::MOOTA_SDK_API_KEY,
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => 'API Timeout',
                    'name' => self::MOOTA_SDK_API_TIMEOUT,
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Environment'),
                    'desc' => $this->l('Only change when asked by Moota'),
                    'name' => self::MOOTA_SDK_ENV,
                    'size' => 20,

                    // If set to true, this option must be set.
                    'required' => true,

                    // The content of the 'class' attribute of the <label> tag
                    // for the <input> tag.
                    'class'     => 'col-xs-2',
                    'is_bool'   => false, 
                    'values' => [
                        [
                            'id' => self::MOOTA_SDK_ENV . '_production',
                            'value' => 'production',
                            'label' => 'Production',
                        ], [
                            'id' => self::MOOTA_SDK_ENV . '_testing',
                            'value' => 'testing',
                            'label' => 'Testing',
                        ]
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Server Address'),
                    'name' => self::MOOTA_SDK_SERVER_ADDRESS,
                    'size' => 20,
                    'required' => true
                ],
            ],
            'submit' => [
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            ]
        ];

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

        // Load current value
        $config = unserialize( Configuration::get( self::MOOTA_SDK_SETTINGS ) );
        $helper->fields_value = [
            self::MOOTA_SDK_API_KEY => $config[ self::MOOTA_SDK_API_KEY ],
            self::MOOTA_SDK_API_TIMEOUT => $config[
                self::MOOTA_SDK_API_TIMEOUT
            ],
            self::MOOTA_SDK_ENV => $config[ self::MOOTA_SDK_ENV ],
            self::MOOTA_SDK_SERVER_ADDRESS => $config[
                self::MOOTA_SDK_SERVER_ADDRESS
            ],
        ];

        return $helper->generateForm($fields_form);
    }
}
