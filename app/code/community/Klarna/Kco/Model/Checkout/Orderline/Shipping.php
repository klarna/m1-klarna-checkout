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
 * Generate shipping order line details
 */
class Klarna_Kco_Model_Checkout_Orderline_Shipping extends Klarna_Kco_Model_Checkout_Orderline_Abstract
{
    /**
     * Checkout item types
     */
    const ITEM_TYPE_SHIPPING = 'shipping_fee';

    /**
     * Collect totals process.
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $checkout
     *
     * @return $this
     */
    public function collect($checkout)
    {
        $object = $checkout->getObject();
        $helper = Mage::helper('klarna_kco/checkout');

        if ($object instanceof Mage_Sales_Model_Quote) {
            $totals = $object->getTotals();
            if (isset($totals['shipping'])) {
                /** @var Mage_Sales_Model_Quote_Address_Total_Shipping $total */
                $total   = $totals['shipping'];
                $address = $total->getAddress();
                $amount  = $address->getBaseShippingAmount();
                $unitPriceAndTax = $this->_calculateUnitPriceAndTax($helper, $object, $address, $amount);

                $checkout->addData(
                    array(
                    'shipping_unit_price'   => $helper->toApiFloat($unitPriceAndTax['unitPrice']),
                    'shipping_tax_rate'     => $helper->toApiFloat($unitPriceAndTax['taxRate']),
                    'shipping_total_amount' => $helper->toApiFloat($unitPriceAndTax['unitPrice']),
                    'shipping_tax_amount'   => $helper->toApiFloat($unitPriceAndTax['taxAmount']),
                    'shipping_title'        => $total->getTitle(),
                    'shipping_reference'    => $total->getCode()

                    )
                );
            }
        }

        if ($object instanceof Mage_Sales_Model_Order_Invoice || $object instanceof Mage_Sales_Model_Order_Creditmemo) {
            $unitPrice = $object->getBaseShippingInclTax();
            $taxRate   = $this->_calculateShippingTax($object);
            $taxAmount = $object->getShippingTaxAmount() + $object->getShippingHiddenTaxAmount();

            $checkout->addData(
                array(
                'shipping_unit_price'   => $helper->toApiFloat($unitPrice),
                'shipping_tax_rate'     => $helper->toApiFloat($taxRate),
                'shipping_total_amount' => $helper->toApiFloat($unitPrice),
                'shipping_tax_amount'   => $helper->toApiFloat($taxAmount),
                'shipping_title'        => 'Shipping',
                'shipping_reference'    => 'shipping'

                )
            );
        }

        return $this;
    }

    /**
     * Add order details to checkout request
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $checkout
     *
     * @return $this
     */
    public function fetch($checkout)
    {
        if ($checkout->getShippingTotalAmount()) {
            $checkout->addOrderLine(
                array(
                'type'             => self::ITEM_TYPE_SHIPPING,
                'reference'        => $checkout->getShippingReference(),
                'name'             => $checkout->getShippingTitle(),
                'quantity'         => 1,
                'unit_price'       => $checkout->getShippingUnitPrice(),
                'tax_rate'         => $checkout->getShippingTaxRate(),
                'total_amount'     => $checkout->getShippingTotalAmount(),
                'total_tax_amount' => $checkout->getShippingTaxAmount(),
                )
            );
        }

        return $this;
    }

    /**
     * Calculate shipping tax rate for an object
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $object
     *
     * @return float
     */
    protected function _calculateShippingTax($object)
    {
        $store     = $object->getStore();
        $taxCalc   = Mage::getModel('tax/calculation');
        $request   = $taxCalc->getRateRequest(
            $object->getShippingAddress(),
            $object->getBillingAddress(),
            $object->getCustomerTaxClassId(),
            $store
        );
        $taxRateId = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS, $store);

        return $taxCalc->getRate($request->setProductClassId($taxRateId));
    }

    /**
     * Calculate and return unit price and tax amount
     *
     * @return array
     */
    protected function _calculateUnitPriceAndTax($helper, $object, $address, $amount)
    {

        /** @var Klarna_Kco_Model_Checkout_Type_Kco $kco */
        $kco = Mage::getSingleton('klarna_kco/checkout_type_kco');
        if ($kco->hasActiveKlarnaShippingGateway()) {
            $shippingGateway = $kco->getKlarnaShippingGateway();
            return array(
                'taxRate' => $shippingGateway->getTaxRate(),
                'taxAmount' => $shippingGateway->getTaxAmount(),
                'unitPrice' => $shippingGateway->getShippingAmount()
            );
        }

        if ($helper->getSeparateTaxLine()) {
            return array(
                'taxRate' => 0,
                'taxAmount' => 0,
                'unitPrice' => $amount
            );
        }

        $taxRate = $this->_calculateShippingTax($object);
        if (!Mage::helper('tax')->shippingPriceIncludesTax()) {
            $taxAmount = $amount * $taxRate / 100;
            return array(
                'taxRate' => $taxRate,
                'taxAmount' => $taxAmount,
                'unitPrice' => $amount + $taxAmount
            );
        }

        $taxAmount = $address->getShippingTaxAmount() + $address->getShippingHiddenTaxAmount();
        return array(
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'unitPrice' => $amount + $taxAmount
        );
    }
}
