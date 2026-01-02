<?php
/**
 * CodGuard for PrestaShop
 *
 * @package    CodGuard
 * @author     CodGuard
 * @copyright  2025 CodGuard
 * @license    GPL v2 or later
 * @version    1.0.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class CodGuard extends Module
{
    /**
     * API endpoint for customer rating
     */
    const API_RATING_ENDPOINT = 'https://api.codguard.com/api/customer-rating';

    /**
     * API endpoint for order import
     */
    const API_ORDER_ENDPOINT = 'https://api.codguard.com/api/orders/import';

    /**
     * API endpoint for feedback
     */
    const API_FEEDBACK_ENDPOINT = 'https://api.codguard.com/api/feedback';

    public function __construct()
    {
        $this->name = 'codguard';
        $this->tab = 'checkout';
        $this->version = '1.0.0';
        $this->author = 'CodGuard';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('CodGuard - COD Fraud Prevention');
        $this->description = $this->l('Validates customer ratings and disables Cash on Delivery for high-risk customers.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall CodGuard?');
    }

    /**
     * Install module
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        // Create database tables
        if (!$this->createTables()) {
            return false;
        }

        // Register hooks
        if (!$this->registerHook('paymentOptions') ||
            !$this->registerHook('actionPresentPaymentOptions') ||
            !$this->registerHook('displayPaymentTop') ||
            !$this->registerHook('displayPayment') ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('actionValidateOrder') ||
            !$this->registerHook('displayPaymentReturn') ||
            !$this->registerHook('actionFrontControllerSetMedia') ||
            !$this->registerHook('actionOrderStatusPostUpdate')) {
            return false;
        }

        // Set default configuration
        Configuration::updateValue('CODGUARD_RATING_TOLERANCE', 35);
        Configuration::updateValue('CODGUARD_REJECTION_MESSAGE',
            'Unfortunately, we cannot offer Cash on Delivery for this order. Please choose a different payment method.');
        Configuration::updateValue('CODGUARD_ENABLED', false);
        Configuration::updateValue('CODGUARD_GOOD_STATUS', Configuration::get('PS_OS_PAYMENT')); // Default: Payment accepted
        Configuration::updateValue('CODGUARD_REFUSED_STATUS', Configuration::get('PS_OS_CANCELED')); // Default: Canceled
        Configuration::updateValue('CODGUARD_PAYMENT_METHODS', json_encode(array('ps_cashondelivery'))); // Default: COD only

        // Install override file
        if (!$this->installOverrides()) {
            return false;
        }

        return true;
    }

    /**
     * Uninstall module
     */
    public function uninstall()
    {
        if (!parent::uninstall()) {
            return false;
        }

        // Remove configuration
        Configuration::deleteByName('CODGUARD_SHOP_ID');
        Configuration::deleteByName('CODGUARD_PUBLIC_KEY');
        Configuration::deleteByName('CODGUARD_PRIVATE_KEY');
        Configuration::deleteByName('CODGUARD_RATING_TOLERANCE');
        Configuration::deleteByName('CODGUARD_REJECTION_MESSAGE');
        Configuration::deleteByName('CODGUARD_ENABLED');
        Configuration::deleteByName('CODGUARD_GOOD_STATUS');
        Configuration::deleteByName('CODGUARD_REFUSED_STATUS');
        Configuration::deleteByName('CODGUARD_PAYMENT_METHODS');

        // Drop database tables
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'codguard_block_events`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'codguard_settings`');

        return true;
    }

    /**
     * Create database tables
     */
    private function createTables()
    {
        $sql = array();

        // Block events table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'codguard_block_events` (
            `id_block_event` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(255) NOT NULL,
            `rating` DECIMAL(3,2) NOT NULL,
            `timestamp` INT(11) NOT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            PRIMARY KEY (`id_block_event`),
            INDEX `email` (`email`),
            INDEX `timestamp` (`timestamp`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        // Settings persistence table (backup for configuration)
        $sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'codguard_settings` (
            `id_setting` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `setting_key` VARCHAR(255) NOT NULL,
            `setting_value` TEXT,
            PRIMARY KEY (`id_setting`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Hook into front controller to register CSS and JavaScript files
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        // Simply register assets without any complex logic
        $this->context->controller->registerStylesheet(
            'module-codguard-css',
            'modules/'.$this->name.'/views/css/codguard.css',
            [
                'media' => 'all',
                'priority' => 150,
            ]
        );

        $this->context->controller->registerJavascript(
            'module-codguard-js',
            'modules/'.$this->name.'/views/js/codguard.js',
            [
                'position' => 'bottom',
                'priority' => 150,
            ]
        );
    }

    /**
     * Get blocking configuration if customer should be blocked
     */
    private function getBlockingConfig()
    {
        // Only process if module is enabled
        if (!Configuration::get('CODGUARD_ENABLED')) {
            return null;
        }

        // Get customer email
        $customer = $this->context->customer;
        $email = null;

        if ($customer && $customer->email) {
            $email = $customer->email;
        } else {
            // Try to get email from cart
            $cart = $this->context->cart;
            if (isset($cart->id)) {
                $address = new Address((int)$cart->id_address_invoice);
                if (Validate::isLoadedObject($address)) {
                    $customer_obj = new Customer((int)$address->id_customer);
                    if (Validate::isLoadedObject($customer_obj)) {
                        $email = $customer_obj->email;
                    }
                }
            }
        }

        if (!$email) {
            return null;
        }

        // Get customer rating
        $rating = $this->getCustomerRating($email);
        if ($rating === null) {
            return null;
        }

        // Check rating tolerance
        $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');
        $rating_percentage = $rating * 100;

        // If rating is below tolerance, return blocking config
        if ($rating_percentage < $tolerance) {
            $blocked_methods = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true) ?: array('ps_cashondelivery');
            $rejection_message = Configuration::get('CODGUARD_REJECTION_MESSAGE');

            PrestaShopLogger::addLog('CodGuard [CONFIG]: Blocking for '.$email.' - rating: '.$rating_percentage.'%', 1);

            return [
                'blocked' => true,
                'blockedMethods' => $blocked_methods,
                'rejectionMessage' => $rejection_message,
                'email' => $email,
                'rating' => $rating_percentage
            ];
        }

        return null;
    }

    /**
     * Hook for payment options - prevents COD from being offered if customer rating is low
     * This hook is called when PrestaShop asks modules to provide payment options
     */
    public function hookPaymentOptions($params)
    {
        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookPaymentOptions called!', 1);

        // Only process if module is enabled
        if (!Configuration::get('CODGUARD_ENABLED')) {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Module is DISABLED in paymentOptions', 1);
            return null;
        }

        // Get customer email
        $customer = $this->context->customer;
        $email = null;

        if ($customer && $customer->email) {
            $email = $customer->email;
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from logged-in customer in paymentOptions: '.$email, 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: No logged-in customer in paymentOptions, checking cart', 1);
            // Try to get email from cart/guest checkout
            $cart = $this->context->cart;
            if (isset($cart->id)) {
                $address = new Address((int)$cart->id_address_invoice);
                if (Validate::isLoadedObject($address)) {
                    $customer_obj = new Customer((int)$address->id_customer);
                    if (Validate::isLoadedObject($customer_obj)) {
                        $email = $customer_obj->email;
                        PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from cart in paymentOptions: '.$email, 1);
                    }
                }
            }
        }

        // If no email found, allow all payment methods (fail-open)
        if (!$email) {
            PrestaShopLogger::addLog('CodGuard: No customer email found in paymentOptions, allowing all payment methods', 1);
            return null;
        }

        // Get customer rating
        $rating = $this->getCustomerRating($email);

        // If API fails, allow all payment methods (fail-open)
        if ($rating === null) {
            PrestaShopLogger::addLog('CodGuard: API failed for '.$email.' in paymentOptions, allowing all payment methods', 1);
            return null;
        }

        // Check rating tolerance
        $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');
        $rating_percentage = $rating * 100;

        PrestaShopLogger::addLog('CodGuard: Customer '.$email.' rating in paymentOptions: '.$rating_percentage.'% (tolerance: '.$tolerance.'%)', 1);

        // If rating is below tolerance, we don't provide COD as an option
        if ($rating_percentage < $tolerance) {
            // Log block event
            $this->logBlockEvent($email, $rating);

            // Store blocked status and message in session/cookie
            $this->context->cookie->codguard_blocked = '1';
            $this->context->cookie->codguard_message = Configuration::get('CODGUARD_REJECTION_MESSAGE');
            $this->context->cookie->write();

            PrestaShopLogger::addLog('CodGuard: Blocking COD in paymentOptions for '.$email.' (rating: '.$rating_percentage.'%)', 2);

            // Return null - this module doesn't provide payment options
            // The COD module will be blocked by actionPresentPaymentOptions
            return null;
        } else {
            // Clear blocked flag if rating is acceptable
            if (isset($this->context->cookie->codguard_blocked)) {
                unset($this->context->cookie->codguard_blocked);
                unset($this->context->cookie->codguard_message);
                $this->context->cookie->write();
            }
        }

        // Return null - this module doesn't add payment options, just validates
        return null;
    }

    /**
     * Hook to display content at top of payment page - provides configuration to JavaScript
     */
    public function hookDisplayPaymentTop($params)
    {
        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookDisplayPaymentTop called!', 1);

        $html = '';

        // Inject CSS and JS files directly as a fallback
        $moduleUrl = $this->_path;
        $html .= '<!-- CodGuard: Hook called -->'."\n";
        $html .= '<link rel="stylesheet" href="'.$moduleUrl.'views/css/codguard.css" type="text/css" media="all" />'."\n";
        $html .= '<script type="text/javascript" src="'.$moduleUrl.'views/js/codguard.js"></script>'."\n";

        // Only process if module is enabled
        if (!Configuration::get('CODGUARD_ENABLED')) {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Module is DISABLED in displayPaymentTop', 1);
            $html .= '<!-- CodGuard: Module DISABLED -->'."\n";
            return $html;
        }

        $html .= '<!-- CodGuard: Module ENABLED -->'."\n";

        // FIRST: Check if customer was blocked by the override (via cookie)
        if (isset($this->context->cookie->codguard_blocked) && $this->context->cookie->codguard_blocked == '1') {
            $rejection_message = $this->context->cookie->codguard_message;
            if ($rejection_message) {
                PrestaShopLogger::addLog('CodGuard [displayPaymentTop]: Showing rejection message from cookie', 1);
                $html .= '<div class="alert alert-warning codguard-warning" role="alert" style="margin: 15px 0;">';
                $html .= '<strong>'.Tools::safeOutput($rejection_message).'</strong>';
                $html .= '</div>';
                return $html;
            }
        }

        // Get customer email
        $customer = $this->context->customer;
        $email = null;

        if ($customer && $customer->email) {
            $email = $customer->email;
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from logged-in customer in displayPaymentTop: '.$email, 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: No logged-in customer in displayPaymentTop, checking cart', 1);
            // Try to get email from cart/guest checkout
            $cart = $this->context->cart;
            if (isset($cart->id)) {
                $address = new Address((int)$cart->id_address_invoice);
                if (Validate::isLoadedObject($address)) {
                    $customer_obj = new Customer((int)$address->id_customer);
                    if (Validate::isLoadedObject($customer_obj)) {
                        $email = $customer_obj->email;
                        PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from cart in displayPaymentTop: '.$email, 1);
                    }
                }
            }
        }

        // If no email found, just return the basic HTML with CSS/JS
        if (!$email) {
            PrestaShopLogger::addLog('CodGuard: No customer email found in displayPaymentTop', 1);
            $html .= '<!-- CodGuard: No email found -->'."\n";
            return $html;
        }

        $html .= '<!-- CodGuard: Email found: '.htmlspecialchars($email).' -->'."\n";

        // Get customer rating
        $rating = $this->getCustomerRating($email);

        // If API fails, just return the basic HTML
        if ($rating === null) {
            PrestaShopLogger::addLog('CodGuard: API failed for '.$email.' in displayPaymentTop', 1);
            $html .= '<!-- CodGuard: API failed -->'."\n";
            return $html;
        }

        $html .= '<!-- CodGuard: Rating received: '.($rating * 100).'% -->'."\n";

        // Check rating tolerance
        $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');
        $rating_percentage = $rating * 100;

        PrestaShopLogger::addLog('CodGuard: Customer '.$email.' rating in displayPaymentTop: '.$rating_percentage.'% (tolerance: '.$tolerance.'%)', 1);

        // If rating is below tolerance, block COD
        if ($rating_percentage < $tolerance) {
            PrestaShopLogger::addLog('CodGuard [BLOCK-START]: Rating '.$rating_percentage.'% is below tolerance '.$tolerance.'%', 1);

            // Log block event
            $this->logBlockEvent($email, $rating);

            $blocked_methods = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true) ?: array('ps_cashondelivery');
            $rejection_message = Configuration::get('CODGUARD_REJECTION_MESSAGE');

            PrestaShopLogger::addLog('CodGuard [BLOCK]: Blocked methods: ' . json_encode($blocked_methods), 1);
            PrestaShopLogger::addLog('CodGuard [BLOCK]: Rejection message: ' . $rejection_message, 1);

            // Store blocked status in cookie
            $this->context->cookie->codguard_blocked = '1';
            $this->context->cookie->codguard_message = $rejection_message;
            $this->context->cookie->write();

            PrestaShopLogger::addLog('CodGuard [BLOCK]: Cookie written - codguard_blocked=1', 1);

            // Prepare configuration for JavaScript
            $config = array(
                'blockedMethods' => $blocked_methods,
                'rejectionMessage' => $rejection_message
            );

            $configJson = json_encode($config);
            PrestaShopLogger::addLog('CodGuard [BLOCK]: Config JSON: ' . $configJson, 1);

            // INJECT JAVASCRIPT CONFIG DIRECTLY
            $js_config = '<script type="text/javascript">' . "\n";
            $js_config .= 'if (typeof prestashop === "undefined") { var prestashop = {}; }' . "\n";
            $js_config .= 'prestashop.codguardConfig = ' . $configJson . ';' . "\n";
            $js_config .= 'console.log("[CodGuard] Config injected from displayPaymentTop:", prestashop.codguardConfig);' . "\n";
            $js_config .= '</script>' . "\n";
            $html .= $js_config;

            // Add configuration via data attribute (will be read by codguard.js)
            $html .= '<div data-codguard-config=\''.Tools::jsonEncode($config).'\' style="display:none;"></div>';
            $html .= '<!-- CodGuard: Config element added -->';

            // Also add a warning message banner
            $html .= '<div class="alert alert-warning codguard-warning" role="alert" style="margin: 15px 0;">';
            $html .= '<strong>'.Tools::safeOutput($rejection_message).'</strong>';
            $html .= '</div>';

            PrestaShopLogger::addLog('CodGuard [BLOCK]: HTML output with inline JS length: ' . strlen($html) . ' characters', 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [ALLOW]: Rating '.$rating_percentage.'% is above tolerance '.$tolerance.'%', 1);

            // Clear blocked flag if rating is acceptable
            if (isset($this->context->cookie->codguard_blocked)) {
                unset($this->context->cookie->codguard_blocked);
                unset($this->context->cookie->codguard_message);
                $this->context->cookie->write();
            }
        }

        PrestaShopLogger::addLog('CodGuard [displayPaymentTop]: Returning HTML, length: ' . strlen($html), 1);
        return $html;
    }

    /**
     * Hook for displayPayment - alternative payment display hook
     */
    public function hookDisplayPayment($params)
    {
        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookDisplayPayment called!', 1);
        return $this->hookDisplayPaymentTop($params);
    }

    /**
     * Hook for displayHeader - inject assets in header
     */
    public function hookDisplayHeader($params)
    {
        // Only on checkout pages
        if ($this->context->controller->php_self != 'order' &&
            $this->context->controller->php_self != 'orderopc') {
            return '';
        }

        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookDisplayHeader called on checkout!', 1);
        return $this->hookDisplayPaymentTop($params);
    }

    /**
     * Hook for order validation (required by PrestaShop)
     */
    public function hookActionValidateOrder($params)
    {
        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookActionValidateOrder called', 1);
        // This hook is for logging/tracking completed orders if needed in the future
    }

    /**
     * Hook for order status updates - upload to CodGuard API when status matches selection
     */
    public function hookActionOrderStatusPostUpdate($params)
    {
        // Only process if module is enabled
        if (!Configuration::get('CODGUARD_ENABLED')) {
            return;
        }

        // Get the new order status
        $new_order_status_id = (int)$params['newOrderStatus']->id;
        $order_id = (int)$params['id_order'];

        PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Order #'.$order_id.' status changed to '.$new_order_status_id, 1);

        // Get configured good and refused statuses
        $good_status = (int)Configuration::get('CODGUARD_GOOD_STATUS');
        $refused_status = (int)Configuration::get('CODGUARD_REFUSED_STATUS');

        // Only upload orders with configured statuses
        if ($new_order_status_id != $good_status && $new_order_status_id != $refused_status) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Order #'.$order_id.' status '.$new_order_status_id.' does not match good ('.$good_status.') or refused ('.$refused_status.') - skipping', 1);
            return;
        }

        // Load order data
        $order = new Order($order_id);
        if (!Validate::isLoadedObject($order)) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Order #'.$order_id.' not found', 3);
            return;
        }

        // Get customer
        $customer = new Customer($order->id_customer);
        if (!Validate::isLoadedObject($customer) || empty($customer->email)) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Order #'.$order_id.' has no customer email', 3);
            return;
        }

        // Prepare order data for API
        $order_data = $this->prepareOrderData($order, $customer, $new_order_status_id);

        PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Order #'.$order_id.' - sending to API', 1);

        // Send to API immediately
        $result = $this->sendOrdersToApi(array($order_data));

        if ($result) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: SUCCESS - Order #'.$order_id.' sent to API', 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: FAILED - Order #'.$order_id.' could not be sent to API', 3);
        }
    }

    /**
     * Hook into payment options presentation to filter COD if customer rating is low
     */
    public function hookActionPresentPaymentOptions($params)
    {
        PrestaShopLogger::addLog('CodGuard [DEBUG]: hookActionPresentPaymentOptions called!', 1);

        // Only process if module is enabled
        if (!Configuration::get('CODGUARD_ENABLED')) {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Module is DISABLED', 1);
            return;
        }

        PrestaShopLogger::addLog('CodGuard [DEBUG]: Module is ENABLED, checking customer email', 1);

        // Get customer email
        $customer = $this->context->customer;
        $email = null;

        if ($customer && $customer->email) {
            $email = $customer->email;
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from logged-in customer: '.$email, 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: No logged-in customer, checking cart', 1);
            // Try to get email from cart/guest checkout
            $cart = $params['cart'];
            if (isset($cart->id)) {
                $address = new Address((int)$cart->id_address_invoice);
                if (Validate::isLoadedObject($address)) {
                    $customer_obj = new Customer((int)$address->id_customer);
                    if (Validate::isLoadedObject($customer_obj)) {
                        $email = $customer_obj->email;
                        PrestaShopLogger::addLog('CodGuard [DEBUG]: Found email from cart: '.$email, 1);
                    }
                }
            }
        }

        // If no email found, allow all payment methods (fail-open)
        if (!$email) {
            PrestaShopLogger::addLog('CodGuard: No customer email found, allowing all payment methods', 1);
            return;
        }

        // Get customer rating
        $rating = $this->getCustomerRating($email);

        // If API fails, allow all payment methods (fail-open)
        if ($rating === null) {
            PrestaShopLogger::addLog('CodGuard: API failed for '.$email.', allowing all payment methods', 1);
            return;
        }

        // Check rating tolerance
        $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');
        $rating_percentage = $rating * 100;

        PrestaShopLogger::addLog('CodGuard: Customer '.$email.' rating: '.$rating_percentage.'% (tolerance: '.$tolerance.'%)', 1);

        // If rating is below tolerance, block COD
        if ($rating_percentage < $tolerance) {
            // Log block event (wrapped in try-catch to prevent crashes)
            try {
                $this->logBlockEvent($email, $rating);
            } catch (Exception $e) {
                PrestaShopLogger::addLog('CodGuard [ERROR]: logBlockEvent failed: ' . $e->getMessage(), 3);
            }

            // Send feedback to API
            $this->sendFeedback($email, $rating, $tolerance / 100, 'blocked');

            // Get configured payment methods to block
            $blocked_methods = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true) ?: array('ps_cashondelivery');

            PrestaShopLogger::addLog('CodGuard [DEBUG]: Payment methods to block: ' . implode(', ', $blocked_methods), 1);
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Payment options keys: ' . print_r(array_keys($params['payment_options'] ?? []), true), 1);

            // Remove blocked payment options from the payment_options array
            if (isset($params['payment_options'])) {
                foreach ($blocked_methods as $blocked_module) {
                    // Try direct key match first (module name as key)
                    if (isset($params['payment_options'][$blocked_module])) {
                        unset($params['payment_options'][$blocked_module]);
                        PrestaShopLogger::addLog('CodGuard [BLOCK]: Removed payment method (direct key): ' . $blocked_module, 2);
                    }

                    // Also check all keys for partial matches and iterate through options
                    foreach ($params['payment_options'] as $module_name => $payment_options_array) {
                        // Check if module name contains the blocked method
                        if (strpos($module_name, $blocked_module) !== false) {
                            unset($params['payment_options'][$module_name]);
                            PrestaShopLogger::addLog('CodGuard [BLOCK]: Removed payment method (pattern match): ' . $module_name, 2);
                            continue;
                        }

                        // Check within the array of payment options
                        if (is_array($payment_options_array)) {
                            foreach ($payment_options_array as $key => $option) {
                                if (is_object($option) && method_exists($option, 'getModuleName')) {
                                    $option_module = $option->getModuleName();
                                    if ($option_module === $blocked_module || strpos($option_module, $blocked_module) !== false) {
                                        unset($params['payment_options'][$module_name][$key]);
                                        PrestaShopLogger::addLog('CodGuard [BLOCK]: Removed payment option: ' . $option_module, 2);
                                    }
                                }
                            }
                            // If array is now empty, remove the module key entirely
                            if (empty($params['payment_options'][$module_name])) {
                                unset($params['payment_options'][$module_name]);
                                PrestaShopLogger::addLog('CodGuard [BLOCK]: Removed empty payment module: ' . $module_name, 2);
                            }
                        }
                    }
                }
            }

            // Set warning message
            $rejection_message = Configuration::get('CODGUARD_REJECTION_MESSAGE');
            $this->context->controller->warnings[] = $rejection_message;

            // Store blocked status and message in session for JavaScript to use
            $this->context->cookie->codguard_blocked = '1';
            $this->context->cookie->codguard_message = $rejection_message;
            $this->context->cookie->write();

            PrestaShopLogger::addLog('CodGuard: Blocked COD for '.$email.' (rating: '.$rating_percentage.'%) - Payment methods filtered', 2);
        } else {
            // Send feedback to API for allowed transactions
            $this->sendFeedback($email, $rating, $tolerance / 100, 'allowed');

            // Clear blocked flag if rating is acceptable
            if (isset($this->context->cookie->codguard_blocked)) {
                unset($this->context->cookie->codguard_blocked);
                $this->context->cookie->write();
            }
        }
    }

    /**
     * Get customer rating from CodGuard API
     *
     * @param string $email Customer email
     * @return float|null Rating (0-1) or null on failure
     */
    private function getCustomerRating($email)
    {
        $shop_id = Configuration::get('CODGUARD_SHOP_ID');
        $public_key = Configuration::get('CODGUARD_PUBLIC_KEY');

        if (empty($shop_id) || empty($public_key)) {
            PrestaShopLogger::addLog('CodGuard [ERROR]: API keys not configured', 3);
            return null;
        }

        $url = self::API_RATING_ENDPOINT.'/'.$shop_id.'/'.urlencode($email);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Accept: application/json',
            'x-api-key: '.$public_key
        ));

        $full_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $response = substr($full_response, $header_size);

        curl_close($ch);

        PrestaShopLogger::addLog('CodGuard [INFO]: Rating API called for '.$email.' - HTTP '.$http_code, 1);

        if ($curl_error) {
            PrestaShopLogger::addLog('CodGuard [ERROR]: cURL error - '.$curl_error, 3);
            return null;
        }

        // 404 = new customer, return 1.0 (allow)
        if ($http_code == 404) {
            PrestaShopLogger::addLog('CodGuard [INFO]: Customer not found (404) - allowing with rating 1.0', 1);
            return 1.0;
        }

        // Non-200 status, fail open
        if ($http_code != 200) {
            PrestaShopLogger::addLog('CodGuard [ERROR]: API returned non-200 status: '.$http_code, 3);
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            PrestaShopLogger::addLog('CodGuard [ERROR]: JSON decode error: '.json_last_error_msg(), 3);
            return null;
        }

        if (!isset($data['rating'])) {
            PrestaShopLogger::addLog('CodGuard [ERROR]: Invalid API response - missing "rating" field', 3);
            return null;
        }

        $rating = (float)$data['rating'];
        PrestaShopLogger::addLog('CodGuard [INFO]: Customer rating retrieved: '.$rating, 1);

        return $rating;
    }

    /**
     * Log block event
     *
     * @param string $email Customer email
     * @param float $rating Customer rating
     */
    private function logBlockEvent($email, $rating)
    {
        $ip_address = Tools::getRemoteAddr();

        Db::getInstance()->execute('
            INSERT INTO `'._DB_PREFIX_.'codguard_block_events`
            (email, rating, timestamp, ip_address)
            VALUES ("'.pSQL($email).'", '.(float)$rating.', '.time().', "'.pSQL($ip_address).'")
        ');

        PrestaShopLogger::addLog('CodGuard: Blocked COD for '.$email.' (rating: '.($rating * 100).'%)', 2);
    }

    /**
     * Send feedback to CodGuard API
     *
     * @param string $email Customer email
     * @param float $rating Customer rating (0-1)
     * @param float $threshold Threshold (0-1)
     * @param string $action Action taken (blocked|allowed)
     */
    private function sendFeedback($email, $rating, $threshold, $action)
    {
        $shop_id = Configuration::get('CODGUARD_SHOP_ID');
        $public_key = Configuration::get('CODGUARD_PUBLIC_KEY');

        if (empty($shop_id) || empty($public_key)) {
            PrestaShopLogger::addLog('CodGuard [WARNING]: Cannot send feedback - API keys not configured', 2);
            return;
        }

        $url = self::API_FEEDBACK_ENDPOINT;

        $data = array(
            'eshop_id' => (int)$shop_id,
            'email' => $email,
            'reputation' => (float)$rating,
            'threshold' => (float)$threshold,
            'action' => $action
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-API-KEY: '.$public_key
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            PrestaShopLogger::addLog('CodGuard [WARNING]: Feedback API cURL error - '.$curl_error, 2);
            return;
        }

        if ($http_code == 200) {
            PrestaShopLogger::addLog('CodGuard [DEBUG]: Feedback sent successfully (action: '.$action.')', 1);
        } else {
            PrestaShopLogger::addLog('CodGuard [WARNING]: Feedback API returned status '.$http_code.': '.$response, 2);
        }
    }

    /**
     * Prepare order data for API
     *
     * @param Order $order Order object
     * @param Customer $customer Customer object
     * @param int $order_status_id Order status ID
     * @return array Formatted order data
     */
    private function prepareOrderData($order, $customer, $order_status_id)
    {
        $shop_id = Configuration::get('CODGUARD_SHOP_ID');
        $refused_status = (int)Configuration::get('CODGUARD_REFUSED_STATUS');

        // Get invoice address
        $address = new Address((int)$order->id_address_invoice);

        // Format address parts
        $address_parts = array_filter(array(
            $address->address1,
            $address->address2,
            $address->city,
            $address->postcode
        ));
        $address_string = implode(', ', $address_parts);

        // Get country ISO code
        $country_code = '';
        if ($address->id_country) {
            $country = new Country((int)$address->id_country);
            if (Validate::isLoadedObject($country)) {
                $country_code = $country->iso_code;
            }
        }

        // Get order status name
        $order_state = new OrderState((int)$order_status_id, (int)Configuration::get('PS_LANG_DEFAULT'));
        $status_name = Validate::isLoadedObject($order_state) ? $order_state->name : 'unknown';

        // Determine outcome: -1 for refused, 1 for good
        $outcome = ($order_status_id == $refused_status) ? '-1' : '1';

        // Get phone number
        $phone = !empty($address->phone) ? $address->phone : (!empty($address->phone_mobile) ? $address->phone_mobile : 'N/A');

        return array(
            'eshop_id' => (int)$shop_id,
            'email' => $customer->email,
            'code' => $order->id,
            'status' => $status_name,
            'outcome' => $outcome,
            'phone' => $phone,
            'country_code' => $country_code,
            'postal_code' => $address->postcode ?: '',
            'address' => $address_string
        );
    }

    /**
     * Send orders to CodGuard API
     *
     * @param array $orders Array of order data
     * @return bool Success status
     */
    private function sendOrdersToApi($orders)
    {
        $public_key = Configuration::get('CODGUARD_PUBLIC_KEY');
        $private_key = Configuration::get('CODGUARD_PRIVATE_KEY');

        if (empty($public_key) || empty($private_key)) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: API keys not configured', 3);
            return false;
        }

        $payload = json_encode(array('orders' => $orders));

        PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: Sending '.count($orders).' order(s) to API', 1);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_ORDER_ENDPOINT);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-API-PUBLIC-KEY: '.$public_key,
            'X-API-PRIVATE-KEY: '.$private_key
        ));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: cURL error - '.$curl_error, 3);
            return false;
        }

        PrestaShopLogger::addLog('CodGuard [ORDER-UPLOAD]: API response - HTTP '.$http_code.': '.$response, 1);

        return ($http_code == 200 || $http_code == 201);
    }

    /**
     * Configuration page
     */
    public function getContent()
    {
        $output = '';

        // Handle creating new order status
        if (Tools::isSubmit('createRefusedStatus')) {
            $status_name = Tools::getValue('refused_status_name');
            if (empty($status_name)) {
                $output .= $this->displayError($this->l('Please enter a name for the order status.'));
            } else {
                $new_status_id = $this->createOrderStatus($status_name);
                if ($new_status_id) {
                    Configuration::updateValue('CODGUARD_REFUSED_STATUS', $new_status_id);
                    $output .= $this->displayConfirmation($this->l('Order status created successfully.'));
                } else {
                    $output .= $this->displayError($this->l('Failed to create order status.'));
                }
            }
        }

        // Handle form submission
        if (Tools::isSubmit('submitCodGuardModule')) {
            $shop_id = Tools::getValue('CODGUARD_SHOP_ID');
            $public_key = Tools::getValue('CODGUARD_PUBLIC_KEY');
            $private_key = Tools::getValue('CODGUARD_PRIVATE_KEY');
            $rating_tolerance = (int)Tools::getValue('CODGUARD_RATING_TOLERANCE');
            $rejection_message = Tools::getValue('CODGUARD_REJECTION_MESSAGE');
            $enabled = Tools::getValue('CODGUARD_ENABLED');
            $good_status = (int)Tools::getValue('CODGUARD_GOOD_STATUS');
            $refused_status = (int)Tools::getValue('CODGUARD_REFUSED_STATUS');
            $payment_methods = Tools::getValue('CODGUARD_PAYMENT_METHODS');

            // Validation
            if (empty($shop_id)) {
                $output .= $this->displayError($this->l('Shop ID is required.'));
            } elseif (empty($public_key) || strlen($public_key) < 10) {
                $output .= $this->displayError($this->l('Public Key is required (minimum 10 characters).'));
            } elseif (empty($private_key) || strlen($private_key) < 10) {
                $output .= $this->displayError($this->l('Private Key is required (minimum 10 characters).'));
            } else {
                Configuration::updateValue('CODGUARD_SHOP_ID', $shop_id);
                Configuration::updateValue('CODGUARD_PUBLIC_KEY', $public_key);
                Configuration::updateValue('CODGUARD_PRIVATE_KEY', $private_key);
                Configuration::updateValue('CODGUARD_RATING_TOLERANCE', $rating_tolerance);
                Configuration::updateValue('CODGUARD_REJECTION_MESSAGE', $rejection_message);
                Configuration::updateValue('CODGUARD_ENABLED', $enabled);
                Configuration::updateValue('CODGUARD_GOOD_STATUS', $good_status);
                Configuration::updateValue('CODGUARD_REFUSED_STATUS', $refused_status);
                Configuration::updateValue('CODGUARD_PAYMENT_METHODS', json_encode($payment_methods ?: array()));

                $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
            }
        }

        return $output . $this->displayForm();
    }

    /**
     * Display configuration form
     */
    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Get order statuses
        $order_statuses = OrderState::getOrderStates($default_lang);

        // Get active payment modules
        $payment_modules = array();
        $modules = Module::getPaymentModules();
        foreach ($modules as $module) {
            // Skip our own module from the payment methods list
            if ($module['name'] !== 'codguard') {
                $payment_modules[] = array(
                    'id' => $module['name'],
                    'name' => $module['name']
                );
            }
        }

        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('CodGuard API Configuration'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable CodGuard'),
                    'name' => 'CODGUARD_ENABLED',
                    'is_bool' => true,
                    'desc' => $this->l('Enable or disable the CodGuard module'),
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Shop ID'),
                    'name' => 'CODGUARD_SHOP_ID',
                    'required' => true,
                    'desc' => $this->l('Your CodGuard shop ID'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Public API Key'),
                    'name' => 'CODGUARD_PUBLIC_KEY',
                    'required' => true,
                    'desc' => $this->l('Your CodGuard public API key'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Private API Key'),
                    'name' => 'CODGUARD_PRIVATE_KEY',
                    'required' => true,
                    'desc' => $this->l('Your CodGuard private API key'),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Rating Tolerance (%)'),
                    'name' => 'CODGUARD_RATING_TOLERANCE',
                    'required' => true,
                    'desc' => $this->l('Minimum acceptable customer rating (0-100). Default: 35'),
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Rejection Message'),
                    'name' => 'CODGUARD_REJECTION_MESSAGE',
                    'required' => true,
                    'desc' => $this->l('Message shown to customers when COD is blocked'),
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Good Order Status'),
                    'name' => 'CODGUARD_GOOD_STATUS',
                    'desc' => $this->l('Order status to set when customer has good rating'),
                    'options' => array(
                        'query' => $order_statuses,
                        'id' => 'id_order_state',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Refused Order Status'),
                    'name' => 'CODGUARD_REFUSED_STATUS',
                    'desc' => $this->l('Order status to set when customer has low rating (for future use)'),
                    'options' => array(
                        'query' => $order_statuses,
                        'id' => 'id_order_state',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Payment Methods to Block'),
                    'name' => 'CODGUARD_PAYMENT_METHODS[]',
                    'multiple' => true,
                    'desc' => $this->l('Select payment methods to block for low-rated customers (typically COD)'),
                    'options' => array(
                        'query' => $payment_modules,
                        'id' => 'id',
                        'name' => 'name'
                    )
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submitCodGuardModule';

        // Load current values
        $helper->fields_value['CODGUARD_ENABLED'] = Configuration::get('CODGUARD_ENABLED');
        $helper->fields_value['CODGUARD_SHOP_ID'] = Configuration::get('CODGUARD_SHOP_ID');
        $helper->fields_value['CODGUARD_PUBLIC_KEY'] = Configuration::get('CODGUARD_PUBLIC_KEY');
        $helper->fields_value['CODGUARD_PRIVATE_KEY'] = Configuration::get('CODGUARD_PRIVATE_KEY');
        $helper->fields_value['CODGUARD_RATING_TOLERANCE'] = Configuration::get('CODGUARD_RATING_TOLERANCE');
        $helper->fields_value['CODGUARD_REJECTION_MESSAGE'] = Configuration::get('CODGUARD_REJECTION_MESSAGE');
        $helper->fields_value['CODGUARD_GOOD_STATUS'] = Configuration::get('CODGUARD_GOOD_STATUS');
        $helper->fields_value['CODGUARD_REFUSED_STATUS'] = Configuration::get('CODGUARD_REFUSED_STATUS');
        $helper->fields_value['CODGUARD_PAYMENT_METHODS[]'] = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true) ?: array();

        // Add custom HTML for creating new order status
        $create_status_form = '
        <div class="panel">
            <div class="panel-heading">
                <i class="icon-plus"></i> '.$this->l('Create New Order Status').'
            </div>
            <div class="panel-body">
                <form action="'.$helper->currentIndex.'&token='.$helper->token.'" method="post">
                    <div class="form-group">
                        <label>'.$this->l('Order Status Name').'</label>
                        <input type="text" name="refused_status_name" class="form-control" placeholder="'.$this->l('e.g., Refused - Low Rating').'" />
                        <p class="help-block">'.$this->l('Enter a name for the new order status to use for refused orders.').'</p>
                    </div>
                    <button type="submit" name="createRefusedStatus" class="btn btn-default">
                        <i class="icon-plus"></i> '.$this->l('Create Order Status').'
                    </button>
                </form>
            </div>
        </div>';

        return $helper->generateForm($fields_form) . $create_status_form;
    }

    /**
     * Create a new order status
     */
    private function createOrderStatus($name)
    {
        $order_state = new OrderState();

        // Set properties for all languages
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $order_state->name[$language['id_lang']] = $name;
        }

        // Set order state properties
        $order_state->color = '#DC143C'; // Crimson red color
        $order_state->send_email = false;
        $order_state->module_name = '';
        $order_state->invoice = false;
        $order_state->logable = false;
        $order_state->shipped = false;
        $order_state->unremovable = false;
        $order_state->delivery = false;
        $order_state->hidden = false;
        $order_state->paid = false;
        $order_state->pdf_invoice = false;
        $order_state->pdf_delivery = false;
        $order_state->deleted = false;

        if ($order_state->add()) {
            return (int)$order_state->id;
        }

        return false;
    }
}
