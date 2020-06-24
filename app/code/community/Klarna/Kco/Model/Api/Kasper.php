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
 * Api request to the Klarna Kasper platform
 */
class Klarna_Kco_Model_Api_Kasper extends Klarna_Kco_Model_Api_Abstract
{
    /**
     * @var string
     */
    protected $_builderType = 'klarna_kco/api_builder_kasper';

    /**
     * Order statuses
     */
    const ORDER_STATUS_AUTHORIZED    = 'AUTHORIZED';
    const ORDER_STATUS_PART_CAPTURED = 'PART_CAPTURED';
    const ORDER_STATUS_CAPTURED      = 'CAPTURED';
    const ORDER_STATUS_CANCELLED     = 'CANCELLED';
    const ORDER_STATUS_EXPIRED       = 'EXPIRED';
    const ORDER_STATUS_CLOSED        = 'CLOSED';

    /**
     * Order fraud statuses
     */
    const ORDER_FRAUD_STATUS_ACCEPTED = 'ACCEPTED';
    const ORDER_FRAUD_STATUS_REJECTED = 'REJECTED';
    const ORDER_FRAUD_STATUS_PENDING  = 'PENDING';

    /**
     * Order notification statuses
     */
    const ORDER_NOTIFICATION_FRAUD_REJECTED = 'FRAUD_RISK_REJECTED';
    const ORDER_NOTIFICATION_FRAUD_ACCEPTED = 'FRAUD_RISK_ACCEPTED';
    const ORDER_NOTIFICATION_FRAUD_STOPPED  = 'FRAUD_RISK_STOPPED';

    /**
     * API allowed shipping method code
     */
    const KLARNA_API_SHIPPING_METHOD_HOME = "Home";
    const KLARNA_API_SHIPPING_METHOD_PICKUPSTORE = "PickUpStore";
    const KLARNA_API_SHIPPING_METHOD_BOXREG = "BoxReg";
    const KLARNA_API_SHIPPING_METHOD_BOXUNREG = "BoxUnreg";
    const KLARNA_API_SHIPPING_METHOD_PICKUPPOINT = "PickUpPoint";
    const KLARNA_API_SHIPPING_METHOD_OWN = "Own";

    /**
     * If a request is being made recursively, to prevent loops
     *
     * @var bool
     */
    protected $_isRecursiveCall = false;

    /**
     * Create or update an order in the checkout API
     *
     * @param string     $checkoutId
     * @param bool|false $createIfNotExists
     * @param bool|false $updateItems
     *
     * @return Klarna_Kco_Model_Api_Response
     * @throws Klarna_Kco_Model_Api_Exception
     */
    public function initKlarnaCheckout($checkoutId = null, $createIfNotExists = false, $updateItems = false)
    {
        $api         = $this->_getCheckoutApi();
        $klarnaOrder = new Varien_Object();

        if ($createIfNotExists || $updateItems) {
            $data = $this->getGeneratedCreateRequest();

            if (!$checkoutId && $createIfNotExists) {
                $klarnaOrder = $api->createOrder($data);
            } elseif ($updateItems) {
                $klarnaOrder = $api->updateOrder($checkoutId, $data);
            }

            $this->_extractAndSaveShippingGateway($klarnaOrder);
        } elseif ($checkoutId) {
            $klarnaOrder = $api->getOrder($checkoutId);
        }

        // If existing order fails or is expired, create a new one
        if (!$klarnaOrder->getIsSuccessful()
            && ('READ_ONLY_ORDER' == $klarnaOrder->getErrorCode()
                || !$klarnaOrder->getErrorCode())
        ) {
            if ($createIfNotExists) {
                $data        = $this->getGeneratedCreateRequest();
                $klarnaOrder = $api->createOrder($data);
            }
        }

        // If we still get an error, give up
        if (!$klarnaOrder->getIsSuccessful()) {
            throw new Klarna_Kco_Model_Api_Exception(
                $this->getCheckoutHelper()
                ->__('Unable to initialize Klarna checkout order')
            );
        }

        $this->setKlarnaOrder($klarnaOrder);

        return $klarnaOrder;
    }

    /**
     * Extract and save the shipping method gateway information
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Response $klarnaOrder
     * @throws Exception
     */
    protected function _extractAndSaveShippingGateway(Klarna_Kco_Model_Api_Rest_Client_Response $klarnaOrder)
    {
        if (!$klarnaOrder->getIsSuccessful()) {
            return;
        }

        $shipping = $this->_getShippingMethodGateway($klarnaOrder);

        if ($klarnaOrder->getSelectedShippingOption() === null ||
            !isset($klarnaOrder->getSelectedShippingOption()['shipping_method']) ||
            $klarnaOrder->getSelectedShippingOption()['shipping_method'] === null
        ) {
            if ($shipping->getId() !== null) {
                $shipping->setIsActive(false);
                $shipping->save();
            }

            return;
        }

        $shipping = Mage::helper('klarna_kco/checkout')
            ->updateShippingGatewayMetrics($shipping, $klarnaOrder->getSelectedShippingOption());
        $shipping->setIsActive(true);
        $shipping->save();
    }

    /**
     * Getting back the shipping method gateway model
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Response $klarnaOrder
     * @return Klarna_Kco_Model_Klarnashippingmethodgateway
     */
    protected function _getShippingMethodGateway(Klarna_Kco_Model_Api_Rest_Client_Response $klarnaOrder)
    {
        /** @var Klarna_Kco_Model_Klarnashippingmethodgateway $shipping */
        $shipping = Mage::getModel('klarna_kco/klarnashippingmethodgateway')
            ->loadByKlarnaCheckoutId($klarnaOrder->getOrderId());

        if ($shipping->getId() === null) {
            $shipping->setKlarnaCheckoutId($klarnaOrder->getOrderId());
        }

        return $shipping;
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
        $klarnaOrder = $this->_getOrderManagementApi()->getOrder($orderId);
        switch ($klarnaOrder->getFraudStatus()) {
            case self::ORDER_FRAUD_STATUS_ACCEPTED:
                return 1;
            case self::ORDER_FRAUD_STATUS_REJECTED:
                return -1;
            case self::ORDER_FRAUD_STATUS_PENDING:
            default:
                return 0;
        }
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
        return $this->_getOrderManagementApi()->acknowledgeOrder($orderId);
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
        return $this->_getOrderManagementApi()->updateMerchantReferences($orderId, $reference1, $reference2);
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
        $data['captured_amount'] = Mage::helper('klarna_kco/checkout')->toApiFloat($amount);

        /**
         * Get items for capture
         */
        if ($invoice instanceof Mage_Sales_Model_Order_Invoice) {
            $orderItems = $this->_getGenerator()
                ->setObject($invoice)
                ->collectOrderLines()
                ->getOrderLines();

            if ($orderItems) {
                $data['order_lines'] = $orderItems;
            }
        }

        /**
         * Set shipping delay for capture
         *
         * Change this setting when items will not be shipped for x amount of days after capture.
         *
         * For instance, you capture on Friday but won't ship until Monday. A 3 day shipping delay would be set.
         */
        $shippingDelayObject = new Varien_Object(
            array(
            'shipping_delay' => 0
            )
        );

        Mage::dispatchEvent(
            'kco_capture_shipping_delay', array(
            'shipping_delay_object' => $shippingDelayObject
            )
        );

        if ($shippingDelayObject->getShippingDelay()) {
            $data['shipping_delay'] = $shippingDelayObject->getShippingDelay();
        }

        $response = $this->_getOrderManagementApi()->captureOrder($orderId, $data);

        /**
         * If a capture fails, attempt to extend the auth and attempt capture again.
         * This work in certain cases that cannot be detected via api calls
         */
        if (!$response->getIsSuccessful() && !$this->_isRecursiveCall) {
            $extendResponse = $this->_getOrderManagementApi()->extendAuthorization($orderId);

            if ($extendResponse->getIsSuccessful()) {
                $this->_isRecursiveCall = true;
                $response               = $this->capture($orderId, $amount);
                $this->_isRecursiveCall = false;

                return $response;
            }
        }

        if ($response->getIsSuccessful()) {
            $captureId = $this->_getOrderManagementApi()
                ->getLocationResourceId($response->getResponseObject()->getHeader('Location'));

            if ($captureId) {
                $captureDetails = $this->_getOrderManagementApi()->getCapture($orderId, $captureId);

                if ($captureDetails->getKlarnaReference()) {
                    $captureDetails->setTransactionId($captureDetails->getKlarnaReference());

                    return $captureDetails;
                }
            }
        }

        return $response;
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
        $data['refunded_amount'] = Mage::helper('klarna_kco/checkout')->toApiFloat($amount);

        /**
         * Get items for refund
         */
        if ($creditMemo instanceof Mage_Sales_Model_Order_Creditmemo) {
            $orderItems = $this->_getGenerator()
                ->setObject($creditMemo)
                ->collectOrderLines()
                ->getOrderLines();

            if ($orderItems) {
                $data['order_lines'] = $orderItems;
            }
        }

        $response = $this->_getOrderManagementApi()->refund($orderId, $data);

        $response->setTransactionId($this->_getOrderManagementApi()->getLocationResourceId($response));

        return $response;
    }

    /**
     * @param $orderId
     * @param $captureId
     * @param $shippingInfo
     * @return array|DataObject
     */
    public function addShippingInfo($orderId, $captureId, $shippingInfo)
    {
        $data = $this->prepareShippingInfo($shippingInfo);
        $response =  $this->_getOrderManagementApi()->addShippingDetailsToCapture($orderId, $captureId, $data);
        return $response;
    }

    /**
     * Prepare shipping info request,For merchant who implement this feature
     * overwrite this function to add additional information
     *
     * @param array $shippingInfo
     */
    public function prepareShippingInfo(array $shippingInfo)
    {
        $data = array();
        foreach ($shippingInfo as $shipping) {
            $data['shipping_info'][] = array(
                'tracking_number' => $shipping['number'],
                'shipping_method' => $this->getKlarnaShippingMethod($shipping),
                'shipping_company' => $shipping['title']
            );
        }

        return $data;
    }

    /**
     * Get Api Accepted shipping method,For merchant who implement this feature
     * overwrite this function to return correct method code
     * Allowed values matches (PickUpStore|Home|BoxReg|BoxUnreg|PickUpPoint|Own)
     *
     * @param array $shipping
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getKlarnaShippingMethod(array $shipping)
    {
        return self::KLARNA_API_SHIPPING_METHOD_HOME;
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
        return $this->_getOrderManagementApi()->cancelOrder($orderId);
    }

    /**
     * Release the authorization on an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function release($orderId)
    {
        return $this->_getOrderManagementApi()->releaseAuthorization($orderId);
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
        return $this->_getOrderManagementApi()->getOrder($orderId);
    }

    /**
     * Get the html snippet for an order
     *
     * @return string
     */
    public function getKlarnaCheckoutGui()
    {
        return $this->getKlarnaOrder()->getHtmlSnippet();
    }

    /**
     * Get the api for checkout api
     *
     * @return Klarna_Kco_Model_Api_Rest_Checkout
     */
    protected function _getCheckoutApi()
    {
        return Mage::getSingleton('klarna_kco/api_rest_checkout')
            ->setConfig($this->getConfig())
            ->setStore($this->getStore());
    }

    /**
     * Get the api for order management
     *
     * @return Klarna_Kco_Model_Api_Rest_Ordermanagement
     */
    protected function _getOrderManagementApi()
    {
        return Mage::getSingleton('klarna_kco/api_rest_ordermanagement')
            ->setConfig($this->getConfig())
            ->setStore($this->getStore());
    }
}
