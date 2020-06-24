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
 * Klarna shipping method gateway resource
 *
 * Class Klarna_Kco_Model_Resource_Klarnashippingmethodgateway
 */
class Klarna_Kco_Model_Resource_Klarnashippingmethodgateway extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init('klarna_kco/shipping_method_gateway', 'kco_shipping_id');
    }

    /**
     * Load shipping gateway by klarna checkout id
     *
     * @param Klarna_Kco_Model_Klarnashippingmethodgateway $klarnaShippingGateway
     * @param string                                       $checkoutId
     *
     * @return Klarna_Kco_Model_Resource_Klarnashippingmethodgateway
     */
    public function loadByCheckoutId($klarnaShippingGateway, $checkoutId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $this->_getLoadSelect('klarna_checkout_id', $checkoutId, $klarnaShippingGateway);

        $data = $adapter->fetchRow($select);
        if ($data) {
            $klarnaShippingGateway->setData($data);
        }

        $this->_afterLoad($klarnaShippingGateway);

        return $this;
    }
}