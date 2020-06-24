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
 * Class Klarna_Kco_Model_Sales_Total_Quote_Tax
 */
class Klarna_Kco_Model_Sales_Total_Quote_Tax extends Mage_Tax_Model_Sales_Total_Quote_Tax
{
    /**
     * Using the rate which was sent back from the api when using KSS.
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Varien_Object $taxRateRequest
     * @return $this
     */
    protected function _calculateShippingTax(Mage_Sales_Model_Quote_Address $address, $taxRateRequest)
    {
        /** @var Klarna_Kco_Model_Checkout_Type_Kco $kco */
        $kco = Mage::getSingleton('klarna_kco/checkout_type_kco');

        if (!Mage::helper('klarna_kco')->kcoEnabled() || !$kco->hasActiveKlarnaShippingGateway()) {
            return parent::_calculateShippingTax($address, $taxRateRequest);
        }

        $shippingGateway = $kco->getKlarnaShippingGateway();

        $newRate = array(
            array(
                'percent' => $shippingGateway->getTaxRate(),
                'id' => Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY,
                'rates' => array(
                    array(
                        'code' => 'Klarna shipping tax',
                        'title' => __('Klarna shipping tax'),
                        'percent' => $shippingGateway->getTaxRate()
                    )
                )
            )
        );
        $address->setShippingTaxAmount(0);
        $address->setBaseShippingTaxAmount(0);
        $address->setShippingHiddenTaxAmount(0);
        $address->setBaseShippingHiddenTaxAmount(0);
        $address->setAppliedRates($newRate);

        $this->_calculateShippingTaxByRate($address, $shippingGateway->getTaxRate(), $newRate);

        return $this;
    }

}