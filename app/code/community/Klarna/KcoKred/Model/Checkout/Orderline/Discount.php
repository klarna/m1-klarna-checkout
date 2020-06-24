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
class Klarna_KcoKred_Model_Checkout_Orderline_Discount extends Klarna_Kco_Model_Checkout_Orderline_Discount
{
    /**
     * Add order details to checkout request
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $checkout
     *
     * @return $this
     */
    public function fetch($checkout)
    {
        $discountTaxRate = false;

        if ($checkout->getItems()) {
            $itemTaxRates       = array();
            $totalsIncludingTax = array();
            $totalsExcludingTax = array();
            foreach ($checkout->getItems() as $item) {
                $totalsIncludingTax[] = $item['total_amount'];
                $totalsExcludingTax[] = $item['total_amount'] - $item['total_tax_amount'];
                $itemTaxRates[]       = isset($item['tax_rate']) ? ($item['tax_rate'] * 1) : 0;
            }

            $itemTaxRates = array_unique($itemTaxRates);
            $taxRateCount = count($itemTaxRates);

            if (1 < $taxRateCount) {
                $helper          = Mage::helper('klarna_kco/checkout');
                $discountTaxRate = ((array_sum($totalsIncludingTax) / array_sum($totalsExcludingTax)) - 1) * 100;
                $discountTaxRate = $helper->toApiFloat($discountTaxRate);
            } elseif (1 === $taxRateCount) {
                $discountTaxRate = reset($itemTaxRates);
            }
        }

        if ($checkout->getDiscountReference()) {
            $checkout->addOrderLine(
                array(
                'type'          => self::ITEM_TYPE_DISCOUNT,
                'reference'     => $checkout->getDiscountReference(),
                'name'          => $checkout->getDiscountTitle(),
                'quantity'      => 1,
                'unit_price'    => $checkout->getDiscountTotalAmount(),
                'discount_rate' => 0,
                'tax_rate'      => $discountTaxRate === false ? $checkout->getDiscountTaxRate() : $discountTaxRate
                )
            );
        }

        return $this;
    }
}
