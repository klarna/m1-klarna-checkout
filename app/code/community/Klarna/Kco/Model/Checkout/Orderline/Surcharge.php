<?php
/**
 * Copyright 2016 Klarna AB
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
 * @author     Dario Kaßler <dario.kassler@klarna.com>
 */

/**
 * Generate tax order line details
 *
 * Class Klarna_Kco_Model_Checkout_Orderline_Surcharge
 */
class Klarna_Kco_Model_Checkout_Orderline_Surcharge extends Klarna_Kco_Model_Checkout_Orderline_Abstract
{
    const ITEM_TYPE_SURCHARGE = 'surcharge';

    /**
     * Collect totals process.
     *
     * @param Klarna_Kco_Model_Checkout_Orderline_Surcharge $checkout
     *
     * @return $this
     */
    public function collect($checkout)
    {
        /** @var Mage_Sales_Model_Quote $object */
        $object = $checkout->getObject();

        /** @var Klarna_Kco_Helper_Checkout $helper */
        $helper = Mage::helper('klarna_kco/checkout');

        if (!$helper->getDisplayInSubtotalFPT($object->getStore())) {
            return $this;
        }

        $totalTax = 0;
        $name = array();
        $reference = array();

        foreach ($object->getAllItems() as $item) {
            $qtyMultiplier = 1;
            $product = null;

            // Order item checks
            if (($item instanceof Mage_Sales_Model_Order_Invoice_Item
                || $item instanceof Mage_Sales_Model_Order_Creditmemo_Item)
            ) {
                $orderItem  = $item->getOrderItem();
                $parentItem = $orderItem->getParentItem()
                    ?: ($orderItem->getParentItemId() ? $object->getItemById($orderItem->getParentItemId()) : null);
                $product = $orderItem->getProduct();

                // Skip if child product of a non bundle parent
                if ($parentItem && Mage_Catalog_Model_Product_Type::TYPE_BUNDLE != $parentItem->getProductType()) {
                    continue;
                }

                // Skip if a bundled product with price type dynamic
                if ((Mage_Catalog_Model_Product_Type::TYPE_BUNDLE == $orderItem->getProductType()
                    && Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC == $orderItem->getProduct()->getPriceType())
                ) {
                    continue;
                }

                // Skip if child product of a bundle parent and bundle product price type is fixed
                if ($parentItem && Mage_Catalog_Model_Product_Type::TYPE_BUNDLE == $parentItem->getProductType()
                    && Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED == $parentItem->getProduct()->getPriceType()
                ) {
                    continue;
                }

                // Skip if parent is a bundle product having price type dynamic
                if ($parentItem && Mage_Catalog_Model_Product_Type::TYPE_BUNDLE == $orderItem->getProductType()
                    && Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC == $orderItem->getProduct()->getPriceType()
                ) {
                    continue;
                }
            }

            // Quote item checks
            if ($item instanceof Mage_Sales_Model_Quote_Item) {
                $product = $item->getProduct();

                // Skip if bundle product with a dynamic price type
                if (Mage_Catalog_Model_Product_Type::TYPE_BUNDLE == $item->getProductType()
                    && Mage_Bundle_Model_Product_Price::PRICE_TYPE_DYNAMIC == $item->getProduct()->getPriceType()
                ) {
                    continue;
                }

                // Get quantity multiplier for bundle products
                if ($item->getParentItemId() && ($parentItem = $object->getItemById($item->getParentItemId()))) {
                    // Skip if non bundle product or if bundled product with a fixed price type
                    if (Mage_Catalog_Model_Product_Type::TYPE_BUNDLE != $parentItem->getProductType()
                        || Mage_Bundle_Model_Product_Price::PRICE_TYPE_FIXED == $parentItem->getProduct()->getPriceType()
                    ) {
                        continue;
                    }

                    $qtyMultiplier = $parentItem->getQty();
                }
            }

            $totalTax += $item->getWeeeTaxAppliedRowAmount();

            $attributes = Mage::helper('weee/data')->getProductWeeeAttributes(
                $product,
                $this->getShippingAddress($object),
                $this->getBillingAddress($object),
                $object->getStore()->getWebsiteId()
            );

            foreach ($attributes as $attribute) {
                $name[] = $attribute->getName();
                $reference[] = $attribute->getName();
            }
        }

        $name = array_unique($name);
        $reference = array_unique($reference);

        $checkout->addData(
            array(
                'surcharge_unit_price'   => $helper->toApiFloat($totalTax),
                'surcharge_total_amount' => $helper->toApiFloat($totalTax),
                'surcharge_reference'        => implode(',', $reference),
                'surcharge_name'             => implode(',', $name)

            )
        );

        return $this;
    }

    /**
     * Get Shipping Address from quote
     *
     * @param $object
     * @return Mage_Sales_Model_Quote_Address
     */
    private function getShippingAddress($object)
    {
        if ($object instanceof Mage_Sales_Model_Order) {
            $quote = new Mage_Sales_Model_Quote();
            $quote->load($object->getQuoteId());
            return $quote->getShippingAddress();
        }

        if ($object instanceof Mage_Sales_Model_Order_Invoice || $object instanceof Mage_Sales_Model_Order_Creditmemo) {
            $order = $object->getOrder();
            $quote = new Mage_Sales_Model_Quote();
            $quote->load($order->getQuoteId());
            return $quote->getShippingAddress();
        }

        return $object->getShippingAddress();
    }

    /**
     * Get Billing Address from quote
     *
     * @param $object
     * @return Mage_Sales_Model_Quote_Address
     */
    private function getBillingAddress($object)
    {
        if ($object instanceof Mage_Sales_Model_Order) {
            $quote = new Mage_Sales_Model_Quote();
            $quote->load($object->getQuoteId());
            return $quote->getBillingAddress();
        }

        if ($object instanceof Mage_Sales_Model_Order_Invoice || $object instanceof Mage_Sales_Model_Order_Creditmemo) {
            $order = $object->getOrder();
            $quote = new Mage_Sales_Model_Quote();
            $quote->load($order->getQuoteId());
            return $quote->getBillingAddress();
        }

        return $object->getBillingAddress();
    }

    /**
     * Add order details to checkout request
     *
     * @param Klarna_Kco_Model_Checkout_Orderline_Surcharge $checkout
     *
     * @return $this
     */
    public function fetch($checkout)
    {
        if ($checkout->getSurchargeUnitPrice()) {
            $checkout->addOrderLine(
                array(
                    'type'             => self::ITEM_TYPE_SURCHARGE,
                    'reference'        => $checkout->getSurchargeReference(),
                    'name'             => $checkout->getSurchargeName(),
                    'quantity'         => 1,
                    'unit_price'       => $checkout->getSurchargeUnitPrice(),
                    'tax_rate'         => 0,
                    'total_amount'     => $checkout->getSurchargeTotalAmount(),
                    'total_tax_amount' => 0,
                )
            );
        }

        return $this;
    }

}
