<?php
/**
 * Override PaymentOptionsFinder to block payment methods based on CodGuard rating
 */

class PaymentOptionsFinder extends PaymentOptionsFinderCore
{
    public function find()
    {
        try {
            $paymentOptions = parent::find();

            // Get customer email - if none, return immediately
            $context = Context::getContext();
            $customer = $context->customer;
            $email = $customer->email ?? null;

            if (!$email) {
                return $paymentOptions;
            }

            // Only process if module enabled
            if (!Configuration::get('CODGUARD_ENABLED')) {
                return $paymentOptions;
            }

            // Get rating
            $rating = $this->getCustomerRating($email);
            if ($rating === null) {
                return $paymentOptions;
            }

            // Check tolerance
            $tolerance = (int)Configuration::get('CODGUARD_RATING_TOLERANCE');
            $rating_percentage = $rating * 100;

            PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: Customer '.$email.' rating: '.$rating_percentage.'% (tolerance: '.$tolerance.'%)', 1);

            // Filter if below tolerance
            if ($rating_percentage < $tolerance) {
                $blocked_methods = json_decode(Configuration::get('CODGUARD_PAYMENT_METHODS'), true) ?: array('ps_cashondelivery');
                $rejection_message = Configuration::get('CODGUARD_REJECTION_MESSAGE');

                PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: Blocking payment methods: ' . implode(', ', $blocked_methods), 1);

                foreach ($paymentOptions as $module => $paymentOption) {
                    // The key itself is the module name (e.g., 'ps_cashondelivery')
                    if (in_array($module, $blocked_methods)) {
                        // Just remove the blocked payment method entirely
                        // Trying to "disable" it doesn't work reliably in PrestaShop
                        unset($paymentOptions[$module]);
                        PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: REMOVED: ' . $module, 2);
                    }
                }

                // Store blocked status in cookie so hookDisplayPaymentTop can show the message
                if ($rejection_message && isset($context->cookie)) {
                    $context->cookie->codguard_blocked = '1';
                    $context->cookie->codguard_message = $rejection_message;
                    $context->cookie->write();
                    PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: Cookie set with rejection message', 1);
                }
            } else {
                // Clear the cookie if rating is acceptable
                if (isset($context->cookie) && isset($context->cookie->codguard_blocked)) {
                    unset($context->cookie->codguard_blocked);
                    unset($context->cookie->codguard_message);
                    $context->cookie->write();
                    PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: Cookie cleared - rating acceptable', 1);
                }
            }

            return $paymentOptions;

        } catch (Exception $e) {
            PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder ERROR]: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine(), 3);
            return parent::find();
        }
    }

    private function getCustomerRating($email)
    {
        try {
            $shop_id = Configuration::get('CODGUARD_SHOP_ID');
            $public_key = Configuration::get('CODGUARD_PUBLIC_KEY');

            if (empty($shop_id) || empty($public_key)) {
                return null;
            }

            $url = 'https://api.codguard.com/api/customer-rating/'.$shop_id.'/'.urlencode($email);

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
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $response = substr($full_response, $header_size);
            curl_close($ch);

            PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder]: API call for '.$email.' - HTTP '.$http_code, 1);

            if ($http_code == 404) {
                return 1.0;
            }

            if ($http_code != 200) {
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['rating'])) {
                return null;
            }

            return (float)$data['rating'];
        } catch (Exception $e) {
            PrestaShopLogger::addLog('CodGuard [PaymentOptionsFinder API ERROR]: ' . $e->getMessage(), 3);
            return null;
        }
    }
}
