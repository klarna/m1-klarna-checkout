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
 * The Klarna Shipping Carrier for our shipping api gateway
 *
 * Class Klarna_Kco_Model_Carrier_Klarna
 */
class Klarna_Kco_Model_Carrier_Klarna extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{

    /** @var string $_code */
    protected $_code = 'klarna_shipping_method_gateway';

    const GATEWAY_KEY = 'klarna_shipping_method_gateway';

    /**
     * @inheritdoc
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        $result = Mage::getModel('shipping/rate_result');

        $method = $this->_createResultMethod();
        $result->append($method);

        return $result;
    }

    /**
     * Creating the result shipping method object
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _createResultMethod()
    {
        $method = Mage::getModel('shipping/rate_result_method');

        $method->setCarrier('klarna');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('shipping_method_gateway');

        /** @var Klarna_Kco_Model_Checkout_Type_Kco $kco */
        $kco = Mage::getSingleton('klarna_kco/checkout_type_kco');
        $shippingGateway = $kco->getKlarnaShippingGateway();

        $method->setMethodTitle($this->getConfigData('name'));
        if ($shippingGateway->getIsPickUpPoint()) {
            $suffixName = ' (' . $shippingGateway->getPickUpPointName() . ')';
            $method->setMethodTitle($this->getConfigData('name') . $suffixName);
        }

        $method->setPrice($shippingGateway->getShippingAmount());
        $method->setCost($shippingGateway->getShippingAmount());
        $method->setAmount($shippingGateway->getShippingAmount());

        return $method;
    }

    /**
     * Return empty set of shipping methods
     * 
     * @return array
     */
    public function getAllowedMethods()
    {
        return array();
    }

    /**
     * @inheritdoc
     */
    public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request)
    {
        /** @var Klarna_Kco_Model_Checkout_Type_Kco $kco */
        $kco = Mage::getSingleton('klarna_kco/checkout_type_kco');

        return $kco->hasActiveKlarnaShippingGateway();
    }
}