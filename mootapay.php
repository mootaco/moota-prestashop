<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_
    . '/mootapay/library/moota/moota-sdk/constants.php';
require_once _PS_MODULE_DIR_ . '/mootapay/constants.php';

class MootaPay extends PaymentModule
{
    protected $overridenFiles = array(
        'classes/Cart.php',
        'controllers/front/CartController.php',
        'controllers/front/GuestTrackingController.php',
        'controllers/front/HistoryController.php',
        'controllers/front/OrderConfirmationController.php',
        'controllers/front/OrderController.php',
        'controllers/front/OrderDetailController.php',
        'modules/ps_shoppingcart/ps_shoppingcart.php',
    );

    protected $hooks = array();

    protected $defaultLang;

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

        // Get default language
        $this->defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
    }

    protected function getDefaultConfig() {
        return array(
            MOOTA_ENV => 'production',
            MOOTA_API_KEY => null,
            MOOTA_API_TIMEOUT => 30,
            MOOTA_COMPLETED_STATUS => null,
            MOOTA_COMPLETE_SENDMAIL => false,
            MOOTA_OLDEST_ORDER => 7,
            MOOTA_UQ_LABEL => 'Moota - Kode Unik',
            MOOTA_USE_UQ_CODE => true,
            MOOTA_UQ_MIN => 1,
            MOOTA_UQ_MAX => 999,
        );
    }

    protected function logDebug($data) {
        @file_put_contents(
            '/Volumes/WData/Projects/web/www/prestashop/debug.log',
            json_encode($data, JSON_PRETTY_PRINT) . PHP_EOL,
            FILE_APPEND
        );
    }

    protected function initConfig() {
        // config in db is a `serialize()`-d string
        $config = Configuration::get(MOOTA_SETTINGS);

        // `unserialize()`-d into array, if not empty
        $config = empty($config) ? array() : unserialize($config);

        // merge old config in db, with current default
        // in case there is new key-value pair(s)
        // this will not overwrite old config
        $config = array_merge($config, $this->getDefaultConfig());

        // store it to db
        Configuration::updateValue(MOOTA_SETTINGS, serialize($config));
    }

    public function install()
    {
        $this->initConfig();

        $installed = parent::install();

        // if `$this->hooks` contains `displayCheckoutSubtotalDetails`
        // make sure this class have a method named:
        // `hookDisplayCheckoutSubtotalDetails`
        foreach ($this->hooks as $hookName) {
            $installed = $installed && $this->registerHook($hookName);
        }

        return $installed;
    }

    public function uninstall()
    {
        $this->plsManuallyDeleteStuffLikeItsTheNineties();
        Configuration::deleteByName(MOOTA_SETTINGS);
        parent::uninstall();

        return true;
    }

    public function getContent()
    {
        $output = null;
     
        if (Tools::isSubmit('submit' . $this->name)) {
            $configValues = [
                MOOTA_ENV => strval( Tools::getValue( MOOTA_ENV ) ),
                MOOTA_API_KEY => strval(
                    Tools::getValue(MOOTA_API_KEY)
                ),
                MOOTA_API_TIMEOUT => (int) Tools::getValue(MOOTA_API_TIMEOUT),
                MOOTA_COMPLETED_STATUS => (int) Tools::getValue(
                    MOOTA_COMPLETED_STATUS
                ),
                MOOTA_COMPLETE_SENDMAIL => (bool) Tools::getValue(
                    MOOTA_COMPLETE_SENDMAIL
                ),
                MOOTA_OLDEST_ORDER => (int) Tools::getValue(
                    MOOTA_OLDEST_ORDER
                ),
                MOOTA_UQ_LABEL => strval(
                    Tools::getValue(MOOTA_UQ_LABEL)
                ),
                MOOTA_USE_UQ_CODE => (bool) Tools::getValue(MOOTA_USE_UQ_CODE),
                MOOTA_UQ_MIN => (int) Tools::getValue(MOOTA_UQ_MIN),
                MOOTA_UQ_MAX => (int) Tools::getValue(MOOTA_UQ_MAX),
            ];

            if (
                $configValues[ MOOTA_ENV ] !== 'production'
                && $configValues[ MOOTA_ENV ] !== 'testing'
            ) {
                $configValues[ MOOTA_ENV ] = 'production';
            }

            if ( $configValues[ MOOTA_UQ_MIN ] < 1 ) {
                $configValues[ MOOTA_UQ_MIN ] = 1;
            }

            \Configuration::updateValue(
                MOOTA_SETTINGS, serialize($configValues)
            );

            $output .= $this->displayConfirmation(
                $this->l('Settings updated')
            );
        }

        return $output . $this->displayForm();
    }

    protected function getOrderStates($keyName = null, $valueName = null)
    {
        if (empty($keyName) || empty($valueName)) {
            $keyName = null;
            $valueName = null;
        }

        $osIds = array();
        $orderStates = array();

        $query = (new \DbQuery)
            ->select('`value`')
            ->from('configuration')
            ->where("`name` LIKE 'PS_OS%'")
        ;

        foreach (\Db::getInstance()->executeS($query) as $row) {
            $osIds[] = $row['value'];
        }

        $query = (new \DbQuery)
            ->select('`id_order_state`, `name`')
            ->from('order_state_lang')
            ->where('`id_order_state` IN ('. implode(
                ', ', $osIds
            ) .')')
            ->where("`id_lang` = {$this->defaultLang}")
        ;

        foreach (\Db::getInstance()->executeS($query) as $row) {
            if (empty($keyName)) {
                $orderStates[ $row['id_order_state'] ] = $row['name'];
            } else {
                $orderStates[] = array(
                    $keyName => $row['id_order_state'],
                    $valueName => $row['name'],
                );
            }
        }

        return $orderStates;
    }

    public function displayForm()
    {
        $orderStates = array( array(
            'id' => null,
            'name' => 'Pilih satu',
        ) );
        $orderStates = array_merge(
            $orderStates,
            $this->getOrderStates('id', 'name')
        );

        // Init Fields form array
        $fields_form[0]['form'] = array(
            'legend' => array( 'title' => $this->l('Settings') ),
            'input' => array(
                // MOOTA_ENV
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
                        ),
                        array(
                            'id' => MOOTA_ENV . '_testing',
                            'value' => 'testing',
                            'label' => 'Sandbox',
                        )
                    )
                ),

                // pseudo: PUSH_NOTIF_URL
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

                // MOOTA_API_KEY
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

                // MOOTA_API_TIMEOUT
                array(
                    'type' => 'text',
                    'label' => 'API Timeout',
                    'name' => MOOTA_API_TIMEOUT,
                    'size' => 20,
                    'required' => true
                ),

                // MOOTA_COMPLETED_STATUS
                array(
                    'type' => 'select',
                    'label' => $this->l('Status Berhasil'),
                    'desc' => $this->l(
                        'Status setelah berhasil menemukan order yang cocok'
                    ),
                    'name' => MOOTA_COMPLETED_STATUS,
                    'required' => true,
                    'is_bool'   => false,
                    'options' => array(
                        'query' => $orderStates,
                        'id' => 'id',
                        'name' => 'name',
                        'multiple' => false,
                    ),
                ),

                // MOOTA_USE_UQ_CODE
                array(
                    'type' => 'radio',
                    'label' => 'Kirim notifikasi ke customer?',
                    'desc' => $this->l(
                        'Kirim email notifikasi pembayaran diterima'
                    ),
                    'name' => MOOTA_COMPLETE_SENDMAIL,
                    'class' => 'col-xs-1',
                    'values' => array(
                        array(
                            'id' => MOOTA_COMPLETE_SENDMAIL . '_on',
                            'value' => 1,
                            'label' => 'Iya',
                        ),
                        array(
                            'id' => MOOTA_COMPLETE_SENDMAIL . '_off',
                            'value' => 0,
                            'label' => 'Tidak',
                        ),
                    ),
                    'required' => true,
                ),

                // MOOTA_OLDEST_ORDER
                array(
                    'type' => 'text',
                    'label' => 'Batas lama pengecekkan order',
                    'desc' => $this->l(
                        'Pengecekkan order berdasarkan x hari ke belakang '
                            .'(default: 7 hari kebelakang)'
                    ),
                    'name' => MOOTA_OLDEST_ORDER,
                    'size' => 20,
                    'required' => true
                ),

                // MOOTA_USE_UQ_CODE
                array(
                    'type' => 'radio',
                    'label' => 'Gunakan kode unik?',
                    'desc' => $this->l(''),
                    'name' => MOOTA_USE_UQ_CODE,
                    'class' => 'col-xs-1',
                    'values' => array(
                        array(
                            'id' => MOOTA_USE_UQ_CODE . '_on',
                            'value' => 1,
                            'label' => 'Iya',
                        ),
                        array(
                            'id' => MOOTA_USE_UQ_CODE . '_off',
                            'value' => 0,
                            'label' => 'Tidak',
                        ),
                    ),
                    'required' => true,
                ),

                // MOOTA_UQ_LABEL
                array(
                    'type' => 'text',
                    'label' => 'Label Kode Unik',
                    'desc' => $this->l(
                        'Label yang akan muncul di form checkout'
                    ),
                    'name' => MOOTA_UQ_LABEL,
                    'size' => 20,
                    'required' => true
                ),

                // MOOTA_UQ_MIN
                array(
                    'type' => 'text',
                    'label' => 'Angka Unik - Minimum',
                    'desc' => $this->l(
                        'Masukan nilai Minimum angka unik'
                            . ', 1 - 999'
                    ),
                    'name' => MOOTA_UQ_MIN,
                    'size' => 20,
                    'required' => true,
                    'maxchar' => 3,
                ),

                // MOOTA_UQ_MAX
                array(
                    'type' => 'text',
                    'label' => 'Angka Unik - Maksimum',
                    'desc' => $this->l(
                        'Masukan nilai Maksimum angka unik'
                    ),
                    'name' => MOOTA_UQ_MAX,
                    'size' => 20,
                    'required' => true,
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
        $helper->default_form_language = $this->defaultLang;
        $helper->allow_employee_form_lang = $this->defaultLang;
         
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

        $protocol = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '');

        $useFriendly = Configuration::get('PS_REWRITING_SETTINGS');

        if ($useFriendly) {
            $config['PUSH_NOTIF_URL'] = "{$protocol}://{$baseUri}"
                . '/module/mootapay/push';
        } else {
            $config['PUSH_NOTIF_URL'] = "{$protocol}://{$baseUri}"
                . '/index.php?fc=module&module=mootapay&controller=push';
        }

        $helper->fields_value = $config;

        return $helper->generateForm($fields_form);
    }

    public function enable($force_all = false)
    {
        $this->initConfig();
        $this->plsManuallyDeleteStuffLikeItsTheNineties();

        return parent::enable($force_all);
    }

    public function disable($force_all = false)
    {
        $this->plsManuallyDeleteStuffLikeItsTheNineties();

        return parent::disable($force_all);
    }

    /**
     * Call this before calling parent class action Module#<ACTION>,
     * where action is one of the following:
     * `install`, `uninstall`, `enable`, `disable`
     *
     * Safety measure against Prestashop's default behavior when
     * reenabling a module, which is to whine and moan because some
     * automatically generated Class Names is missing from god knows where....
     * :'(
     */
    protected function plsManuallyDeleteStuffLikeItsTheNineties()
    {
        @unlink(_PS_ROOT_DIR_ . '/app/cache/dev/class_index.php');
        @unlink(_PS_ROOT_DIR_ . '/app/cache/prod/class_index.php');

        foreach ($this->overridenFiles as $file) {
            @unlink(_PS_ROOT_DIR_ . '/override/' . $file);
        }
    }
}
