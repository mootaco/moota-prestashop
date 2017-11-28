<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/library/moota/moota-sdk/constants.php';

const MOOTA_UQ_LABEL = 'uqCodeLabel';

class MootaPay extends PaymentModule
{
    protected $hooks = array();

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
                MOOTA_ENV => 'production',
                MOOTA_API_KEY => null,
                MOOTA_API_TIMEOUT => 30,
                MOOTA_COMPLETED_STATUS => null,
                MOOTA_OLDEST_ORDER => 7,
                MOOTA_UQ_LABEL => 'Moota - Kode Unik',
                MOOTA_USE_UQ_CODE => true,
                MOOTA_UQ_MIN => 1,
                MOOTA_UQ_MAX => 999,
            ]));
        }

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
                MOOTA_COMPLETED_STATUS => strval(
                    Tools::getValue(MOOTA_COMPLETED_STATUS)
                ),
                MOOTA_OLDEST_ORDER => strval(
                    Tools::getValue(MOOTA_OLDEST_ORDER)
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

            Configuration::updateValue(
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

        // Get default language
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

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
        $config['PUSH_NOTIF_URL'] =
            "{$baseUri}/modules/mootapay/notification.php";

        $helper->fields_value = $config;

        return $helper->generateForm($fields_form);
    }

    public function hookDisplayCheckoutSubtotalDetails()
    {
        return false;
    }
}
