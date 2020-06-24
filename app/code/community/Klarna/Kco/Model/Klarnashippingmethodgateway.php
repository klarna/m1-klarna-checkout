<?php
/**
 * Copyright 2019 Klarna Bank AB (publ)
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
 */

/**
 * Getting and setting values from the kco shipping gateway table
 *
 * Class Klarna_Kco_Model_Klarnashippingmethodgateway
 *
 * @method string getIsPickUpPoint()
 * @method string getPickUpPointName()
 * @method string getKlarnaCheckoutId()
 * @method float getShippingAmount()
 * @method float getTaxAmount()
 * @method float getTaxRate()
 * @method bool getIsActive()
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setIsPickUpPoint(bool $flag)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setPickUpPointName(string $name)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setKlarnaCheckoutId(string $id)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setShippingAmount(float $amount)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setTaxAmount(float $amount)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setTaxRate(float $taxRate)
 * @method Klarna_Kco_Model_Klarnashippingmethodgateway setIsActive(bool $flag)
 */
class Klarna_Kco_Model_Klarnashippingmethodgateway  extends Mage_Core_Model_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kco/klarnashippingmethodgateway');
    }

    /**
     * Load by checkout id
     *
     * @param string $checkoutId
     * @return Klarna_Kco_Model_Klarnashippingmethodgateway
     */
    public function loadByCheckoutId($checkoutId)
    {
        return $this->load($checkoutId, 'klarna_checkout_id');
    }

    /**
     * Load by klarna checkout id
     *
     * @param string $checkoutId
     * @return $this
     */
    public function loadByKlarnaCheckoutId($checkoutId)
    {
        $this->_getResource()->loadByCheckoutId($this, $checkoutId);
        $this->_afterLoad();

        return $this;
    }

    /**
     * Returns true when the gateway can be used
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->getData('is_active');
    }
}