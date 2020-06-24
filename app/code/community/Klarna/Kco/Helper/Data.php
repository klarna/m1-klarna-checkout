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
 * Klarna KCO helper
 */
class Klarna_Kco_Helper_Data extends Mage_Core_Helper_Abstract
{
    const MERCHANT_PORTAL_US = 'https://us.portal.klarna.com/orders/';
    const MERCHANT_PORTAL_EU = 'https://eu.portal.klarna.com/orders/';
    const API_TYPE_V2 = 'kred';

    /**
     * Configuration cache for api versions
     *
     * @var array
     */
    protected $_versionConfigCache = array();

    /**
     * Determine if KCO checkout is enabled
     *
     * By checking if the Klarna payment method and Checkout is enabled
     *
     * @param Mage_Core_Model_Store        $store
     * @param Mage_Customer_Model_Customer $customer
     *
     * @return bool
     */
    public function kcoEnabled($store = null, $customer = null)
    {
        if (!$this->klarnaPaymentEnabled($store)) {
            return false;
        }

        if (null === $customer) {
            $customer = Mage::helper('customer')->getCustomer();
        }

        $customerGroupId        = $customer->getId() ? $customer->getGroupId() : 0;
        $disabledCustomerGroups = Mage::helper('klarna_kco/checkout')->getPaymentConfig('disable_customer_group');
        $disabledCustomerGroups = trim($disabledCustomerGroups);

        if ('' == $disabledCustomerGroups) {
            return true;
        }

        if (!is_array($disabledCustomerGroups)) {
            $disabledCustomerGroups = explode(',', (string)$disabledCustomerGroups);
        }

        return !in_array($customerGroupId, $disabledCustomerGroups);
    }

    /**
     * Check if the Klarna payment method is enabled
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function klarnaPaymentEnabled($store = null)
    {
        return Mage::getStoreConfigFlag('payment/klarna_kco/active', $store);
    }

    /**
     * Determine if current store supports the use of partial captures and refunds
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function getPartialPaymentSupport($store = null)
    {
        return !(bool)$this->getVersionConfig($store)->getPartialPaymentDisabled();
    }

    /**
     * Get configuration parameters for a store
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return Varien_Object
     */
    public function getVersionConfig($store = null)
    {
        $version = Mage::getStoreConfig('payment/klarna_kco/api_version', $store);

        if (!isset($this->_versionConfigCache[$version])) {
            $this->_versionConfigCache[$version] = $this->getCheckoutVersionDetails($version);
        }

        return $this->_versionConfigCache[$version];
    }

    /**
     * Get Api instance
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return Klarna_Kco_Model_Api_Abstract
     */
    public function getApiInstance($store = null)
    {
        $versionConfig = $this->getVersionConfig($store);

        /** @var Klarna_Kco_Model_Api_Abstract $instance */
        $instance = $this->_getApiTypeInstance($versionConfig->getType());

        $instance->setStore($store);
        $instance->setConfig($versionConfig);

        return $instance;
    }

    /**
     * Load api type instance
     *
     * @param string $code
     *
     * @return Klarna_Kco_Model_Api_Abstract
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _getApiTypeInstance($code)
    {
        $typeConfig = $this->_getApiTypeConfig($code);
        $instance   = Mage::getSingleton($typeConfig->getClass());

        if (!$instance) {
            throw new Klarna_Kco_Model_Api_Exception(
                sprintf('API class "%s" does not exist!', $typeConfig->getClass())
            );
        }

        return $instance;
    }

    /**
     * Get api type configuration
     *
     * @param string $code
     *
     * @return Varien_Object
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _getApiTypeConfig($code)
    {
        $typeConfig = Mage::getConfig()->getNode(sprintf('klarna/api_types/%s', $code));
        if (!$typeConfig) {
            throw new Klarna_Kco_Model_Api_Exception(sprintf('API type "%s" does not exist!', $code));
        }

        $config = $typeConfig->asArray();
        unset($config['@']);

        $configObject = new Varien_Object($config);

        Mage::dispatchEvent(
            'kco_load_api_config',
            array('options' => $configObject)
        );

        return $configObject;
    }

    /**
     * Get api version details
     *
     * @param string $code
     *
     * @return Varien_Object
     */
    public function getCheckoutVersionDetails($code)
    {
        $options = array();
        if ($version = Mage::getConfig()->getNode(sprintf('klarna/api_versions/%s', $code))) {
            $options         = $version->asArray();
            $options['code'] = $code;
            unset($options['@']);
        }

        // Start with api type global options
        $optionsObject  = new Varien_Object($options);
        $apiTypeOptions = $this->_getApiTypeConfig($optionsObject->getType())->getOptions();
        $options        = array_merge($apiTypeOptions, $options);
        $optionsObject  = new Varien_Object($options);

        Mage::dispatchEvent(
            'kco_load_version_details',
            array('options' => $optionsObject)
        );

        return $optionsObject;
    }

    /**
     * get link to merchant portal for order
     *
     * @param $mageOrder
     * @param $klarnaOrder
     * @return string
     */
    public function getOrderMerchantPortalLink($mageOrder, $klarnaOrder)
    {
        $store = $mageOrder->getStore();

        $merchantId = Mage::getStoreConfig('payment/klarna_kco/merchant_id', $store);
        $apiVersion = Mage::getStoreConfig('payment/klarna_kco/api_version', $store);
       
        //don't display link for v2 order
        if ($this->getCheckoutVersionDetails($apiVersion)->getType() == self::API_TYPE_V2) {
            return false;
        }

        if ($apiVersion == 'na') {
            $url = self::MERCHANT_PORTAL_US;
        } else {
            $url = self::MERCHANT_PORTAL_EU;
        }

        $url .= "merchants/" . $merchantId . "/orders/" . $klarnaOrder->getKlarnaCheckoutId();
        return $url;
    }

    /**
     * Check if KCO payment info is valid
     *
     * @param array $initialPaymentInfo
     * @return bool
     */
    public function isKcoPaymentInfoValid($initialPaymentInfo)
    {
        return is_array($initialPaymentInfo)
            && isset($initialPaymentInfo['type'])
            && isset($initialPaymentInfo['description']);
    }
}
