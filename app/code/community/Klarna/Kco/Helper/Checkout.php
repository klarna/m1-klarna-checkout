<?php
/**
 * Copyright 2018 Klarna Bank AB (publ)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   Klarna
 * @package    Klarna_Kco
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/**
 * Helper to support checkout
 */
class Klarna_Kco_Helper_Checkout extends Mage_Core_Helper_Abstract
{
    /**
     * Check is allowed Guest Checkout
     * Use config settings and observer
     *
     * @param Mage_Sales_Model_Quote    $quote
     * @param int|Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function isAllowedGuestCheckout(Mage_Sales_Model_Quote $quote, $store = null)
    {
        if ($store === null) {
            $store = $quote->getStoreId();
        }

        $guestCheckout = $this->getCheckoutConfigFlag('guest_checkout', $store);

        if ($guestCheckout == true) {
            $result = new Varien_Object();
            $result->setIsAllowed($guestCheckout);
            Mage::dispatchEvent(
                'checkout_allow_guest', array(
                    'quote'  => $quote,
                    'store'  => $store,
                    'result' => $result
                )
            );

            $guestCheckout = $result->getIsAllowed();
        }

        return $guestCheckout;
    }

    /**
     * Get checkout config value
     *
     * @param string                $config
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getCheckoutConfigFlag($config, $store = null)
    {
        return Mage::getStoreConfigFlag(sprintf('checkout/klarna_kco/%s', $config), $store);
    }

    /**
     * Load quote by checkout id
     *
     * @param string $checkoutId
     *
     * @return Mage_Sales_Model_Quote
     */
    public function loadQuoteByCheckoutId($checkoutId)
    {
        $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($checkoutId);

        return Mage::getModel('sales/quote')->load($klarnaQuote->getQuoteId());
    }

    /**
     * Get an object of default destination details
     *
     * @param   Mage_Core_Model_Store $store
     *
     * @return  Varien_Object
     */
    public function getDefaultDestinationAddress($store = null)
    {
        $shippingDestinationObject = new Varien_Object(
            array(
                'country_id' => $this->getDefaultCountry($store),
                'region_id'  => null,
                'post_code'  => null,
                'store'      => $store
            )
        );

        Mage::dispatchEvent(
            'kco_get_default_destination_address', array(
                'shipping_destination' => $shippingDestinationObject
            )
        );

        return $shippingDestinationObject;
    }

    /**
     * Get default store country
     *
     * @param null $store
     *
     * @return mixed|string
     */
    public function getDefaultCountry($store = null)
    {
        if (version_compare(Mage::getVersion(), '1.6.2', '>=')) {
            return Mage::helper('core')->getDefaultCountry($store);
        }

        return Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_COUNTRY, $store);
    }

    /**
     * Update KCO checkout session address
     *
     * @param Varien_Object $klarnaAddressData
     * @param string        $type
     * @param bool          $saveQuote
     *
     * @throws Klarna_Kco_Exception
     */
    public function updateKcoCheckoutAddress(
        $klarnaAddressData,
        $type = Mage_Sales_Model_Quote_Address::TYPE_BILLING,
        $saveQuote = true
    ) {
        $store = Mage::app()->getStore();
        $country = strtoupper($klarnaAddressData->getCountry());
        $countryDirectory = Mage::getModel('directory/country')->loadByCode($country);

        $address1 = !$klarnaAddressData->hasStreetAddress()
            ? $klarnaAddressData->getStreetName() . ' ' . $klarnaAddressData->getStreetNumber()
            : $klarnaAddressData->getStreetAddress();

        $streetData = array(
            $address1,
            $klarnaAddressData->getStreetAddress2()
        );

        $streetData = array_filter($streetData);
        $data = array(
            'lastname'      => $klarnaAddressData->getFamilyName(),
            'firstname'     => $klarnaAddressData->getGivenName(),
            'email'         => $klarnaAddressData->getEmail(),
            'prefix'        => $klarnaAddressData->getTitle(),
            'street'        => $streetData,
            'postcode'      => $klarnaAddressData->getPostalCode(),
            'city'          => $klarnaAddressData->getCity(),
            'region'        => $klarnaAddressData->getRegion(),
            'telephone'     => $klarnaAddressData->getPhone(),
            'country_id'    => $countryDirectory->getIso2Code(),
            'same_as_other' => $klarnaAddressData->getSameAsOther() ? 1 : 0
        );

        if ($klarnaAddressData->hasDob()) {
            $data['dob'] = $klarnaAddressData->getDob();
        }

        if ($klarnaAddressData->hasGender()) {
            $data['gender'] = $klarnaAddressData->getGender();
        }

        if ($country === 'US' && $this->getStripPostalCodeEnabled($store)) {
            $data['postcode'] = $this->_stripPostalCodePlus4($data['postcode']);
        }

        $dataObject = new Varien_Object($data);

        Mage::dispatchEvent(
            'klarna_kco_update_checkout_address', array(
                'data_object'         => $dataObject,
                'klarna_address_data' => $klarnaAddressData,
                'address_type'        => $type,
                'save_quote'          => $saveQuote
            )
        );

        if (Mage_Sales_Model_Quote_Address::TYPE_BILLING == $type) {
            $this->getKco()->saveBilling($dataObject->toArray(), 0, $saveQuote);
        } else {
            $this->getKco()->saveShipping($dataObject->toArray(), 0, $saveQuote);
        }
    }

    /**
     * Removes the hyphen and last four digits of US postal codes that follow the ZIP+4 format
     *
     * @param string $postalCode
     * @return string
     */
    protected function _stripPostalCodePlus4($postalCode)
    {
        preg_match('/(\d{5})-\d{4}/', $postalCode, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        return $postalCode;
    }

    /**
     * Get kco checkout model
     *
     * @return Klarna_Kco_Model_Checkout_Type_Kco
     */
    public function getKco()
    {
        return Mage::getSingleton('klarna_kco/checkout_type_kco');
    }

    /**
     * Get external payment details
     *
     * @param string $code
     * @param bool   $includeDisabled
     *
     * @return Varien_Object
     */
    public function getExternalPaymentDetails($code, $includeDisabled = false)
    {
        $options = array();
        if ($version = Mage::getConfig()->getNode(sprintf('klarna/external_payment_methods/%s', $code))) {
            $options = $version->asArray();
            $configPath = $version->getAttribute('ifconfig') ?: false;
            unset($options['@']);
            unset($options['label']);

            if (!$includeDisabled || !$configPath || ($configPath && Mage::getStoreConfigFlag($configPath))) {
                foreach ($options as $option => $value) {
                    if (false !== stripos($option, 'url') && !parse_url($value, PHP_URL_SCHEME)) {
                        $options[$option] = $this->getUrl($value);
                    }
                }
            }

            $options = array_filter($options);
        }

        return new Varien_Object($options);
    }

    /**
     * Get url using url template variables
     *
     * @param string $value
     *
     * @return string
     */
    public function getUrl($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if (strpos($value, '{{unsecure_base_url}}') !== false) {
            $unsecureBaseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, false);
            $value = str_replace('{{unsecure_base_url}}', $unsecureBaseUrl, $value);
        } elseif (strpos($value, '{{secure_base_url}}') !== false) {
            $secureBaseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK, true);
            $value = str_replace('{{secure_base_url}}', $secureBaseUrl, $value);
        } elseif (strpos($value, '{{') !== false && strpos($value, '{{base_url}}') === false) {
            $value = Mage::getConfig()->substDistroServerVars($value);
        }

        return Mage::getModel('core/url')->escape($value);
    }

    /**
     * Restore quote from cancelled order from checkout error
     *
     * @return bool
     */
    public function restoreQuote()
    {
        $order = $this->_getCheckoutSession()->getLastRealOrder();
        if ($order->getId()) {
            $quote = $this->_getQuote($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                      ->setReservedOrderId(null)
                      ->save();
                $this->_getCheckoutSession()
                     ->replaceQuote($quote)
                     ->unsLastRealOrderId();

                return true;
            }
        }

        return false;
    }

    /**
     * Return checkout session instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return sales quote instance for specified ID
     *
     * @param int $quoteId Quote identifier
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote($quoteId)
    {
        return Mage::getModel('sales/quote')->load($quoteId);
    }

    /**
     * Prepare float for API call
     *
     * @param float $float
     *
     * @return int
     */
    public function toApiFloat($float)
    {
        return round($float * 100);
    }

    /**
     * Convert value to a shop specific value
     *
     * @param int $value
     * @return float
     */
    public function toShopFloat($value)
    {
        return round($value / 100, 2);
    }

    /**
     * Determine if current store allows shipping methods to be within the iframe
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getShippingInIframe($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getShippingInIframe();
    }

    /**
     * Determine if the current store supports MOTO orders
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getMotoEnabled($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getMotoEnabled();
    }

    /**
     * Determine if current store allows cart totals to be within the iframe
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getCartTotalsInIframe($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getCartTotalsInIframe();
    }

    /**
     * Determine if current store allows shipping callbacks
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getShippingCallbackSupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getShippingCallbackSupport();
    }

    /**
     * Determine if current store requires a separate line for tax
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getSeparateTaxLine($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getSeparateTaxLine();
    }

    /**
     * Determine if current store supports the use of the merchant checkbox feature
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getMerchantCheckboxSupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getMerchantCheckboxSupport();
    }

    /**
     * Determine if current store supports the use of date of birth mandatory
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getDateOfBirthMandatorySupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getDateOfBirthMandatorySupport();
    }

    /**
     * Determine if current store supports the use of date of birth mandatory
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getNationalIdentificationNumberMandatorySupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')
                ->getVersionConfig($store)
                ->getNationalIdentificationNumberMandatorySupport();
    }

    /**
     * Determine if current store supports the use of phone mandatory
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getPhoneMandatorySupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getPhoneMandatorySupport();
    }

    /**
     * Determine if current store supports the use of title mandatory
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getTitleMandatorySupport($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getTitleMandatorySupport();
    }

    /**
     * Determine if current store has a delayed push notification from Klarna
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getDelayedPushNotification($store = null)
    {
        return (bool)Mage::helper('klarna_kco')->getVersionConfig($store)->getDelayedPushNotification();
    }

    /**
     * Get checkout config value
     *
     * @param string                $config
     * @param Mage_Core_Model_Store $store
     *
     * @return mixed
     */
    public function getCheckoutConfig($config, $store = null)
    {
        return Mage::getStoreConfig(sprintf('checkout/klarna_kco/%s', $config), $store);
    }

    /**
     * Get checkout design config value
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return mixed
     */
    public function getCheckoutDesignConfig($store = null)
    {
        $designOptions = Mage::getStoreConfig('checkout/klarna_kco_design', $store);

        return is_array($designOptions) ? $designOptions : array();
    }

    /**
     * Get the current API Connection Class
     *
     * @return Klarna_Kco_Model_Api_Rest_Client
     */
    public function getApiConnection()
    {
        return Mage::getSingleton('klarna/api_rest_client');
    }

    /**
     * Get the order status that should be set on orders that have been processed by Klarna
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return string
     */
    public function getProcessedOrderStatus($store = null)
    {
        return $this->getPaymentConfig('order_status', $store);
    }

    /**
     * Get payment config value
     *
     * @param string                $config
     * @param Mage_Core_Model_Store $store
     *
     * @return mixed
     */
    public function getPaymentConfig($config, $store = null)
    {
        return Mage::getStoreConfig(sprintf('payment/klarna_kco/%s', $config), $store);
    }

    /**
     * Get the current checkout api type code
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return string
     */
    public function getCheckoutType($store = null)
    {
        return Mage::helper('klarna_kco')->getVersionConfig($store)->getType();
    }

    /**
     * Get the text from a merchant checkbox method
     *
     * Will call merchant checkbox methods
     *
     * @param string $code
     *
     * @return mixed
     */
    public function getMerchantCheckboxText($code = null)
    {
        if (!$code) {
            return null;
        }

        $methodConfig = $this->_getMerchantCheckboxMethodConfig($code);

        return $methodConfig->getText();
    }

    /**
     * Get merchant checkbox method configuration details
     *
     * @param string $code
     *
     * @return Varien_Object
     */
    protected function _getMerchantCheckboxMethodConfig($code)
    {
        $options = array();
        if ($version = Mage::getConfig()->getNode(sprintf('klarna/merchant_checkbox/%s', $code))) {
            $options = $version->asArray();
            $options['code'] = $code;
            unset($options['@']);
        }

        return new Varien_Object($options);
    }

    /**
     * Determine if merchant checkbox should be enabled
     *
     * @param string $code
     * @param array  $args
     *
     * @return bool
     */
    public function getMerchantCheckboxEnabled($code, $args = array())
    {
        if (!$code || -1 == $code) {
            return false;
        }

        $observer = new Varien_Event_Observer();
        $observer->setData($args);
        $observer->setEnabled(true);
        $methodConfig = $this->_getMerchantCheckboxMethodConfig($code);
        $object = Mage::getSingleton($methodConfig->getValidationClass());

        $this->_callMerchantCheckboxMethod($object, $methodConfig->getValidationMethod(), $observer);

        return $observer->getEnabled();
    }

    /**
     * Performs the merchant checkbox method to set the checkbox values
     *
     * @param object                $object
     * @param string                $method
     * @param Varien_Event_Observer $observer
     *
     * @return Mage_Core_Model_App
     * @throws Mage_Core_Exception
     */
    protected function _callMerchantCheckboxMethod($object, $method, $observer)
    {
        if (method_exists($object, $method)) {
            $object->$method($observer);
        } elseif (Mage::getIsDeveloperMode()) {
            Mage::throwException('Method "' . $method . '" is not defined in "' . get_class($object) . '"');
        }

        return $this;
    }


    /**
     * Dispatch the merchant checkbox method
     *
     * This should be called before order creation
     *
     * @param $code
     *
     * @param array $args
     *
     * @return Mage_Core_Model_App|null
     *
     * @throws Mage_Core_Exception
     */
    public function dispatchMerchantCheckboxMethod($code, $args = array())
    {
        if (!$code) {
            return null;
        }

        $observer = new Varien_Event_Observer();
        $observer->setData($args);
        $methodConfig = $this->_getMerchantCheckboxMethodConfig($code);
        $object = Mage::getSingleton($methodConfig->getSaveClass());

        return $this->_callMerchantCheckboxMethod($object, $methodConfig->getSaveMethod(), $observer);
    }

    /**
     * check if B2B mode is enabled
     *
     * @param null $store
     *
     * @return bool
     */
    public function isB2bEnabled($store = null)
    {
        return (bool)Mage::getStoreConfigFlag('checkout/klarna_kco/enable_b2b', $store);
    }

    /**
     * get value of business id attribute
     *
     * @param $customerId
     *
     * @param $store
     *
     * @return bool|mixed
     */
    public function getBusinessIdAttributeValue($customerId, $store)
    {
        $customerObj = Mage::getModel('customer/customer')->load($customerId);
        $businessIdValue = $customerObj->getData($this->getBusinessIdAttribute($store));
        if ($businessIdValue) {
            return $businessIdValue;
        }

        return false;
    }

    /**
     * check if this is a business customer
     *
     * @param $customerId
     *
     * @param $store
     *
     * @return bool
     */
    public function isB2bCustomer($customerId, $store)
    {
        $businessIdValue = $this->getBusinessIdAttributeValue($customerId, $store);
        $businessNameValue = $this->getCompanyNameFromAddress($customerId);

        if (!empty($businessIdValue) || !empty($businessNameValue)) {
            return true;
        }

        return false;
    }

    /**
     * get defined business id attribute
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getBusinessIdAttribute($store = null)
    {
        return Mage::getStoreConfig('checkout/klarna_kco/business_id_attribute', $store);

    }

    /**
     * get additional checkboxes from setting
     *
     * @param null $store
     *
     * @return array
     */
    public function getAdditionalCheckboxes($store = null)
    {
        $checkboxes = array();
        $checkboxesConfigs = json_decode(Mage::getStoreConfig('checkout/klarna_kco/custom_checkboxes', $store), true);

        if (!is_array($checkboxesConfigs) || count($checkboxesConfigs) === 0) {
            return $checkboxes;
        }

        foreach ($checkboxesConfigs as $checkboxesConfig) {
            $merchantCheckboxObject = new Varien_Object($checkboxesConfig);
            $merchantCheckboxObject->setEnabled(true);
            $merchantCheckboxObject->setChecked($checkboxesConfig['checked'] ? 1 : 0);
            $merchantCheckboxObject->setRequired($checkboxesConfig['required'] ? 1 : 0);

            Mage::dispatchEvent(
                'kco_merchant_checkbox_' . $checkboxesConfig['id'], array(
                    'merchant_checkbox_object' => $merchantCheckboxObject
                )
            );

            if ($merchantCheckboxObject->getEnabled()) {
                $checkboxesConfig = $merchantCheckboxObject->toArray(array('id', 'text', 'checked', 'required'));
                $checkboxesConfig['checked'] = (bool)$checkboxesConfig['checked'];
                $checkboxesConfig['required'] = (bool)$checkboxesConfig['required'];
                $checkboxes[] = $checkboxesConfig;
            }
        }

        return $checkboxes;
    }

    /**
     * Dispatch event for multiple checkbox
     *
     * @param array $checkboxesInfo
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @param Klarna_Kco_Model_Klarnaquote $klarnaQuote
     */
    public function dispatchMultipleCheckboxesEvent($checkboxesInfo, $quote, $klarnaQuote)
    {
        foreach ($checkboxesInfo as $checkbox) {
            if (!empty($checkbox['id'])) {
                Mage::dispatchEvent(
                    'kco_' . $checkbox['id'] . '_save',
                    array(
                        'quote' => $quote,
                        'klarna_quote' => $klarnaQuote,
                        'checked' => (bool)$checkbox['checked']
                    )
                );
            }
        }
    }

    /**
     * get company name from customers default billing address
     *
     * @param $customerId
     *
     * @return bool
     */
    public function getCompanyNameFromAddress($customerId)
    {
        $customerObj = Mage::getModel('customer/customer')->load($customerId);
        $billingAddress = $customerObj->getDefaultBillingAddress();
        if ($billingAddress) {
            return $billingAddress->getCompany();
        }

        return false;
    }


    /**
     * get store default country code
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getStoreDefaultCountry($store = null)
    {
        return Mage::getStoreConfig('general/country/default', $store);
    }

    /**
     * get store default locale code
     *
     * @param null $store
     *
     * @return mixed
     */
    public function getStoreLocale($store = null)
    {
        return Mage::getStoreConfig('general/locale/code', $store);
    }

    /**
     * Determine if FPT is set to be included in the subtotal
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getDisplayInSubtotalFPT($store = null)
    {
        return Mage::getStoreConfigFlag('tax/weee/include_in_subtotal', $store);
    }

    /**
     * Determine if current store supports the use of pack station
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getPackstationSupport($store = null)
    {
        return (bool)$this->getCheckoutConfigFlag('packstation_enabled', $store);
    }

    /**
     * Determine if current store is set to strip the last four digits from US postal codes
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getStripPostalCodeEnabled($store = null)
    {
        return $this->getPaymentConfig('strip_postal_code', $store);
    }


    /**
     * Updating the Klarna shipping gateway instance
     *
     * @param Klarna_Kco_Model_Klarnashippingmethodgateway $shipping
     * @param array $shippingOption
     * @return Klarna_Kco_Model_Klarnashippingmethodgateway
     */
    public function updateShippingGatewayMetrics(
        Klarna_Kco_Model_Klarnashippingmethodgateway $shipping,
        array $shippingOption
    ) {
        $shipping->setShippingAmount($this->toShopFloat($shippingOption['price']));
        $shipping->setTaxAmount($this->toShopFloat($shippingOption['tax_amount']));
        $shipping->setTaxRate($this->toShopFloat($shippingOption['tax_rate']));

        $shipping->setIsPickUpPoint(false);
        if ($shippingOption['shipping_method'] === 'PickUpPoint') {
            $shipping->setIsPickUpPoint(true);
            $shipping->setPickUpPointName($shippingOption['name']);
        }

        return $shipping;
    }

    /**
     * Returns true if the given shipping method is a shipping method registered in the shop
     *
     * @param string $shippingMethod
     * @return bool
     */
    public function isShopShippingMethod($shippingMethod)
    {
        foreach ($this->getKco()->getQuote()->getShippingAddress()->getAllShippingRates() as $rate) {
            if ($rate->getCode() === $shippingMethod) {
                return true;
            }
        }

        return false;
    }
}
