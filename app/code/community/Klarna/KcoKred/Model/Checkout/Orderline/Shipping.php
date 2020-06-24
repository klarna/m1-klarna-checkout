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
class Klarna_KcoKred_Model_Checkout_Orderline_Shipping extends Klarna_Kco_Model_Checkout_Orderline_Shipping
{

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
        $kcoHelper = Mage::helper('klarna_kcokred');

        if ($object instanceof Mage_Sales_Model_Quote) {
            $totals = $object->getTotals();
            if (isset($totals['shipping'])) {
                /** @var Mage_Sales_Model_Quote_Address_Total_Shipping $total */
                $total = $totals['shipping'];
                $address = $total->getAddress();
                $amount = $address->getBaseShippingAmount();

                //get shipping amount for nominal product
                if ($kcoHelper->isKcoRecurringOrder()) {
                    $items = $address->getAllNominalItems();
                    if (count($items)) {
                        foreach ($items as $item) {
                            if (!$item->getProduct()->isVirtual()) {
                                $address->requestShippingRates($item);
                                $baseAmount = $item->getBaseShippingAmount();
                                if ($baseAmount) {
                                    $amount += $baseAmount;
                                }
                            }
                        }
                    }
                }

                if ($helper->getSeparateTaxLine()) {
                    $unitPrice = $amount;
                    $taxRate = 0;
                    $taxAmount = 0;
                } else {
                    $taxRate = $this->_calculateShippingTax($object);
                    $taxAmount = $address->getShippingTaxAmount() + $address->getShippingHiddenTaxAmount();
                    $unitPrice = $amount + $taxAmount;
                }

                $checkout->addData(
                    array(
                        'shipping_unit_price'   => $helper->toApiFloat($unitPrice),
                        'shipping_tax_rate'     => $helper->toApiFloat($taxRate),
                        'shipping_total_amount' => $helper->toApiFloat($unitPrice),
                        'shipping_tax_amount'   => $helper->toApiFloat($taxAmount),
                        'shipping_title'        => $total->getTitle(),
                        'shipping_reference'    => $total->getCode()

                    )
                );
            }
        }

        if ($object instanceof Mage_Sales_Model_Order_Invoice || $object instanceof Mage_Sales_Model_Order_Creditmemo) {
            $unitPrice = $object->getBaseShippingInclTax();
            $taxRate = $this->_calculateShippingTax($object);
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
        $kcoHelper = Mage::helper('klarna_kcokred');
        $helper = Mage::helper('klarna_kco/checkout');

        if ($checkout->getShippingTotalAmount() || $kcoHelper->isKcoRecurringOrder()) {
            $shippingUnitPrice = $checkout->getShippingTotalAmount();
            //for recurring order
            if ($kcoHelper->isKcoRecurringOrder()) {
                $shippingUnitPrice = 0;
                $object = $checkout->getObject();
                $totals = $object->getTotals();
                if (isset($totals['shipping'])) {
                    $total = $totals['shipping'];
                    $address = $total->getAddress();
                    $items = $address->getAllNominalItems();
                    if (count($items)) {
                        foreach ($items as $item) {
                            if (!$item->getProduct()->isVirtual()) {
                                $address->requestShippingRates($item);
                                $baseAmount = $item->getBaseShippingAmount();
                                if ($baseAmount) {
                                    $shippingUnitPrice += $baseAmount;
                                }
                            }
                        }
                    }
                }

                $shippingUnitPrice = $helper->toApiFloat($shippingUnitPrice);
            }

            $checkout->addOrderLine(
                array(
                    'type'          => self::ITEM_TYPE_SHIPPING,
                    'reference'     => $checkout->getShippingReference(),
                    'name'          => $checkout->getShippingTitle(),
                    'quantity'      => 1,
                    'unit_price'    => $shippingUnitPrice,
                    'discount_rate' => 0,
                    'tax_rate'      => $checkout->getShippingTaxRate()
                )
            );
        }

        return $this;
    }
}
