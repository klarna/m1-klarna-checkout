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
 * Klarna checkout summary shipping methods
 */
class Klarna_Kco_Block_Checkout_Summary_Shipping extends Klarna_Kco_Block_Checkout_Summary_Abstract
{
    /**
     * If the shipping form should be displayed
     *
     * @return bool
     */
    public function isEnabled()
    {
        return !Mage::helper('klarna_kco/checkout')->getShippingInIframe() && !$this->getQuote()->isVirtual();
    }

    /**
     * Return form action url
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('checkout/klarna/saveShippingMethod', array('_secure' => true));
    }

    /**
     * Get available shipping rates
     *
     * @return array
     */
    public function getShippingRates()
    {
        if (!$this->hasShippingRates()) {
            $this->getAddress()->collectShippingRates()->save();
            $rates = $this->getAddress()->getGroupedAllShippingRates();
            $this->setShippingRates($rates);
        }

        return $this->getData('shipping_rates');
    }

    /**
     * Get current shipping method used
     *
     * @return string
     */
    public function getAddressShippingMethod()
    {
        return $this->getAddress()->getShippingMethod();
    }

    /**
     * Get quote address
     *
     * @return Mage_Sales_Model_Quote_Address
     */
    public function getAddress()
    {
        if (!$this->hasAddress()) {
            $this->setAddress($this->getQuote()->getShippingAddress());
        }

        return $this->getData('address');
    }

    /**
     * Get carrier name by code
     *
     * @param string $carrierCode
     *
     * @return string
     */
    public function getCarrierName($carrierCode)
    {
        if ($name = Mage::getStoreConfig('carriers/' . $carrierCode . '/title')) {
            return $name;
        }

        return $carrierCode;
    }

    /**
     * Get shipping price for store
     *
     * @param float $price
     * @param bool  $flag
     *
     * @return float
     */
    public function getShippingPrice($price, $flag)
    {
        return $this->getQuote()->getStore()->convertPrice(
            Mage::helper('tax')
            ->getShippingPrice($price, $flag, $this->getAddress()), true
        );
    }
}
