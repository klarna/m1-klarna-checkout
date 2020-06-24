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
 * Klarna quote to associate a Klarna quote with a Magento quote
 *
 * @method string getKlarnaCheckoutId()
 * @method int getIsActive()
 * @method int getQuoteId()
 * @method int getIsChanged()
 * @method Klarna_Kco_Model_Klarnaquote setKlarnaCheckoutId(string $value)
 * @method Klarna_Kco_Model_Klarnaquote setIsActive(int $value)
 * @method Klarna_Kco_Model_Klarnaquote setQuoteId(int $value)
 * @method Klarna_Kco_Model_Klarnaquote setIsChanged(int $value)
 */
class Klarna_Kco_Model_Klarnaquote extends Mage_Core_Model_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kco/klarnaquote');
    }

    /**
     * Load by checkout id
     *
     * @param string $checkoutId
     *
     * @return Klarna_Kco_Model_Klarnaquote
     */
    public function loadByCheckoutId($checkoutId)
    {
        return $this->load($checkoutId, 'klarna_checkout_id');
    }

    /**
     * Load active Klarna checkout object by quote
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return Klarna_Kco_Model_Klarnaquote
     */
    public function loadActiveByQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_getResource()->loadActive($this, $quote->getId());
        $this->_afterLoad();

        return $this;
    }
}
