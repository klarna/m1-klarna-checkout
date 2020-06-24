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
 * Generate order lines for discounts
 */
class Klarna_Kco_Model_Checkout_Orderline_Discount extends Klarna_Kco_Model_Checkout_Orderline_Abstract
{
    /**
     * Checkout item type
     */
    const ITEM_TYPE_DISCOUNT = 'discount';

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
        $totals = $object->getTotals();
        $helper = Mage::helper('klarna_kco/checkout');
        $store = $object->getStore();

        if (is_array($totals) && isset($totals['discount'])) {
            /** @var Mage_Sales_Model_Quote_Address_Total_Discount $total */
            $total = $totals['discount'];
            $taxRate = $this->getDiscountTaxRate($checkout, $object->getAllItems());

            $taxAmount = $this->getDiscountTaxAmount($object->getAllItems(), $total, $taxRate, $store);
            $amount = -$total->getValue();

            if ($helper->getSeparateTaxLine()) {
                $unitPrice   = $amount;
                $totalAmount = $amount;
                $taxRate     = 0;
                $taxAmount   = 0;
            } else {
                $taxAmountToAdd = $taxAmount;
                if (Mage::helper('tax')->discountTax($store)) {
                    $taxAmountToAdd = 0;
                }

                $unitPrice = $amount + $taxAmountToAdd;
                $totalAmount = $amount + $taxAmountToAdd;
            }

            $checkout->addData(
                array(
                    'discount_unit_price'   => -$helper->toApiFloat($unitPrice),
                    'discount_tax_rate'     => $helper->toApiFloat($taxRate),
                    'discount_total_amount' => -$helper->toApiFloat($totalAmount),
                    'discount_tax_amount'   => -$helper->toApiFloat($taxAmount),
                    'discount_title'        => $total->getTitle(),
                    'discount_reference'    => $total->getCode()

                )
            );
        } elseif (((float)$object->getDiscountAmount()) != 0) {
            if ($object->getDiscountDescription()) {
                $discountLabel = Mage::helper('sales')->__('Discount (%s)', $object->getDiscountDescription());
            } else {
                $discountLabel = Mage::helper('sales')->__('Discount');
            }

            $taxAmount = $object->getBaseHiddenTaxAmount();
            $amount    = -$object->getBaseDiscountAmount() - $taxAmount;

            if ($helper->getSeparateTaxLine()) {
                $unitPrice   = $amount;
                $totalAmount = $amount;
                $taxRate     = 0;
                $taxAmount   = 0;
            } else {
                $taxRate     = $this->getDiscountTaxRate($checkout);
                $unitPrice   = $amount + $taxAmount;
                $totalAmount = $amount + $taxAmount;
            }

            $checkout->addData(
                array(
                    'discount_unit_price'   => -$helper->toApiFloat($unitPrice),
                    'discount_tax_rate'     => $helper->toApiFloat($taxRate),
                    'discount_total_amount' => -$helper->toApiFloat($totalAmount),
                    'discount_tax_amount'   => -$helper->toApiFloat($taxAmount),
                    'discount_title'        => $discountLabel,
                    'discount_reference'    => self::ITEM_TYPE_DISCOUNT

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
        if ($checkout->getDiscountReference()) {
            $checkout->addOrderLine(
                array(
                    'type'             => self::ITEM_TYPE_DISCOUNT,
                    'reference'        => $checkout->getDiscountReference(),
                    'name'             => $checkout->getDiscountTitle(),
                    'quantity'         => 1,
                    'unit_price'       => $checkout->getDiscountUnitPrice(),
                    'tax_rate'         => $checkout->getDiscountTaxRate(),
                    'total_amount'     => $checkout->getDiscountTotalAmount(),
                    'total_tax_amount' => $checkout->getDiscountTaxAmount(),
                )
            );
        }

        return $this;
    }

    /**
     * Get the tax rate for the discount order line
     *
     * @param $checkout
     *
     * @return float
     */
    protected function getDiscountTaxRate($checkout)
    {
        $quote = $checkout->getObject();
        $discountInfo = array();
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getParentItem()) {
                continue;
            }

            if ($item->getDiscountAmount()) {
                $discountInfo[] = array('amount' => $item->getDiscountAmount(), 'taxRate' => $item->getTaxPercent());
            }
        }

        if (count($discountInfo) > 1) {
            $totalTaxValue = 0;
            $totalDiscountValue = 0;
            foreach ($discountInfo as $key => $discount) {
                $totalDiscountValue += $discount['amount'];
                $totalTaxValue += $discount['amount'] * ($discount['taxRate'] / 100);
            }

            return $totalTaxValue / $totalDiscountValue * 100;
        }

        return count($discountInfo) == 1 ? $discountInfo[0]['taxRate'] : 0;
    }

    /**
     * Get tax amount for discount
     *
     * @param Item[] $items
     * @param array  $total
     * @param float  $taxRate
     * @param Mage_Core_Model_Store $store
     *
     * @return float
     */
    public function getDiscountTaxAmount($items, $total, $taxRate, $store = null)
    {
        $taxAmount = 0;
        foreach ($items as $item) {
            if ($item->getBaseDiscountAmount() == 0) {
                continue;
            }

            $taxAmount += $item->getDiscountTaxCompensationAmount();
        }

        if ($taxAmount === 0) {
            $taxRate = $taxRate > 1 ? $taxRate / 100 : $taxRate;
            if (Mage::helper('tax')->discountTax($store)) {
                return -($total->getValue() * $taxRate / (1 + $taxRate));
            }

            return -($total->getValue() * $taxRate);
        }

        return $taxAmount;
    }
}
