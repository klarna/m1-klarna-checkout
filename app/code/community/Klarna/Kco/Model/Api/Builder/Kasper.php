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
 * @author     Fei Chen <fei.chen@klarna.com>
 */

/**
 * Api request builder for Kasper
 */
class Klarna_Kco_Model_Api_Builder_Kasper extends Klarna_Kco_Model_Api_Builder_Abstract
{
    /**
     * Generate KCO request
     *
     * @param string $type
     *
     * @return $this
     */
    public function generateRequest($type = self::GENERATE_TYPE_CREATE)
    {
        parent::generateRequest($type);

        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $this->getObject();
        $store = $quote->getStore();
        $create = array();

        $create['purchase_country'] = $this->_helper->getDefaultCountry();
        $create['purchase_currency'] = $store->getBaseCurrencyCode();
        $create['locale'] = str_replace('_', '-', Mage::app()->getLocale()->getLocaleCode());

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

            $create = $this->_cleanUpShippingAddress($create);
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
        if (!$this->getIsAdmin()
            && ($enabledExternalMethods = $this->_helper->getPaymentConfig('external_payment_methods'))
        ) {
            $externalMethods = array();
            foreach (explode(',', $enabledExternalMethods) as $externalMethod) {
                $methodDetails = $this->_helper->getExternalPaymentDetails($externalMethod);
                if (!$methodDetails->isEmpty()) {
                    $externalMethods[] = $methodDetails->toArray();
                }
            }

            if ($externalMethods) {
                $create['external_payment_methods'] = $externalMethods;
            }
        }

        /**
         * Options
         */
        $create['options'] = array_map('trim', array_filter($this->_helper->getCheckoutDesignConfig($store)));

        /**
         * allow b2b checkout mode if config is enabled
         */
        if ($this->_helper->isB2bEnabled($store)) {
            $create['options']['allowed_customer_types'] = array('person', 'organization');
        }

        $create['options']['allow_separate_shipping_address'] = false;

        if ($this->_helper->getCheckoutConfigFlag('separate_address', $store)
            && (!$this->getIsAdmin()
                || ($this->getIsAdmin() && !$quote->getShippingAddress()->getSameAsBilling()))
        ) {
            $create['options']['allow_separate_shipping_address'] = true;
        }

        if ($this->_helper->getPhoneMandatorySupport($store)) {
            $create['options']['phone_mandatory'] = $this->_helper->getCheckoutConfigFlag('phone_mandatory', $store);
        }

        if (!$this->_helper->getCheckoutConfigFlag('title_mandatory', $store)
            || !$this->_helper->getTitleMandatorySupport($store)
        ) {
            $create['options']['title_mandatory'] = false;
        }

        if ($this->_helper->getDateOfBirthMandatorySupport($store)) {
            $create['options']['date_of_birth_mandatory'] = $this->_helper->getCheckoutConfigFlag('dob_mandatory', $store);
        }

        if ($this->_helper->getNationalIdentificationNumberMandatorySupport($store)) {
            $create['options']['national_identification_number_mandatory'] = $this->_helper->getCheckoutConfigFlag('national_identification_number_mandatory', $store);
        }

        $additionalCheckboxes = $this->_helper->getAdditionalCheckboxes($store);
        if (count($additionalCheckboxes) > 0) {
            $create['options']['additional_checkboxes'] = $additionalCheckboxes;
        }

        $create['options']['require_validate_callback_success'] = true;

        if ($this->getIsAdmin() && $this->_helper->getMotoEnabled($store)) {
            $create['options']['acquiring_channel'] = 'MOTO';
        }

        // Merchant checkbox
        if (!$this->getIsAdmin()
            && Mage::helper('klarna_kco')->getVersionConfig($quote->getStore())->getMerchantCheckboxSupport()
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
                    'text' => $merchantCheckboxObject->getText(),
                    'checked' => (bool)$merchantCheckboxObject->getChecked(),
                    'required' => (bool)$merchantCheckboxObject->getRequired()
                );
            }
        }

        /**
         * Shipping methods drop down
         */
        if (!$this->getIsAdmin() && $this->_helper->getShippingInIframe($store)) {
            $create['shipping_options'] = $this->_getShippingMethods($quote);
        }

        /**
         * Totals
         */
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $create['order_amount'] = $this->_helper->toApiFloat($address->getBaseGrandTotal());
        $create['order_lines'] = $this->getOrderLines();

        $attachmentData = $this->getAttachmentData();
        if ($attachmentData) {
            $create['attachment'] = $attachmentData;
        }

        $create['order_tax_amount'] = $this->_helper->toApiFloat($address->getBaseTaxAmount());

        if ($shippingCountries = $this->_helper->getPaymentConfig('shipping_countries', $store)) {
            $create['shipping_countries'] = explode(',', $shippingCountries);
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
                'quote' => $quote,
                'merchant_reference_object' => $merchantReferences
            )
        );

        if ($merchantReferences->getData('merchant_reference1')) {
            $create['merchant_reference1'] = $merchantReferences->getData('merchant_reference1');
        }

        if (!empty($merchantReferences['merchant_reference2'])) {
            $create['merchant_reference2'] = $merchantReferences->getData('merchant_reference2');
        }

        /**
         * Urls
         */
        $termsUrl = Mage::getStoreConfig('checkout/klarna_kco/terms_url');
        if (!parse_url($termsUrl, PHP_URL_SCHEME)) {
            $termsUrl = Mage::getUrl($termsUrl, array('_nosid' => true));
        }

        $urlParams = array(
            '_nosid' => true,
            '_forced_secure' => true
        );

        $create['merchant_urls'] = array(
            'terms' => $termsUrl,
            'checkout' => Mage::getUrl('checkout/klarna', $urlParams),
            'push' => Mage::getUrl('kco/api/push/id/{checkout.order.id}', $urlParams),
            'notification' => Mage::getUrl('kco/api/notification/id/{checkout.order.id}', $urlParams)
        );

        if ($this->getIsAdmin()) {
            $create['merchant_urls'] = array_merge(
                $create['merchant_urls'], array(
                    'confirmation' => Mage::helper('adminhtml')->getUrl('*/klarna/confirmation/id/{checkout.order.id}'),
                )
            );
        } else {
            $create['merchant_urls'] = array_merge(
                $create['merchant_urls'], array(
                    'confirmation' => Mage::getUrl('checkout/klarna/confirmation/id/{checkout.order.id}', $urlParams),
                    'address_update' => Mage::getUrl('kco/api/addressUpdate/id/{checkout.order.id}', $urlParams),
                    'validation' => Mage::getUrl('kco/api/validate/id/{checkout.order.id}', $urlParams),
                )
            );

            if ($this->_helper->getShippingInIframe($store)) {
                $create['merchant_urls']['shipping_option_update'] = Mage::getUrl(
                    'kco/api/shippingMethodUpdate/id/{checkout.order.id}', $urlParams
                );
            }
        }

        $this->setRequest($create);

        return $this;
    }

    /**
     * Removing the shipping address if it just has a email field.
     *
     * @param array $create
     * @return array
     */
    protected function _cleanUpShippingAddress(array $create)
    {
        if (count($create['shipping_address']) !== 1) {
            return $create;
        }

        if (!isset($create['shipping_address']['email'])) {
            return $create;
        }

        if ($create['shipping_address']['email'] !== $create['billing_address']['email']) {
            return $create;
        }

        unset($create['shipping_address']);
        return $create;
    }

    /**
     * Auto fill user address details
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $type
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

        return array_filter($resultObject->toArray());
    }

    /**
     * Get available shipping methods for a quote for the api init
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    protected function _getShippingMethods($quote)
    {
        $rates = array();
        if ($quote->isVirtual()) {
            return $rates;
        }

        /** @var Mage_Sales_Model_Quote_Address_Rate $rate */
        foreach ($quote->getShippingAddress()->getAllShippingRates() as $rate) {
            if (!$rate->getCode() || !$rate->getMethodTitle()) {
                continue;
            }

            if ($rate->getCode() === Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY) {
                // When there is a fallback to the native shop shipping methods
                // we don't want to show the klarna carrier as a clickable carrier
                continue;
            }

            $rates[] = $this->_getShippingMethodItem(
                $rate->getCode(),
                $rate->getMethodTitle(),
                $this->_helper->toApiFloat($rate->getPrice()),
                $rate->getMethodDescription(),
                $rate->getCode() == $quote->getShippingAddress()->getShippingMethod()
            );
        }

        return $rates;
    }

    /**
     * Getting back the shipping method item
     *
     * @param string $id
     * @param string $name
     * @param float $price
     * @param string $description
     * @param bool $preselected
     * @return array
     */
    protected function _getShippingMethodItem($id, $name, $price, $description = '', $preselected = false)
    {
        return array(
            'id'          => $id,
            'name'        => $name,
            'price'       => $price,
            'promo'       => '',
            'tax_amount'  => 0,
            'tax_rate'    => 0,
            'description' => $description,
            'preselected' => $preselected
        );
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
                if (!empty($organizationId)) {
                    $customerData['organization_registration_id'] = $organizationId;
                }
            }

            if ($quote->getCustomerDob()) {
                $customerData = array(
                    'date_of_birth' => Varien_Date::formatDate(strtotime($quote->getCustomerDob()), false)
                );
            }
        }

        return $customerData;
    }
}
