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
 * Klarna checkout
 */
class Klarna_Kco_Block_Checkout extends Mage_Core_Block_Template
{
    /**
     * If checkout is allowed for the current customer
     *
     * Checks if guest checkout is allowed and if the customer is a guest or not
     *
     * @return bool
     */
    public function allowCheckout()
    {
        return $this->getKco()->allowCheckout();
    }

    /**
     * Get customer checkout
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Get checkout quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->getCheckout()->getQuote();
    }

    /**
     * Get one page checkout model
     *
     * @return Klarna_Kco_Model_Checkout_Type_Kco
     */
    public function getKco()
    {
        return Mage::getSingleton('klarna_kco/checkout_type_kco');
    }

    /**
     * Get Klarna snippet from api
     *
     * @return string
     */
    public function getKlarnaHtml()
    {
        if ($snippet = $this->getKco()->getApiInstance()->getKlarnaCheckoutGui()) {
            return $snippet;
        }

        return Mage::helper('klarna_kco')->__(
            'Klarna Checkout has failed to load. Please <a href="javascript:;" onclick="location.reload(true)">reload checkout.</a>'
        );
    }

    /**
     * Get customer registration url
     *
     * @return string
     */
    public function getRegistrationUrl()
    {
        return $this->getUrl('customer/account/create');
    }
}
