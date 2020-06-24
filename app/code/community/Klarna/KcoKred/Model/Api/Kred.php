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
 * @package    Klarna_KcoKred
 * @author     Jason Grim <jason.grim@klarna.com>
 */

require_once Mage::getBaseDir('lib') . '/Klarna/XMLrpc/Klarna.php';

/**
 * Api request to the Klarna Kred platform
 */
class Klarna_KcoKred_Model_Api_Kred extends Klarna_Kco_Model_Api_Abstract
{
    /**
     * @var Klarna_Checkout_Connector
     */
    protected $_connector = null;

    /**
     * @var Klarna_Checkout_Order
     */
    protected $_order = null;

    /**
     * @var string
     */
    protected $_builderType = 'klarna_kcokred/api_builder_kred';

    /**
     * @var string
     */
    protected $_builderTypeRecurring = 'klarna_kcokred/api_builder_recurring';

    /**
     * If a request is being made recursively, to prevent loops
     *
     * @var bool
     */
    protected $_isRecursiveCall = false;

    /**
     * @var Klarna
     */
    protected $_klarnaOrderManagement = null;

    /**
     * Items to skip in the capture line item collection
     *
     * @var array
     */
    protected $_captureLineItemSkip = array('shipping', 'discount');

    /**
     * Items to skip in the refund line item collection
     *
     * @var array
     */
    protected $_refundLineItemSkip = array('shipping', 'discount');

    /**
     * Name of log file for request
     *
     * @var string
     */
    const LOG_RAW_FILE = 'kco_kred_api.log';

    /**
     * Refund full invoice
     *
     * @var string
     */
    const REFUND_TYPE_INVOICE = 'invoice';

    /**
     * Partial refund by article
     *
     * @var string
     */
    const REFUND_TYPE_ARTICLE = 'article';

    /**
     * Create or update an order in the checkout API
     *
     * @param string     $checkoutId
     * @param bool|false $createIfNotExists
     * @param bool|false $updateItems
     *
     * @return Klarna_Kco_Model_Api_Response
     * @throws Klarna_Checkout_ApiErrorException
     * @throws Klarna_Kco_Model_Api_Exception
     */
    public function initKlarnaCheckout($checkoutId = null, $createIfNotExists = false, $updateItems = false)
    {
        $klarnaOrder = Mage::getModel('klarna_kco/api_response');
        $order       = $this->_getCheckoutOrder($checkoutId);
        $data        = array();

        try {
            if ($createIfNotExists || $updateItems) {
                if (!$checkoutId && $createIfNotExists) {
                    $data = $this->getGeneratedCreateRequest();

                    $createRequest = $order->create($data);
                    $this->_debug($createRequest);

                    $fetchRequest = $order->fetch();
                    $this->_debug($fetchRequest);
                } elseif ($updateItems) {
                    $data          = $this->getGeneratedUpdateRequest();
                    $updateRequest = $order->update($data);
                    $this->_debug($updateRequest);
                }
            } elseif ($checkoutId) {
                $fetchRequest = $this->_getCheckoutOrder()->fetch();
                $this->_debug($fetchRequest);
            }

            $klarnaOrder->setData($order->marshal());
            $klarnaOrder->setIsSuccessful(true);
        } catch (Klarna_Checkout_ApiErrorException $e) {
            if ($data) {
                $this->_debug('Failed init request: ' . "\n" . print_r($data, true));
            }

            $this->_debug($e, Zend_Log::ERR);
        }

        // If existing order fails or is expired, create a new one
        if (!$klarnaOrder->getIsSuccessful() && $createIfNotExists) {
            $data = array();
            try {
                $data = $this->getGeneratedCreateRequest();

                $createRequest = $order->create($data);
                $this->_debug($createRequest);

                $fetchRequest = $order->fetch();
                $this->_debug($fetchRequest);

                $klarnaOrder->setData($order->marshal());
                $klarnaOrder->setIsSuccessful(true);
            } catch (Klarna_Checkout_ApiErrorException $e) {
                if ($data) {
                    $this->_debug('Failed second attempt init request: ' . "\n" . print_r($data, true));
                }

                $this->_debug($e, Zend_Log::ERR);
                throw $e;
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
     * Get Klarna Checkout Reservation Id
     *
     * @return string
     */
    public function getReservationId()
    {
        return $this->getKlarnaOrder()->getReservation();
    }

    /**
     * Get the fraud status of an order to determine if it should be accepted or denied within Magento
     *
     * Kred does not support pending orders for fraud review, this simply checks if the order is available
     * in order management via the push queue
     *
     * Return value of 1 means accept
     * Return value of 0 means still pending
     * Return value of -1 means deny
     *
     * @param string $orderId
     *
     * @return int
     * @throws Exception
     */
    public function getFraudStatus($orderId)
    {
        $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->load($orderId, 'klarna_reservation_id');
        $pushQueue   = Mage::getModel('klarna_kcokred/pushqueue')->loadByCheckoutId($klarnaOrder->getKlarnaCheckoutId());

        return $pushQueue->getId() ? 1 : 0;
    }

    /**
     * Capture an amount on an order
     *
     * @param string                         $orderId
     * @param float                          $amount
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return Klarna_Kco_Model_Api_Response
     * @throws KlarnaException
     * @throws Klarna_Kco_Exception
     * @throws Mage_Core_Exception
     */
    public function capture($orderId, $amount, $invoice = null)
    {
        if (!$invoice instanceof Mage_Sales_Model_Order_Invoice) {
            Mage::throwException('Capture invoice must be an instance Mage_Sales_Model_Order_Invoice');
        }

        $response = Mage::getModel('klarna_kco/api_response');

        try {
            $orderItems = $this->_getGenerator()
                ->setObject($invoice)
                ->collectOrderLines()
                ->getOrderLines();

            Mage::dispatchEvent(
                'kco_kred_capture_items_before', array(
                'object'      => $invoice,
                'invoice'     => $invoice,
                'order_items' => $orderItems,
                'api'         => $this->_getKlarnaOrderManagement()
                )
            );

            $resultArray = $this->_captureDiscounts($invoice, $orderItems)
                ->_captureShipping($invoice, $orderItems)
                ->_captureItems($invoice, $orderItems)
                ->_getKlarnaOrderManagement()->activate($orderId, null, KlarnaFlags::RSRV_SEND_BY_EMAIL);

            Mage::dispatchEvent(
                'kco_kred_capture_items_after', array(
                'object'      => $invoice,
                'invoice'     => $invoice,
                'order_items' => $orderItems,
                'api'         => $this->_getKlarnaOrderManagement()
                )
            );
        } catch (Exception $e) {
            $this->_debug($this->_getKlarnaOrderManagement());
            throw new Mage_Core_Exception($e->getMessage(), $e->getCode(), $e);
        }

        $this->_debug($this->_getKlarnaOrderManagement());

        list($result, $reservation) = $resultArray;

        $captureResult = 'ok' == $result;

        $response->setIsSuccessful($captureResult);
        $response->setTransactionId($reservation);

        /**
         * If a capture fails, attempt to extend the auth and attempt capture again.
         * This work in certain cases that cannot be detected via api calls
         */
        if (!$response->getIsSuccessful() && !$this->_isRecursiveCall) {
            try {
                $this->_getKlarnaOrderManagement()->extendExpiryDate($orderId);

                $this->_isRecursiveCall = true;
                $response               = $this->capture($orderId, $amount);
                $this->_isRecursiveCall = false;

                $this->_debug($this->_getKlarnaOrderManagement());

                return $response;
            } catch (KlarnaException $e) {
            }
        }

        return $response;
    }

    /**
     * Handle capture of discounts
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array                          $orderItems
     *
     * @return $this
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _captureDiscounts($invoice, $orderItems = array())
    {
        if (0 >= abs($invoice->getBaseDiscountAmount())) {
            return $this;
        }

        /** @var Mage_Sales_Model_Order $order */
        $order = $invoice->getOrder();
        if (abs($invoice->getBaseDiscountAmount()) == abs($order->getBaseDiscountAmount())) {
            $this->_getKlarnaOrderManagement()->addArtNo(1, 'discount');
        } else {
            throw new Klarna_Kco_Model_Api_Exception('Cannot capture partial discount amount for invoice.');
        }

        return $this;
    }

    /**
     * Handle capture of shipping
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array                          $orderItems
     *
     * @return $this
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _captureShipping($invoice, $orderItems = array())
    {
        if (0 >= $invoice->getBaseShippingAmount()) {
            return $this;
        }

        $api = $this->_getKlarnaOrderManagement();
        if ($invoice->getBaseShippingAmount() == $invoice->getOrder()->getBaseShippingAmount()) {
            $api->addArtNo(1, 'shipping');
        } else {
            throw new Klarna_Kco_Model_Api_Exception('Cannot capture partial shipping amount for order.');
        }

        return $this;
    }

    /**
     * Handle capture of items
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param array                          $orderItems
     *
     * @return $this
     */
    protected function _captureItems($invoice, $orderItems = array())
    {
        $api = $this->_getKlarnaOrderManagement();

        foreach ($orderItems as $orderItem) {
            $skipObject = new Varien_Object(
                array(
                'skip_item' => in_array($orderItem['reference'], $this->_captureLineItemSkip)
                )
            );

            Mage::dispatchEvent(
                'kco_kred_capture_item_add_art_no', array(
                'object'      => $invoice,
                'invoice'     => $invoice,
                'order_item'  => $orderItem,
                'skip_object' => $skipObject
                )
            );

            if ($skipObject->getSkipItem()) {
                continue;
            }

            $api->addArtNo((int)$orderItem['quantity'], $orderItem['reference']);
        }

        return $this;
    }

    /**
     * Refund for an order
     *
     * @param string                            $orderId
     * @param float                             $amount
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return Klarna_Kco_Model_Api_Response
     * @throws Exception
     * @throws Klarna_Kco_Exception
     */
    public function refund($orderId, $amount, $creditMemo = null)
    {
        if (!$creditMemo instanceof Mage_Sales_Model_Order_Creditmemo) {
            Mage::throwException('Refund credit memo must be an instance Mage_Sales_Model_Order_Creditmemo');
        }

        $response  = Mage::getModel('klarna_kco/api_response');
        $invoiceId = $creditMemo->getInvoice()->getTransactionId();

        try {
            switch ($this->_getRefundType($creditMemo)) {
                case self::REFUND_TYPE_INVOICE:
                    $refundResponse = $this->_getKlarnaOrderManagement()->creditInvoice($invoiceId);
                    break;
                default:
                    $orderItems = $this->_getGenerator()
                        ->setObject($creditMemo)
                        ->collectOrderLines()
                        ->getOrderLines();

                    Mage::dispatchEvent(
                        'kco_kred_refund_items_before', array(
                        'object'      => $creditMemo,
                        'credit_memo' => $creditMemo,
                        'order_items' => $orderItems,
                        'api'         => $this->_getKlarnaOrderManagement()
                        )
                    );

                    $refundResponse = $this->_refundShipping($creditMemo, $orderItems)
                        ->_refundAdjustmentNegative($creditMemo)
                        ->_refundAdjustmentPositive($creditMemo)
                        ->_refundItems($creditMemo, $orderItems)
                        ->_refundDiscount($creditMemo, $orderItems)
                        ->_getKlarnaOrderManagement()->creditPart($invoiceId);

                    Mage::dispatchEvent(
                        'kco_kred_refund_items_after', array(
                        'object'      => $creditMemo,
                        'invoice'     => $creditMemo,
                        'order_items' => $orderItems
                        )
                    );
            }
        } catch (Exception $e) {
            $this->_debug($this->_getKlarnaOrderManagement());
            throw $e;
        }

        $this->_debug($this->_getKlarnaOrderManagement());

        $refundResult = $invoiceId == $refundResponse;

        $response->setIsSuccessful((bool)$refundResult);

        return $response;
    }

    /**
     * Get the optimal refund type to perform
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return string
     */
    protected function _getRefundType($creditMemo)
    {
        if (0 >= ($creditMemo->getInvoice()->getBaseGrandTotal() - $creditMemo->getBaseGrandTotal())) {
            return self::REFUND_TYPE_INVOICE;
        }

        return self::REFUND_TYPE_ARTICLE;
    }

    /**
     * Add new article for positive refund adjustment
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return $this
     */
    protected function _refundAdjustmentPositive(Mage_Sales_Model_Order_Creditmemo $creditMemo)
    {
        if (0 >= $creditMemo->getAdjustmentPositive()
            || $creditMemo->getAdjustmentPositive() == $creditMemo->getAdjustmentNegative()
        ) {
            return $this;
        }

        $this->_getKlarnaOrderManagement()->addArticle(
            1,
            'adj-pos-' . $creditMemo->getInvoice()->getIncrementId() . '-' . $this->_getRandomString(),
            $this->getCheckoutHelper()->__('Refund Adjustment Positive'),
            -$creditMemo->getAdjustmentPositive(),
            0
        );

        return $this;
    }

    /**
     * Add new article for negative refund adjustment
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return $this
     */
    protected function _refundAdjustmentNegative(Mage_Sales_Model_Order_Creditmemo $creditMemo)
    {
        if (0 >= $creditMemo->getAdjustmentNegative()
            || $creditMemo->getAdjustmentPositive() == $creditMemo->getAdjustmentNegative()
        ) {
            return $this;
        }

        $this->_getKlarnaOrderManagement()->addArticle(
            1,
            'adj-neg-' . $creditMemo->getInvoice()->getIncrementId() . '-' . $this->_getRandomString(),
            $this->getCheckoutHelper()->__('Refund Adjustment Negative'),
            $creditMemo->getAdjustmentNegative(),
            0
        );

        return $this;
    }

    /**
     * Handle refunds of discounts
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param array                             $orderItems
     *
     * @return $this
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _refundDiscount(Mage_Sales_Model_Order_Creditmemo $creditmemo, $orderItems = array())
    {
        if (0 <= $creditmemo->getBaseDiscountAmount()) {
            return $this;
        }

        if ($creditmemo->getBaseDiscountAmount() != $creditmemo->getOrder()->getBaseDiscountAmount()) {
            throw new Klarna_Kco_Model_Api_Exception('Cannot refund partial discount amount for order.');
        }

        $this->_getKlarnaOrderManagement()->addArtNo(1, 'discount');

        return $this;
    }

    /**
     * Refund an amount on shipping
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     * @param array                             $orderItems
     *
     * @return $this
     */
    protected function _refundShipping(Mage_Sales_Model_Order_Creditmemo $creditMemo, $orderItems = array())
    {
        if (0 >= $creditMemo->getBaseShippingAmount()) {
            return $this;
        }

        $taxRate = 0;

        foreach ($orderItems as $orderItem) {
            if (isset($orderItem['type'])
                && $orderItem['type'] == Klarna_Kco_Model_Checkout_Orderline_Shipping::ITEM_TYPE_SHIPPING
            ) {
                $taxRate = $orderItem['tax_rate'] / 100;
                break;
            }
        }

        if ($creditMemo->getBaseShippingAmount() == $creditMemo->getOrder()->getBaseShippingAmount()) {
            $this->_getKlarnaOrderManagement()
                ->addArtNo(1, 'shipping');
        } else {
            $this->_getKlarnaOrderManagement()->addArticle(
                1,
                'shipping-refund-' . $this->_getRandomString(),
                $this->getCheckoutHelper()->__('Refund Shipping'),
                -$creditMemo->getBaseShippingAmount(),
                $taxRate,
                0,
                8
            );
        }

        return $this;
    }

    /**
     * Handel refund of items
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @param array                             $orderItems
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     */
    protected function _refundItems(Mage_Sales_Model_Order_Creditmemo $creditMemo, $orderItems = array())
    {
        $api = $this->_getKlarnaOrderManagement();

        foreach ($orderItems as $orderItem) {
            $skipObject = new Varien_Object(
                array(
                'skip_item' => in_array($orderItem['reference'], $this->_refundLineItemSkip)
                )
            );

            Mage::dispatchEvent(
                'kco_kred_refund_item_add_art_no', array(
                'object'      => $creditMemo,
                'credit_memo' => $creditMemo,
                'order_item'  => $orderItem,
                'skip_object' => $skipObject
                )
            );

            if ($skipObject->getSkipItem()) {
                continue;
            }

            $api->addArtNo($orderItem['quantity'], $orderItem['reference']);
        }

        return $this;
    }

    /**
     * Get the increment id for items on reservation in Klarna api
     *
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return int
     */
    protected function _getInvoiceKlarnaIncrementId($invoice)
    {
        /** @var Mage_Sales_Model_Resource_Order_Invoice_Collection $invoices */
        $invoices = $invoice->getOrder()->getInvoiceCollection();

        foreach ($invoices as $key => $_invoice) {
            if ($_invoice->getIncrementId() == $invoice->getIncrementId()) {
                return (int)$key;
            }
        }

        return 0;
    }

    /**
     * Cancel an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     * @throws Exception
     */
    public function cancel($orderId)
    {
        $response = Mage::getModel('klarna_kco/api_response');

        try {
            $apiResponse = $this->_getKlarnaOrderManagement()->cancelReservation($orderId);
            $response->setIsSuccessful($apiResponse);
        } catch (Exception $e) {
            $this->_debug($this->_getKlarnaOrderManagement());
            throw $e;
        }

        $this->_debug($this->_getKlarnaOrderManagement());

        return $response;
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
        return $this->cancel($orderId);
    }

    /**
     * Get the html snippet for an order
     *
     * @return string
     */
    public function getKlarnaCheckoutGui()
    {
        return $this->getKlarnaOrder()->getData('gui/snippet');
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
        $response      = Mage::getModel('klarna_kco/api_response');
        $updateRequest = $this->_getCheckoutOrder($orderId)->update(
            array(
            'status' => 'created'
            )
        );

        $this->_debug($updateRequest);
        $response->setIsSuccessful(('200' == $updateRequest->getStatus()));

        return $response;
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
        $response          = Mage::getModel('klarna_kco/api_response');
        $merchantReference = array(
            'orderid1' => $reference1
        );

        if (null !== $reference2) {
            $merchantReference['orderid2'] = $reference2;
        }

        $updateRequest = $this->_getCheckoutOrder($orderId)->update(
            array(
            'merchant_reference' => $merchantReference
            )
        );

        $this->_debug($updateRequest);
        $response->setIsSuccessful(('200' == $updateRequest->getStatus()));

        return $response;
    }

    /**
     * Get the checkout connection
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return Klarna_Checkout_Connector
     */
    protected function _getCheckoutConnector($store = null)
    {
        if (null === $this->_connector) {
            $helper = $this->getCheckoutHelper();
            $url    = $helper->getPaymentConfig('test_mode', $store) ? $this->getConfig()->getTestdriveUrl()
                : $this->getConfig()->getProductionUrl();

            $this->_connector = Klarna_Checkout_Connector::create($helper->getPaymentConfig('shared_secret', $store), $url);
        }

        return $this->_connector;
    }

    /**
     * Get Klarna checkout order
     *
     * @param string                $checkoutId
     * @param Mage_Core_Model_Store $store
     *
     * @return Klarna_Checkout_Order
     */
    protected function _getCheckoutOrder($checkoutId = null, $store = null)
    {
        if (null === $this->_order) {
            $this->_order = new Klarna_Checkout_Order($this->_getCheckoutConnector($store), $checkoutId);
        }

        return $this->_order;
    }

    /**
     * Get Klarna order management
     *
     * @return Klarna
     * @throws KlarnaException
     */
    protected function _getKlarnaOrderManagement()
    {
        if (null === $this->_klarnaOrderManagement) {
            $helper                       = $this->getCheckoutHelper();
            $locale                       = Mage::getStoreConfig('general/locale/code', $this->getStore());
            $this->_klarnaOrderManagement = new Klarna();
            $this->_klarnaOrderManagement->config(
                $helper->getPaymentConfig('merchant_id', $this->getStore()),
                $helper->getPaymentConfig('shared_secret', $this->getStore()),
                $helper->getDefaultCountry($this->getStore()),
                strtok($locale, '_'),
                $this->getQuote()->getBaseCurrencyCode(),
                $helper->getPaymentConfig('test_mode', $this->getStore()) ? Klarna::BETA : Klarna::LIVE,
                'json', 'pclasses.json', true, Mage::getStoreConfigFlag('payment/klarna_kco/debug', $this->getStore())
            );
        }

        return $this->_klarnaOrderManagement;
    }

    /**
     * Generate a random 4 letter string
     *
     * @return string
     */
    protected function _getRandomString()
    {
        return substr(hash('sha256', rand()), 0, 4);
    }

    /**
     * Log debug messages
     *
     * @param $message
     * @param $level
     */
    protected function _debug($message, $level = Zend_Log::DEBUG)
    {
        if (Zend_Log::DEBUG != $level || Mage::getStoreConfigFlag('payment/klarna_kco/debug', $this->getStore())) {
            Mage::log($this->_rawDebugMessage($message), $level, self::LOG_RAW_FILE, true);
        }
    }

    /**
     * Raw debug message for logging
     *
     * @param $mixed
     *
     * @return string
     */
    protected function _rawDebugMessage($mixed)
    {
        $message = '';
        if ($mixed instanceof Klarna_Checkout_ApiErrorException) {
            if ($payload = $mixed->getPayload()) {
                $newException = new Klarna_Checkout_ApiErrorException(
                    $payload['internal_message'],
                    $payload['http_status_code'], $payload, $mixed
                );
                $message      = $newException->__toString();
            }
        } elseif ($mixed instanceof Klarna) {
            foreach ($mixed::getDebug() as $debug) {
                if (!empty($debug['mixed'])) {
                    $message .= $debug['msg'] . ':' . print_r($debug['mixed'], true) . "\n";
                } else {
                    $message .= $debug['msg'] . "\n";
                }
            }
        } elseif ($mixed instanceof Klarna_Checkout_HTTP_Response) {
            // build request
            $request = $mixed->getRequest();

            $message = 'Request:' . "\n";
            $message .= $request->getMethod() . ' ' . $request->getURL() . "\n";
            foreach ($request->getHeaders() as $header => $headerValue) {
                $message .= $header . ': ' . $headerValue . "\n";
            }

            $data = $request->getData();
            if (!empty($data)) {
                $json = json_decode($data);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $data = json_encode($json, JSON_PRETTY_PRINT);
                }

                $message .= "\n" . $data . "\n\n";
            }

            // build response
            $message .= "Response ({$mixed->getStatus()}):\n";
            foreach ($mixed->getHeaders() as $header => $headerValue) {
                $message .= $header . ': ' . $headerValue . "\n";
            }

            $data = $mixed->getData();
            if (!empty($data)) {
                $json = json_decode($data);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $data = json_encode($json, JSON_PRETTY_PRINT);
                }

                $message .= "\n" . $data . "\n\n";
            }
        } elseif (is_array($mixed)) {
            $message = print_r($mixed, true);
        } elseif ($mixed instanceof Exception) {
            $message = (string)$mixed;
        } elseif (!is_string($mixed)) {
            $message = 'Invalid message type. Unable to log.';
        } else {
            $message = (string)$mixed;
        }

        return (string)$message;
    }


    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    public function createRecurringOrder(Mage_Sales_Model_Recurring_Profile $profile, Mage_Sales_Model_Order $order)
    {
        $result = array('success' => false);
        $store = Mage::getModel('core/store')->load($profile->getStoreId());
        //get profile recurring token
        $kcoKredHelper = Mage::helper('klarna_kcokred');
        $kcoRecurringToken = $kcoKredHelper->getProfileRecurringToken($profile);
        //create klarna recurring order with token
        $recurringOrder = new Klarna_Checkout_RecurringOrder($this->_getCheckoutConnector($store), $kcoRecurringToken);
        $apiBuilder = Mage::getModel($this->_builderTypeRecurring);
        $apiBuilder->setRecurringProfile($profile);
        $apiBuilder->setMerchantOrder($order);
        $createRequest = $apiBuilder->generateRequest('create');

        try {
            $recurringOrder->create($createRequest);
            $result = array('success' => true, 'reservation_id' => $recurringOrder['reservation']);
        } catch (Klarna_Checkout_ApiErrorException $e) {
            $result = array('success' => false);
        }

        return $result;
    }

    /**
     * refund by invoice id
     *
     * @param $invoiceId
     *
     * @return bool
     *
     * @throws KlarnaException
     */
    public function refundByKlarnaInvoiceId($invoiceId)
    {
        $refundResponse = $this->_getKlarnaOrderManagement()->creditInvoice($invoiceId);
        $refundResult = $invoiceId == $refundResponse;
        return $refundResult;
    }
}
