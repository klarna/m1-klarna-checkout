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
 * Klarna api integration abstract
 *
 * @method Klarna_Kco_Model_Api_Abstract setStore(Mage_Core_Model_Store $store)
 * @method Mage_Core_Model_Store getStore()
 * @method Klarna_Kco_Model_Api_Abstract setConfig(Varien_Object $config)
 * @method Varien_Object getConfig()
 * @method Klarna_Kco_Model_Api_Abstract setQuote(Mage_Sales_Model_Quote $quote)
 * @method Klarna_Kco_Model_Api_Abstract setIsAdmin(bool $bool)
 * @method bool getIsAdmin()
 */
class Klarna_Kco_Model_Api_Abstract extends Varien_Object implements Klarna_Kco_Model_Api_ApiInterface
{
    /**
     * @var Varien_Object
     */
    protected $_klarnaOrder = null;

    /**
     * API type code
     *
     * @var string
     */
    protected $_builderType = '';

    /**
     * Create or update an order in the checkout API
     *
     * @param string     $checkoutId
     * @param bool|false $createIfNotExists
     * @param bool|false $updateItems
     *
     * @return Varien_Object
     */
    public function initKlarnaCheckout($checkoutId = null, $createIfNotExists = false, $updateItems = false)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Get Klarna Checkout Reservation Id
     *
     * @return string
     */
    public function getReservationId()
    {
        return $this->getKlarnaOrder()->getId();
    }

    /**
     * Get generated create request
     *
     * @return array
     * @throws Klarna_Kco_Exception
     */
    public function getGeneratedCreateRequest()
    {
        return $this->_getGenerator()
            ->setIsAdmin($this->getIsAdmin())
            ->setObject($this->getQuote())
            ->generateRequest(Klarna_Kco_Model_Api_Builder_Abstract::GENERATE_TYPE_CREATE)
            ->getRequest();
    }

    /**
     * Get generated update request
     *
     * @return array
     * @throws Klarna_Kco_Exception
     */
    public function getGeneratedUpdateRequest()
    {
        return $this->_getGenerator()
            ->setIsAdmin($this->getIsAdmin())
            ->setObject($this->getQuote())
            ->generateRequest(Klarna_Kco_Model_Api_Builder_Abstract::GENERATE_TYPE_UPDATE)
            ->getRequest();
    }

    /**
     * Get request generator
     *
     * @return Klarna_Kco_Model_Api_Builder_Abstract
     * @throws Klarna_Kco_Exception
     */
    protected function _getGenerator()
    {
        $generator = Mage::getModel($this->_builderType);

        if (!$generator) {
            throw new Klarna_Kco_Exception('Invalid api generator type code.');
        }

        return $generator;
    }

    /**
     * Get the fraud status of an order to determine if it should be accepted or denied within Magento
     *
     * Return value of 1 means accept
     * Return value of 0 means still pending
     * Return value of -1 means deny
     *
     * @param string $orderId
     *
     * @return int
     */
    public function getFraudStatus($orderId)
    {
        return 1;
    }

    /**
     * Acknowledge an order in order management
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function acknowledgeOrder($orderId)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Update merchant references for a Klarna order
     *
     * @param string $orderId
     * @param string $reference1
     * @param string $reference2
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function updateMerchantReferences($orderId, $reference1, $reference2 = null)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Capture an amount on an order
     *
     * @param string                         $orderId
     * @param float                          $amount
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function capture($orderId, $amount, $invoice = null)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Refund for an order
     *
     * @param string                            $orderId
     * @param float                             $amount
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function refund($orderId, $amount, $creditMemo = null)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Cancel an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function cancel($orderId)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Release the authorization for an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function release($orderId)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Set Klarna checkout order details
     *
     * @param Varien_Object $klarnaOrder
     *
     * @return $this
     */
    public function setKlarnaOrder(Varien_Object $klarnaOrder)
    {
        $this->_klarnaOrder = $klarnaOrder;

        return $this;
    }

    /**
     * Get Klarna checkout order details
     *
     * @return Varien_Object
     */
    public function getKlarnaOrder()
    {
        if (null === $this->_klarnaOrder) {
            $this->_klarnaOrder = new Varien_Object();
        }

        return $this->_klarnaOrder;
    }

    /**
     * Get order details for a completed Klarna order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function getPlacedKlarnaOrder($orderId)
    {
        return new Klarna_Kco_Model_Api_Response();
    }

    /**
     * Get Klarna checkout html snippets
     *
     * @return string
     */
    public function getKlarnaCheckoutGui()
    {
        return '';
    }

    /**
     * Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->hasData('quote')) {
            return $this->getData('quote');
        }

        return $this->_getQuote();
    }

    /**
     * Get current active quote instance
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->getKco()->getQuote();
    }

    /**
     * Get Klarna checkout helper
     *
     * @return Klarna_Kco_Helper_Checkout
     */
    public function getCheckoutHelper()
    {
        return Mage::helper('klarna_kco/checkout');
    }

    /**
     * Get one page checkout model
     *
     * @return Klarna_Kco_Model_Checkout_Type_Kco
     */
    public function getKco()
    {
        return Mage::getSingleton('klarna_kco/checkout_type_kco');
    }
}
