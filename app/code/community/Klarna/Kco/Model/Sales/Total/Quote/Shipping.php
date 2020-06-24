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
 * Class Klarna_Kco_Model_Sales_Total_Quote_Shipping
 */
class Klarna_Kco_Model_Sales_Total_Quote_Shipping extends Mage_Tax_Model_Sales_Total_Quote_Shipping
{
    /**
     * Collect totals information about shipping
     *
     * @param   Mage_Sales_Model_Quote_Address $address
     * @return  Mage_Sales_Model_Quote_Address_Total_Shipping
     */
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        /** @var Klarna_Kco_Model_Checkout_Type_Kco $kco */
        $kco = Mage::getSingleton('klarna_kco/checkout_type_kco');

        /**
         * Until this step no quote is assigned to the kco class.
         * This would mean that we fetch the quote from the database in a later step.
         * On the one hand this is not necessary since we already have the quote in this method which we get from
         * the address instance.
         * On the other hand when no quote is assigned the quote we will end in a endless loop when the flag
         * "trigger_recollect" in the quote is set to 1.
         * See class: Mage_Sales_Model_Quote::_afterLoad().
         * Since our class is already part of the "collectTotals()" workflow we have the following scenario:
         * Collect totals - Klarna shipping class executed - fetch quote - collect totals (endless loop)
         * Therefore the quote must be assigned to avoid this critical issue.
         */
        $kco->setQuote($address->getQuote());

        if (!Mage::helper('klarna_kco')->kcoEnabled() || !$kco->hasActiveKlarnaShippingGateway()) {
            return parent::collect($address);
        }

        $shippingGateway = $kco->getKlarnaShippingGateway();
        return $this->_calculateTaxByRate($address, $shippingGateway->getTaxRate());
    }

    /**
     * Calculating the tax rate by a given rate
     * Its mainly a copy from the method parent::collect($address) but with a own tax rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param int $rate
     * @return $this
     */
    private function _calculateTaxByRate(Mage_Sales_Model_Quote_Address $address, $rate)
    {
        // Calling code which was called from the parent parent collect method
        $this->_address = $address;
        $this->_setAmount(0);
        $this->_setBaseAmount(0);

        $calc               = $this->_calculator;
        $store              = $address->getQuote()->getStore();
        $storeTaxRequest    = $calc->getRateOriginRequest($store);
        $addressTaxRequest  = $calc->getRateRequest(
            $address,
            $address->getQuote()->getBillingAddress(),
            $address->getQuote()->getCustomerTaxClassId(),
            $store
        );

        $shippingTaxClass = $this->_config->getShippingTaxClass($store);
        $storeTaxRequest->setProductClassId($shippingTaxClass);
        $addressTaxRequest->setProductClassId($shippingTaxClass);

        $priceIncludesTax = $this->_config->shippingPriceIncludesTax($store);
        if ($priceIncludesTax) {
            if ($this->_helper->isCrossBorderTradeEnabled($store)) {
                $this->_areTaxRequestsSimilar = true;
            } else {
                $this->_areTaxRequestsSimilar =
                    $this->_calculator->compareRequests($storeTaxRequest, $addressTaxRequest);
            }
        }

        $shipping           = $taxShipping = $address->getShippingAmount();
        $baseShipping       = $baseTaxShipping = $address->getBaseShippingAmount();

        $this->calcShipping($priceIncludesTax, $calc, $shipping, $rate, $baseShipping, $address, $addressTaxRequest, $store);

        return $this;
    }

    /**
     * Calculating the shipping
     *
     * @param bool $priceInclTax
     * @param Mage_Tax_Model_Calculation $calc
     * @param float $shipping
     * @param int $rate
     * @param float $baseShipping
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Varien_Object $addressTaxRequest
     * @param Mage_Core_Model_Store $store
     */
    private function calcShipping(
        $priceInclTax,
        Mage_Tax_Model_Calculation $calc,
        $shipping,
        $rate,
        $baseShipping,
        Mage_Sales_Model_Quote_Address $address,
        $addressTaxRequest,
        $store
    ) {
        if ($priceInclTax) {
            if ($this->_areTaxRequestsSimilar) {
                $tax            = $this->_round($calc->calcTaxAmount($shipping, $rate, true, false), $rate, true);
                $baseTax        = $this->_round(
                    $calc->calcTaxAmount($baseShipping, $rate, true, false), $rate, true, 'base'
                );
                $taxShipping    = $shipping;
                $baseTaxShipping = $baseShipping;
                $shipping       = $shipping - $tax;
                $baseShipping   = $baseShipping - $baseTax;
                $taxable        = $taxShipping;
                $baseTaxable    = $baseTaxShipping;
                $isPriceInclTax = true;
                $address->setTotalAmount('shipping', $shipping);
                $address->setBaseTotalAmount('shipping', $baseShipping);
            } else {
                $storeRate      = $calc->getStoreRate($addressTaxRequest, $store);
                $storeTax       = $calc->calcTaxAmount($shipping, $storeRate, true, false);
                $baseStoreTax   = $calc->calcTaxAmount($baseShipping, $storeRate, true, false);
                $shipping       = $calc->round($shipping - $storeTax);
                $baseShipping   = $calc->round($baseShipping - $baseStoreTax);
                $tax            = $this->_round($calc->calcTaxAmount($shipping, $rate, false, false), $rate, true);
                $baseTax        = $this->_round(
                    $calc->calcTaxAmount($baseShipping, $rate, false, false), $rate, true, 'base'
                );
                $taxShipping    = $shipping + $tax;
                $baseTaxShipping = $baseShipping + $baseTax;
                $taxable        = $taxShipping;
                $baseTaxable    = $baseTaxShipping;
                $isPriceInclTax = true;
                $address->setTotalAmount('shipping', $shipping);
                $address->setBaseTotalAmount('shipping', $baseShipping);
            }
        } else {
            $appliedRates = $calc->getAppliedRates($addressTaxRequest);
            $taxes = array();
            $baseTaxes = array();
            foreach ($appliedRates as $appliedRate) {
                $taxRate = $appliedRate['percent'];
                $taxId = $appliedRate['id'];
                $taxes[] = $this->_round($calc->calcTaxAmount($shipping, $taxRate, false, false), $taxId, false);
                $baseTaxes[] = $this->_round(
                    $calc->calcTaxAmount($baseShipping, $taxRate, false, false), $taxId, false, 'base'
                );
            }

            $tax            = array_sum($taxes);
            $baseTax        = array_sum($baseTaxes);
            $taxShipping    = $shipping + $tax;
            $baseTaxShipping = $baseShipping + $baseTax;
            $taxable        = $shipping;
            $baseTaxable    = $baseShipping;
            $isPriceInclTax = false;
            $address->setTotalAmount('shipping', $shipping);
            $address->setBaseTotalAmount('shipping', $baseShipping);
        }

        $address->setShippingInclTax($taxShipping);
        $address->setBaseShippingInclTax($baseTaxShipping);
        $address->setShippingTaxable($taxable);
        $address->setBaseShippingTaxable($baseTaxable);
        $address->setIsShippingInclTax($isPriceInclTax);
        if ($this->_config->discountTax($store)) {
            $address->setShippingAmountForDiscount($taxShipping);
            $address->setBaseShippingAmountForDiscount($baseTaxShipping);
        }
    }
}