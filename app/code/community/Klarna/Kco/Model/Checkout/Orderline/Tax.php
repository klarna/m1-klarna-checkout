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
 * Generate tax order line details
 */
class Klarna_Kco_Model_Checkout_Orderline_Tax extends Klarna_Kco_Model_Checkout_Orderline_Abstract
{
    /**
     * Checkout item types
     */
    const ITEM_TYPE_TAX = 'sales_tax';

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

        if (!$helper->getSeparateTaxLine($object->getStore())) {
            return $this;
        }

        if ($checkout->getObject() instanceof Mage_Sales_Model_Quote) {
            $totalTax = $object->isVirtual() ? $object->getBillingAddress()->getBaseTaxAmount()
                : $object->getShippingAddress()->getBaseTaxAmount();
        } else {
            $totalTax = $object->getBaseTaxAmount();
        }

        $checkout->addData(
            array(
            'tax_unit_price'   => $helper->toApiFloat($totalTax),
            'tax_total_amount' => $helper->toApiFloat($totalTax)

            )
        );

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
        $helper = Mage::helper('klarna_kco/checkout');

        if ($checkout->getTaxUnitPrice()) {
            $checkout->addOrderLine(
                array(
                'type'             => self::ITEM_TYPE_TAX,
                'reference'        => $helper->__('Sales Tax'),
                'name'             => $helper->__('Sales Tax'),
                'quantity'         => 1,
                'unit_price'       => $checkout->getTaxUnitPrice(),
                'tax_rate'         => 0,
                'total_amount'     => $checkout->getTaxTotalAmount(),
                'total_tax_amount' => 0,
                )
            );
        }

        return $this;
    }
}
