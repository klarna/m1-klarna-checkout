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
 * Abstract class to generate checkout configuration
 *
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingUnitPrice($integer)
 * @method int getShippingUnitPrice()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingTaxRate($integer)
 * @method int getShippingTaxRate()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingTotalAmount($integer)
 * @method int getShippingTotalAmount()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingTaxAmount($integer)
 * @method int getShippingTaxAmount()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingTitle($integer)
 * @method int getShippingTitle()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setShippingReference($integer)
 * @method int getShippingReference()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountUnitPrice($integer)
 * @method int getDiscountUnitPrice()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountTaxRate($integer)
 * @method int getDiscountTaxRate()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountTotalAmount($integer)
 * @method int getDiscountTotalAmount()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountTaxAmount($integer)
 * @method int getDiscountTaxAmount()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountTitle($integer)
 * @method int getDiscountTitle()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setDiscountReference($integer)
 * @method int getDiscountReference()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setTaxUnitPrice($integer)
 * @method int getTaxUnitPrice()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setTaxTotalAmount($integer)
 * @method int getTaxTotalAmount()
 * @method Klarna_Kco_Model_Api_Builder_Abstract setItems(array $array)
 * @method array getItems()
 */
class Klarna_Kco_Model_Api_Builder_Abstract extends Varien_Object
{
    /**
     * @var Klarna_Kco_Model_Checkout_Orderline_Collector
     */
    protected $_orderLineCollector = null;

    /**
     * @var null
     */
    protected $_attachmentDataCollector = null;

    /**
     * @var array
     */
    protected $_orderLines = array();
    /**
     * @var array
     */
    protected $_attachmentData = array();

    /**
     * @var Mage_Sales_Model_Abstract|Mage_Sales_Model_Quote
     */
    protected $_object = null;

    /**
     * @var Klarna_Kco_Helper_Checkout
     */
    protected $_helper = null;

    /**
     * @var array
     */
    protected $_request = array();

    /**
     * @var bool
     */
    protected $_inRequestSet = false;

    /**
     * @var bool
     */
    protected $_isAdmin = false;

    /**
     * Generate types
     */
    const GENERATE_TYPE_CREATE = 'create';
    /**
     *
     */
    const GENERATE_TYPE_UPDATE = 'update';

    /**
     * Init
     */
    public function _construct()
    {
        $this->_helper = Mage::helper('klarna_kco/checkout');
    }

    /**
     * Check if is an admin order request
     *
     * @return bool
     */
    public function getIsAdmin()
    {
        return $this->_isAdmin;
    }

    /**
     * Set if is admin order request
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setIsAdmin($bool)
    {
        $this->_isAdmin = (bool)$bool;

        return $this;
    }

    /**
     * Generate KCO order body
     *
     * @param string $type
     *
     * @return $this
     */
    public function generateRequest($type = self::GENERATE_TYPE_CREATE)
    {
        $this->collectOrderLines();

        $this->collectAttachmentData();

        //$this->setRequest(array(), $type);

        return $this;
    }

    /**
     * Get request
     *
     * @return array
     */
    public function getRequest()
    {
        return $this->_request;
    }

    /**
     * Set generated request
     *
     * @param array  $request
     * @param string $type
     *
     * @return $this
     */
    public function setRequest(array $request, $type = self::GENERATE_TYPE_CREATE)
    {
        $this->_request = $request;

        if (!$this->_inRequestSet) {
            $this->_inRequestSet = true;
            Mage::dispatchEvent(
                "kco_builder_set_request_{$type}", array(
                'builder' => $this
                )
            );

            Mage::dispatchEvent(
                'kco_builder_set_request', array(
                'builder' => $this
                )
            );
            $this->_inRequestSet = false;
        }

        return $this;
    }

    /**
     * Get the object used to generate request
     *
     * @return Mage_Sales_Model_Abstract|Mage_Sales_Model_Quote
     */
    public function getObject()
    {
        return $this->_object;
    }

    /**
     * Set the object used to generate request
     *
     * @param Mage_Sales_Model_Abstract|Mage_Sales_Model_Quote $object
     *
     * @return $this
     */
    public function setObject($object)
    {
        $this->_object = $object;

        return $this;
    }

    /**
     * Get totals collector model
     *
     * @return Klarna_Kco_Model_Checkout_Orderline_Collector
     */
    public function getOrderLinesCollector()
    {
        if (null === $this->_orderLineCollector) {
            $this->_orderLineCollector = Mage::getSingleton(
                'klarna_kco/checkout_orderline_collector',
                array('store' => $this->getObject()->getStore())
            );
        }

        return $this->_orderLineCollector;
    }

    /**
     * Get attachment collector model
     *
     * @return Mage_Core_Model_Abstract|null
     */
    public function getAttachmentDataCollector()
    {
        if (null === $this->_attachmentDataCollector) {
            $this->_attachmentDataCollector = Mage::getSingleton(
                'klarna_kco/checkout_attachment_collector',
                array('store' => $this->getObject()->getStore())
            );
        }

        return $this->_attachmentDataCollector;
    }

    /**
     * Collect order lines
     *
     * @return $this
     */
    public function collectOrderLines()
    {
        /** @var Klarna_Kco_Model_Checkout_Orderline_Abstract $model */
        foreach ($this->getOrderLinesCollector()->getCollectors() as $model) {
            $model->collect($this);
        }

        return $this;
    }

    /**
     * Collect attachment
     *
     * @return $this
     */
    public function collectAttachmentData()
    {
        foreach ($this->getAttachmentDataCollector()->getCollectors() as $model) {
            $model->collect($this);
        }

        return $this;
    }

    /**
     * Get order lines as array
     *
     * @param bool $orderItemsOnly
     *
     * @return array
     */
    public function getOrderLines($orderItemsOnly = false)
    {
        /** @var Klarna_Kco_Model_Checkout_Orderline_Abstract $model */
        foreach ($this->getOrderLinesCollector()->getCollectors() as $model) {
            if ($model->isIsTotalCollector() && $orderItemsOnly) {
                continue;
            }

            $model->fetch($this);
        }

        return $this->_orderLines;
    }

    /**
     * @return array|bool
     */
    public function getAttachmentData()
    {
        /** @var Klarna_Kco_Model_Checkout_Attachment_Abstract $model */
        foreach ($this->getAttachmentDataCollector()->getCollectors() as $model) {
            $model->fetch($this);
        }

        if (empty($this->_attachmentData)) {
            return false;
        } else {
            return array(
                'content_type' => 'application/vnd.klarna.internal.emd-v2+json',
                'body'         => json_encode($this->_attachmentData)
            );
        }
    }

    /**
     * Add an order line
     *
     * @param array $orderLine
     *
     * @return $this
     */
    public function addOrderLine(array $orderLine)
    {
        $this->_orderLines[] = $orderLine;

        return $this;
    }

    /**
     * Remove all order lines
     *
     * @return $this
     */
    public function resetOrderLines()
    {
        $this->_orderLines = array();

        return $this;
    }

    /**
     * Add attachment data
     *
     * @param array $attachmentData
     *
     * @return $this
     */
    public function addAttachmentData(array $attachmentData)
    {
        foreach ($attachmentData as $key=>$var) {
            $this->_attachmentData[$key][] = $var;
        }

        return $this;
    }

    /**
     * remove attachment data
     *
     * @return $this
     */
    public function resetAttachmentData()
    {
        $this->_attachmentData = array();
        return $this;
    }
}
