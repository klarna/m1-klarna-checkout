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
 * @package    Klarna_KcoKred
 * @author     Jason Grim <jason.grim@klarna.com>
 * @author     Fei Chen <fei.chen@klarna.com>
 */

/**
 * Api request builder for Kred
 */
class Klarna_KcoKred_Model_Api_Builder_Kred extends Klarna_Kco_Model_Api_Builder_Abstract
{
    /**
     * Generate KCO request
     *
     * @param string $type
     *
     * @return $this
     *
     * @throws Klarna_Kco_Exception
     */
    public function generateRequest($type = self::GENERATE_TYPE_CREATE)
    {
        parent::generateRequest($type);

        switch ($type) {
            case self::GENERATE_TYPE_CREATE:
                return $this->_generateCreate();
            case self::GENERATE_TYPE_UPDATE:
                return $this->_generateUpdate();
            default:
                throw new Klarna_Kco_Exception('Invalid request type');
        }
    }

    /**
     * Generate create request
     *
     * @return $this
     */
    protected function _generateCreate()
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote  = $this->getObject();
        $store  = $quote->getStore();
        $create = array();


        Mage::dispatchEvent(
            'kco_request_generated_before', array(
                'quote' => $quote,
                'builder' => $this
            )
        );

        $create['purchase_country']  = $this->_helper->getDefaultCountry();
        $create['purchase_currency'] = $quote->getBaseCurrencyCode();
        $create['locale']            = str_replace('_', '-', Mage::app()->getLocale()->getLocaleCode());

        /**
         * Pre-fill customer details
         */
        if ($this->_helper->getCheckoutConfigFlag('merchant_prefill', $store)) {
            /**
             * Customer
             */
            if ($data = $this->_getCustomerData($quote)) {
                $create['customer'] = $data;
            }

            /**
             * Billing Address
             */
            if ($data = $this->_getAddressData($quote, Mage_Sales_Model_Quote_Address::TYPE_BILLING)) {
                $create['billing_address'] = $data;
            }

            /**
             * Shipping Address
             */
            if (isset($create['billing_address'])
                && ($data = $this->_getAddressData($quote, Mage_Sales_Model_Quote_Address::TYPE_SHIPPING))
            ) {
                $create['shipping_address'] = $data;
            }

            $norticCountries = array('SE', 'FI', 'NO');
            if (in_array(Mage::getStoreConfig('general/country/default', $quote->getStore()), $norticCountries)) {
                unset($create['billing_address']);
            }
        }

        /**
         * GUI
         */
        if (!$this->_helper->getCheckoutConfigFlag('auto_focus', $store)) {
            $create['gui']['options'] = array('disable_autofocus');
        }

        /**
         * External payment methods
         */
        if ($enabledExternalMethods = $this->_helper->getPaymentConfig('external_payment_methods')) {
            $externalMethods = array();
            foreach (explode(',', $enabledExternalMethods) as $externalMethod) {
                $methodDetails = $this->_helper->getExternalPaymentDetails($externalMethod);
                if (!$methodDetails->isEmpty()) {
                    $externalMethods[] = $methodDetails->toArray();
                }
            }

            if ($externalMethods) {
                foreach ($externalMethods as &$method) {
                    if (isset($method['redirect_url'])) {
                        $method['redirect_uri'] = $method['redirect_url'];
                        unset($method['redirect_url']);
                    }

                    if (isset($method['image_url'])) {
                        $method['image_uri'] = $method['image_url'];
                        unset($method['image_url']);
                    }
                }

                $create['external_payment_methods'] = $externalMethods;
            }
        }

        /**
         * Options
         */
        $create['options'] = array_map('trim', array_filter($this->_helper->getCheckoutDesignConfig($store)));

        if($this->_helper->isB2bEnabled($store)){
            $create['options']['allowed_customer_types'] = array('person','organization');
        }

        // Kred does not support radius_border
        unset($create['options']['radius_border']);

        $create['options']['allow_separate_shipping_address'] =
            $this->_helper->getCheckoutConfigFlag('separate_address', $store);

        if ($this->_helper->getPackstationSupport($store)) {
            $create['options']['allow_separate_shipping_address'] = true;
            $create['options']['packstation_enabled'] = true;
        }

        if ($this->_helper->getPhoneMandatorySupport($store)) {
            $create['options']['phone_mandatory'] = $this->_helper->getCheckoutConfigFlag('phone_mandatory', $store);
        }

        if ($this->_helper->getNationalIdentificationNumberMandatorySupport($store)) {
            $create['options']['national_identification_number_mandatory'] = $this->_helper->getCheckoutConfigFlag('national_identification_number_mandatory', $store);
        }

        if ($this->_helper->getCheckoutConfigFlag('dob_mandatory', $store)
            && $this->_helper->getDateOfBirthMandatorySupport($store)
        ) {
            $create['options']['date_of_birth_mandatory'] = true;
        }

        if (count($this->_helper->getAdditionalCheckboxes($store)) > 0) {
            $create['options']['additional_checkboxes'] = $this->_helper->getAdditionalCheckboxes($store);
        }

        if (!$create['options']) {
            unset($create['options']);
        }

        // Merchant checkbox
        if (Mage::helper('klarna_kco')->getVersionConfig($quote->getStore())->getMerchantCheckboxSupport()
            && ($merchantCheckboxMethod = $this->_helper->getCheckoutConfig('merchant_checkbox')) != -1
            && $this->_helper->getMerchantCheckboxEnabled($merchantCheckboxMethod, array('quote' => $quote))
        ) {
            $merchantCheckboxObject = new Varien_Object();
            $merchantCheckboxObject->setText(
                $this->_helper->getCheckoutConfig('merchant_checkbox_text')
                ?: $this->_helper->getMerchantCheckboxText($merchantCheckboxMethod)
            );
            $merchantCheckboxObject->setChecked($this->_helper->getCheckoutConfig('merchant_checkbox_checked') ? 1 : 0);
            $merchantCheckboxObject->setRequired($this->_helper->getCheckoutConfig('merchant_checkbox_required') ? 1 : 0);

            Mage::dispatchEvent(
                'kco_merchant_checkbox', array(
                'merchant_checkbox_object' => $merchantCheckboxObject
                )
            );

            if ($merchantCheckboxObject->getText()) {
                $create['options']['additional_checkbox'] = array(
                    'text'     => $merchantCheckboxObject->getText(),
                    'checked'  => (bool)$merchantCheckboxObject->getChecked(),
                    'required' => (bool)$merchantCheckboxObject->getRequired()
                );
            }
        }

        /**
         * Cart items
         */
        $create['cart']['items'] = $this->getOrderLines();

        $attachmentData =  $this->getAttachmentData();
        if ($attachmentData) {
            $create['attachment'] = $attachmentData;
        }

        /**
         * Merchant reference
         */
        $merchantReferences = new Varien_Object(
            array(
            'merchant_reference1' => $quote->getReservedOrderId()
            )
        );

        Mage::dispatchEvent(
            'kco_merchant_reference_update', array(
            'quote'                     => $quote,
            'merchant_reference_object' => $merchantReferences
            )
        );

        if ($merchantReferences->getData('merchant_reference1')) {
            $create['merchant_reference']['orderid1'] = $merchantReferences->getData('merchant_reference1');
        }

        if (!empty($merchantReferences['merchant_reference2'])) {
            $create['merchant_reference']['orderid2'] = $merchantReferences->getData('merchant_reference2');
        }

        /**
         * Merchant configuration & Urls
         */
        $termsUrl = Mage::getStoreConfig('checkout/klarna_kco/terms_url');
        if (!parse_url($termsUrl, PHP_URL_SCHEME)) {
            $termsUrl = Mage::getUrl($termsUrl, array('_nosid' => true));
        }

        $urlParams = array(
            '_nosid'         => true,
            '_forced_secure' => true
        );

        $create['merchant'] = array(
            'id'               => $this->_helper->getPaymentConfig('merchant_id'),
            'terms_uri'        => rtrim($termsUrl, '/'),
            'checkout_uri'     => Mage::getUrl('checkout/klarna', $urlParams),
            'confirmation_uri' => Mage::getUrl('checkout/klarna/confirmation/id/{checkout.order.id}', $urlParams),
            'push_uri'         => Mage::getUrl('kco/api/push/id/{checkout.order.id}', $urlParams),
            'validation_uri'   => Mage::getUrl('kco/api/validate/id/{checkout.order.id}', $urlParams)
        );

        $this->setRequest($create);

        Mage::dispatchEvent(
            'kco_request_generated_after', array(
                'quote' => $quote,
                'builder' => $this
            )
        );

        return $this;
    }

    /**
     * Generate update request
     *
     * @return $this
     */
    protected function _generateUpdate()
    {
        $create = array('cart' => array('items' => $this->getOrderLines()));

        $this->setRequest($create, self::GENERATE_TYPE_UPDATE);

        return $this;
    }

    /**
     * Auto fill user address details
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string                 $type
     *
     * @return array
     */
    protected function _getAddressData($quote, $type = null)
    {
        $result = array();
        if ($quote->getCustomerEmail()) {
            $result['email'] = $quote->getCustomerEmail();
        }

        $customer = $quote->getCustomer();

        if ($quote->isVirtual() || $type == Mage_Sales_Model_Quote_Address::TYPE_BILLING) {
            $address = $quote->getBillingAddress();

            if ($customer->getId() && !$address->getPostcode()) {
                $address = $customer->getDefaultBillingAddress();
            }
        } else {
            $address = $quote->getShippingAddress();

            if ($customer->getId() && !$address->getPostcode()) {
                $address = $customer->getDefaultShippingAddress();
            }
        }

        $resultObject = new Varien_Object($result);
        if ($address) {
            $address->explodeStreetAddress();
            Mage::helper('core')->copyFieldset('convert_quote_address', 'to_klarna', $address, $resultObject, 'klarna');
        }

        $resultObject->unsStreetAddress2();
        $resultObject->unsRegion();
        if (preg_match('/^([^\d]*[^\d\s]) *(\d.*)$/', $resultObject->getStreetAddress(), $tmp)) {
            $streetName   = isset($tmp[1]) ? $tmp[1] : '';
            $streetNumber = isset($tmp[2]) ? $tmp[2] : '';
            $resultObject->setStreetName($streetName);
            $resultObject->setStreetNumber($streetNumber);
        }

        return array_filter($resultObject->toArray());
    }

    /**
     * Get customer details
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function _getCustomerData($quote)
    {
        $store = $quote->getStore();
        $customerData = array();
        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $customer = $quote->getCustomer();
            if ($this->_helper->isB2bCustomer($customer->getId(), $store)) {
                $customerData['type'] = 'organization';
                $organizationId = $this->_helper->getBusinessIdAttributeValue($customer->getId(), $store);
                if(!empty($organizationId)){
                    $customerData['organization_registration_id'] = $organizationId;
                }
            }

            if ($quote->getCustomerDob()) {
                $customerData = array(
                    'date_of_birth' =>  Varien_Date::formatDate(strtotime($quote->getCustomerDob()), false)
                );
            }
        }

        return $customerData;
    }
}
