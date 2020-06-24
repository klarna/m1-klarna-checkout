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
 * Generate order lines for order items
 */
class Klarna_Kco_Model_Checkout_Orderline_Items extends Klarna_Kco_Model_Checkout_Orderline_Abstract
{
    /**
     * Checkout item types
     */
    const ITEM_TYPE_PHYSICAL = 'physical';
    const ITEM_TYPE_VIRTUAL  = 'digital';

    /**
     * Tax calculation model
     *
     * @var Mage_Tax_Model_Calculation
     */
    protected $_calculator;
    /**
     * Order lines is not a total collector, it's a line item collector
     *
     * @var bool
     */
    protected $_isTotalCollector = false;

    /**
     * @var array
     */
    protected $_productAttributes = array();

    /**
     * @var string|null
     */
    protected $_productSizeUnit = null;

    /**
     * @var string|null
     */
    protected $_weightUnit = null;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->_calculator = Mage::getSingleton('tax/calculation');
    }

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
        $items  = array();
        $units = $this->getProductUnits($object->getStore());

        $productCollection = $this->getProductCollection($object->getAllItems(), $units);

        foreach ($object->getAllItems() as $item) {
            $shippingAttributes = null;
            $product = null;
            $qtyMultiplier = 1;

            // Order item checks
            if (($item instanceof Mage_Sales_Model_Order_Invoice_Item
                || $item instanceof Mage_Sales_Model_Order_Creditmemo_Item)
            ) {
                $orderItem  = $item->getOrderItem();
                $parentItem = $orderItem->getParentItem()
                    ?: ($orderItem->getParentItemId() ? $object->getItemById($orderItem->getParentItemId()) : null);

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

            $productUrl = '';
            $imageUrl = '';
            // Quote item checks
            if ($item instanceof Mage_Sales_Model_Quote_Item) {
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

                $store = $item->getStore();
                $product = $item->getProduct();
                $product->setStoreId($store->getId());
                $productUrl = $product->getUrlInStore();
                $imageUrl = $this->getImageUrl($product);

                $shippingAttributes = array(
                    'weight' => $this->getWeight($item, $units['weight']),
                    'tags' => $this->getCategories($product),
                    'dimensions' => $this->getDimensions($item, $units['dimensions'], $units['size'], $productCollection)
                );
            }

            $_item = array(
                'type'          => $item->getIsVirtual() ? self::ITEM_TYPE_VIRTUAL : self::ITEM_TYPE_PHYSICAL,
                'reference'     => substr($item->getSku(), 0, 64),
                'name'          => $item->getName(),
                'quantity'      => ceil($item->getQty() * $qtyMultiplier),
                'discount_rate' => 0,
                'product_url'   => empty($productUrl) ? null : $productUrl,
                'image_url'     => empty($imageUrl) ? null : $imageUrl
            );

            if ($shippingAttributes !== null) {
                $_item['shipping_attributes'] = $shippingAttributes;
            }

            if ($helper->getSeparateTaxLine($object->getStore())) {
                $_item['tax_rate']         = 0;
                $_item['total_tax_amount'] = 0;
                $_item['unit_price']       = $helper->toApiFloat($item->getBasePrice())
                    ?: $helper->toApiFloat($item->getBaseOriginalPrice());
                $_item['total_amount']     = $helper->toApiFloat($item->getBaseRowTotal());
            } else {
                $taxRate = 0;
                if ($item->getBaseRowTotal() > 0) {
                    $taxRate = ($item->getTaxPercent() > 0) ? $item->getTaxPercent()
                        : ($item->getBaseTaxAmount() / $item->getBaseRowTotal() * 100);
                }

                $totalTaxAmount = $this->_calculator->calcTaxAmount($item->getBaseRowTotalInclTax(), $taxRate, true);

                $_item['tax_rate']         = $helper->toApiFloat($taxRate);
                $_item['total_tax_amount'] = $helper->toApiFloat($totalTaxAmount);
                $_item['unit_price']       = $helper->toApiFloat($item->getBasePriceInclTax());
                $_item['total_amount']     = $helper->toApiFloat($item->getBaseRowTotalInclTax());
            }

            $_item = new Varien_Object($_item);
            Mage::dispatchEvent(
                'kco_orderline_item', array(
                'checkout'    => $checkout,
                'object_item' => $item,
                'klarna_item' => $_item
                )
            );

            $items[] = $_item->toArray();

            $checkout->setItems($items);
        }

        return $this;
    }

    /**
     * Getting back a product collection for the items in the cart
     *
     * @param Mage_Sales_Model_Quote_Item[] $items
     * @param array $units
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    private function getProductCollection($items, array $units)
    {
        $ids = array();

        foreach ($items as $item) {
            $ids[] = $item->getProductId();
        }

        /** @var Mage_Catalog_Model_Product $model_product */
        $model_product = Mage::getModel('catalog/product');

        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = $model_product->getCollection();

        $productCollection->addFieldToFilter('entity_id', array('in'=> $ids));

        /**
         * When choosing the "Unselect" field value in the KCO admin checkout configuration the value "0"
         * is assigned to the attribute.
         * Using this value results in a "Invalid attribute requested" error.
         * To be consistent against other corner cases like null or '' we just set the attribute if its not empty.
         */
        if (!empty($units['dimensions']['width'])) {
            $productCollection->addAttributeToSelect($units['dimensions']['width']);
        }

        if (!empty($units['dimensions']['length'])) {
            $productCollection->addAttributeToSelect($units['dimensions']['length']);
        }

        if (!empty($units['dimensions']['height'])) {
            $productCollection->addAttributeToSelect($units['dimensions']['height']);
        }

        $productCollection->addAttributeToSelect('sku');

        $productCollection->load();

        return $productCollection;
    }

    /**
     * Getting back the product units
     *
     * @param Mage_Core_Model_Store $store
     * @return array
     */
    private function getProductUnits($store)
    {
        return array(
            'dimensions' => array(
                'width' => Mage::getStoreConfig('checkout/klarna_kco_shipping/product_width_attribute', $store),
                'length' => Mage::getStoreConfig('checkout/klarna_kco_shipping/product_length_attribute', $store),
                'height' => Mage::getStoreConfig('checkout/klarna_kco_shipping/product_height_attribute', $store)
            ),
            'size' => Mage::getStoreConfig('checkout/klarna_kco_shipping/product_size_unit', $store),
            'weight' => Mage::getStoreConfig('checkout/klarna_kco_shipping/weight_unit', $store)
        );
    }

    /**
     * Getting back the dimension values of the given product
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @param array $dimensionUnit
     * @param string $sizeUnit
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return array
     */
    private function getDimensions($item, array $dimensionUnit, $sizeUnit, $productCollection)
    {
        $productSize = $this->getProductSize($item, $dimensionUnit, $productCollection);
        $multiplicator = $this->getMultiplactor($sizeUnit);

        foreach ($productSize as $key => $value) {
            $productSize[$key] = round($value * $multiplicator);
        }

        return $productSize;
    }

    /**
     * Getting back the product size
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @param array $dimensionUnit
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return array
     */
    private function getProductSize($item, array $dimensionUnit, $productCollection)
    {
        $dimensions = array(
            'width' => 0,
            'length' => 0,
            'height' => 0
        );

        $items = $productCollection->getItemsByColumnValue('sku', $item->getSku());
        if (count($items) === 0) {
            return $dimensions;
        }

        $product = array_shift($items);

        if ($product !== null) {
            $dimensions['width'] = $product->getData($dimensionUnit['width']);
            $dimensions['length'] = $product->getData($dimensionUnit['length']);
            $dimensions['height'] = $product->getData($dimensionUnit['height']);
        }

        return $dimensions;
    }

    /**
     * Getting back the multiplicator for the product dimension calculation
     *
     * @param string $sizeUnit
     * @return float|int
     */
    private function getMultiplactor($sizeUnit)
    {
        $multiplicator = 1;
        if ($sizeUnit === 'cm') {
            $multiplicator = 10;
        } elseif ($sizeUnit === 'inch') {
            $multiplicator = 25.4;
        }

        return $multiplicator;
    }

    /**
     * Getting back the weight of the product in millimeters
     *
     * @param Mage_Sales_Model_Quote_Item $item
     * @param string $weightUnit
     * @return int
     */
    private function getWeight($item, $weightUnit)
    {
        $weight = $item->getWeight();

        switch ($weightUnit) {
            case 'kg':
                $weight *= 1000;
                break;
            case 'lbs':
                $weight *= 1000/2.2046;
                break;
            case 'oz':
                $weight *= 28.35;
                break;
        }

        return round($weight);
    }

    /**
     * Getting back the categories of the product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    private function getCategories($product)
    {
        $categories = array();

        if ($product === null) {
            return $categories;
        }

        $collection = $product->getCategoryCollection();
        $collection->addNameToResult();
        $productCategories = $collection->getItems();

        foreach ($productCategories as $category) {
            $categories[] = $category->getName();
        }

        return $categories;
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
        if ($checkout->getItems()) {
            foreach ($checkout->getItems() as $item) {
                $checkout->addOrderLine($item);
            }
        }

        return $this;
    }

    /**
     * Get image for product
     *
     * @param Product $product
     * @return string
     */
    protected function getImageUrl($product)
    {
        if (!$product->getSmallImage()) {
            return null;
        }

        $baseUrl = Mage::app()->getStore($product->getStoreId())->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        return $baseUrl . 'catalog/product' . $product->getSmallImage();
    }
}
