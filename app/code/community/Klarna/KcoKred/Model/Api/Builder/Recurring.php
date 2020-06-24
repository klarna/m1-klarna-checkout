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
 * @author     Fei Chen <fei.chen@klarna.com>
 */

/**
 * Api request builder for recurring order
 */
class Klarna_KcoKred_Model_Api_Builder_Recurring extends Klarna_Kco_Model_Api_Builder_Abstract
{

    /**
     * generate request for create recurring order on kco
     *
     * @param string $type
     *
     * @return $this|array
     *
     * @throws Klarna_Kco_Exception
     */
    public function generateRequest($type = self::GENERATE_TYPE_CREATE)
    {
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
     * @return array
     */
    protected function _generateCreate()
    {
        $profile = $this->getRecurringProfile();
        $merchantOrder = $this->getMerchantOrder();
        $store = Mage::getModel('core/store')->load($profile->getStoreId());
        $create = array();
        $create['purchase_country'] = $this->_helper->getStoreDefaultCountry($store);
        $create['purchase_currency'] = $profile->getCurrencyCode();
        $create['locale'] = str_replace('_', '-', $this->_helper->getStoreLocale($store));

        $create['merchant'] = array(
            'id' => $this->_helper->getPaymentConfig('merchant_id', $store),
        );

        $create['merchant_reference'] = array(
            'orderid1' => $merchantOrder->getIncrementId()
        );
        $create['activate'] = false;
        $create['billing_address'] = $this->_createBillingAddress();
        $create['shipping_address'] = $this->_createShippingAddress();
        $create['cart'] = $this->_createCart();
        return $create;
    }

    /**
     * create request for cart item
     *
     * @return array
     */
    protected function _createCart()
    {
        $profile = $this->getRecurringProfile();
        $orderItem = $profile->getOrderItemInfo();
        $cart = array(
            'items' => array(
                array(
                    'reference' => $orderItem['sku'],
                    'name' => $orderItem['name'],
                    'quantity' => 1,
                    'unit_price' => $this->_helper->toApiFloat($orderItem['price_incl_tax']),
                    'discount_rate' => $this->_helper->toApiFloat($orderItem['discount_amount']),
                    'tax_rate' => $this->_helper->toApiFloat($orderItem['tex_amount'])
                ),
                array(
                    'type' => 'shipping_fee',
                    'reference' => 'shipping',
                    'name' => 'Shipping Fee',
                    'quantity' => 1,
                    'unit_price' => $this->_helper->toApiFloat($orderItem['shipping_amount']),
                    'tax_rate' => $this->_helper->toApiFloat(0)
                )
            )
        );

        return $cart;

    }

    /**
     * create request for billing address
     *
     * @return array
     */
    protected function _createBillingAddress()
    {
        $profile = $this->getRecurringProfile();
        $billingAddressInfo = $profile->getBillingAddressInfo();
        $address = array(
            'postal_code' => $billingAddressInfo['postcode'],
            'email' => $billingAddressInfo['email'],
            'country' => $billingAddressInfo['country_id'],
            'city' => $billingAddressInfo['city'],
            'family_name' => $billingAddressInfo['lastname'],
            'given_name' => $billingAddressInfo['firstname'],
            'street_address' => $billingAddressInfo['street'],
            'phone' => $billingAddressInfo['telephone'],
        );

        return $address;

    }

    /**
     * create request for shipping address
     *
     * @return array
     */
    protected function _createShippingAddress()
    {
        $profile = $this->getRecurringProfile();
        $shippingAddressInfo = $profile->getShippingAddressInfo();
        $address = array(
            'postal_code' => $shippingAddressInfo['postcode'],
            'email' => $shippingAddressInfo['email'],
            'country' => $shippingAddressInfo['country_id'],
            'city' => $shippingAddressInfo['city'],
            'family_name' => $shippingAddressInfo['lastname'],
            'given_name' => $shippingAddressInfo['firstname'],
            'street_address' => $shippingAddressInfo['street'],
            'phone' => $shippingAddressInfo['telephone']
        );
        return $address;
    }

}
