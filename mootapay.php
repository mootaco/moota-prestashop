<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

const MOOTA_SDK_SETTINGS = 'MOOTA_SDK_SETTINGS';
const MOOTA_MODULE = 'mootapay';

const MOOTA_SDK_API_KEY = 'apiKey';
const MOOTA_SDK_API_TIMEOUT = 'apiTimeout';
const MOOTA_SDK_ENV = 'sdkMode';
const MOOTA_SDK_SERVER_ADDRESS = 'serverAddress';

class MootaPay extends PaymentModule
{
    public function __construct()
    {
        $this->name = MOOTA_MODULE;
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
        if (! Configuration::get(MOOTA_SDK_SETTINGS)) {
            Configuration::updateValue(
                MOOTA_SDK_SETTINGS,
                serialize(
                    [
                        'apiKey' => null,
                        'apiTimeout' => 30,
                        'sdkMode' => 'production',
                        'serverAddress' => 'app.moota.co',
                    ]
                )
            );
        }

        parent::install();

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName(MOOTA_SDK_SETTINGS);
        parent::uninstall();

        return true;
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit' . $this->name)) {
            $configValues = [
                MOOTA_SDK_API_KEY => strval(
                    Tools::getValue( MOOTA_SDK_API_KEY )
                ),
                MOOTA_SDK_API_TIMEOUT => strval(
                    Tools::getValue( MOOTA_SDK_API_TIMEOUT )
                ),
                MOOTA_SDK_ENV => strval(
                    Tools::getValue( MOOTA_SDK_ENV )
                ),
                MOOTA_SDK_SERVER_ADDRESS => strval(
                    Tools::getValue( MOOTA_SDK_SERVER_ADDRESS )
                ),
            ];

            Configuration::updateValue(
                MOOTA_SDK_SETTINGS, serialize($configValues)
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
                    'name' => MOOTA_SDK_API_KEY,
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => 'API Timeout',
                    'name' => MOOTA_SDK_API_TIMEOUT,
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'radio',
                    'label' => $this->l('Environment'),
                    'desc' => $this->l('Only change when asked by Moota'),
                    'name' => MOOTA_SDK_ENV,
                    'size' => 20,

                    // If set to true, this option must be set.
                    'required' => true,

                    // The content of the 'class' attribute of the <label> tag
                    // for the <input> tag.
                    'class'     => 'col-xs-2',
                    'is_bool'   => false, 
                    'values' => [
                        [
                            'id' => MOOTA_SDK_ENV . '_production',
                            'value' => 'production',
                            'label' => 'Production',
                        ], [
                            'id' => MOOTA_SDK_ENV . '_testing',
                            'value' => 'testing',
                            'label' => 'Testing',
                        ]
                    ]
                ],
                [
                    'type' => 'text',
                    'label' => $this->l('Server Address'),
                    'name' => MOOTA_SDK_SERVER_ADDRESS,
                    'size' => 20,
                    'required' => true
                ],
                [
                    'type' => 'text',
                    'label' => $this->l(
                        'Moota Push Notification URL (readonly)'
                    ),
                    'name' => 'PUSH_NOTIF_URL',
                    'desc' => $this->l(
                        'Masuk halaman edit bank di moota > tab notifikasi '
                        . '> edit "API Push Notif" '
                        . '> lalu masukkan url ini'
                    ),
                    'size' => 20,
                    'readonly' => true,
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

        $paths = explode('/', dirname( $_SERVER['SCRIPT_NAME'] ));
        array_pop($paths);
        $paths = implode('/', $paths);
        $baseUri = $_SERVER['SERVER_NAME'] . realpath(
            dirname( $_SERVER['DOCUMENT_URI'] ) . '/..'
        ) . $paths;
        unset($paths);

        // Load current value
        $config = unserialize(Configuration::get(MOOTA_SDK_SETTINGS));
        $helper->fields_value = [
            MOOTA_SDK_API_KEY => $config[ MOOTA_SDK_API_KEY ],
            MOOTA_SDK_API_TIMEOUT => $config[
                MOOTA_SDK_API_TIMEOUT
            ],
            MOOTA_SDK_ENV => $config[ MOOTA_SDK_ENV ],
            MOOTA_SDK_SERVER_ADDRESS => $config[
                MOOTA_SDK_SERVER_ADDRESS
            ],
            'PUSH_NOTIF_URL' => $baseUri . '/index.php?fc=module'
                . '&module=mootapay&controller=notification',
        ];

        return $helper->generateForm($fields_form);
    }
}
