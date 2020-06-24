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
 * API callback controller for Klarna
 */
class Klarna_Kco_ApiController extends Klarna_Kco_Controller_Klarna
{
    /**
     * API call to update address details on a customers quote via callback from Klarna
     */
    public function addressUpdateAction()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        try {
            $klarnaOrderId = $this->getRequest()->get('id');
            $quote         = Mage::helper('klarna_kco/checkout')->loadQuoteByCheckoutId($klarnaOrderId);

            if (!$quote->getId()) {
                $this->getResponse()->setHeader('Content-type', 'application/json');
                $this->getResponse()->setHttpResponseCode(301);
                $failUrl = Mage::getUrl(
                    'checkout/klarna/validateFailed', array(
                        '_nosid'  => true,
                        '_escape' => false
                    )
                );
                $this->getResponse()->setHeader('Location', $failUrl);

                return;
            }

            $this->getKco()->setQuote($quote);

            $body = $this->getRequest()->getRawBody();

            try {
                $checkout = Mage::helper('core')->jsonDecode($body);
                $checkout = new Varien_Object($checkout);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_sendBadRequestResponse();

                return;
            }

            // When having virtual products there are no shipping method information
            if (is_array($checkout->getSelectedShippingOption())) {
                $this->_updateShippingGateway($checkout->getSelectedShippingOption(), $quote);
            }

            $this->_updateOrderAddresses($checkout);
            try {
                $this->getKco()->setQuote($quote);
                $response = $this->getKco()->getApiInstance()->getGeneratedUpdateRequest();
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_sendBadRequestResponse('Unknown error');

                return;
            }

            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        } catch (Klarna_Kco_Exception $e) {
            // Do not modify header or log exception
            Mage::logException($e);
        } catch (Klarna_Kco_Model_Api_Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHeader('HTTP/1.1', '503 Service Unavailable')->sendResponse();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    /**
     * Updating the Klarna shipping method gateway information
     *
     * @param array $selectedShipping
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function _updateShippingGateway(array $selectedShipping, $quote)
    {
        if ($this->getKco()->hasActiveKlarnaShippingGateway()) {
            $shippingGateway = $this->getKco()->getKlarnaShippingGateway();
            $shippingGateway = Mage::helper('klarna_kco/checkout')
                ->updateShippingGatewayMetrics($shippingGateway, $selectedShipping);

            $shippingGateway->save();
            $quote->collectTotals();
        }
    }

    /**
     *  API call to set shipping method on a customers quote via callback from Klarna
     */
    public function shippingMethodUpdateAction()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        try {
            $klarnaOrderId = $this->getRequest()->get('id');
            $quote         = Mage::helper('klarna_kco/checkout')->loadQuoteByCheckoutId($klarnaOrderId);

            if (!$quote->getId()) {
                $this->getResponse()->setHeader('Content-type', 'application/json');
                $this->getResponse()->setHttpResponseCode(404);
                $this->getResponse()->setBody(
                    Mage::helper('core')->jsonEncode(
                        array(
                            'error' => 'Order not found'
                        )
                    )
                );

                return;
            }

            $this->getKco()->setQuote($quote);

            $body = $this->getRequest()->getRawBody();

            try {
                $checkout = Mage::helper('core')->jsonDecode($body);
                $checkout = new Varien_Object($checkout);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_sendBadRequestResponse();

                return;
            }

            if (($selectedOption = $checkout->getSelectedShippingOption())) {
                try {
                    $this->_handleInitialKssActivation($selectedOption, $klarnaOrderId);
                    $this->_handleKssFallback($selectedOption, $klarnaOrderId);

                    $shippingMethod = $this->_getShippingMethod($selectedOption, $quote);
                    $this->getKco()->saveShippingMethod($shippingMethod);

                    if ($this->getKco()->hasActiveKlarnaShippingGateway()) {
                        $this->getKco()->getQuote()->setTotalsCollectedFlag(false);
                        $this->getKco()->checkShippingMethod();

                        // This line needs to be called because in specific cases the KSS method is now registered
                        $this->getKco()->getQuote()->getShippingAddress()->collectShippingRates();
                    }

                    $this->getKco()->getQuote()->collectTotals()->save();
                } catch (Exception $e) {
                    $this->getKco()->checkShippingMethod();

                    if ($this->getKco()->hasActiveKlarnaShippingGateway()) {
                        $this->getKco()->clearShippingGateway();
                        $this->getKco()->getQuote()->setTotalsCollectedFlag(false);
                        $this->getKco()->checkShippingMethod();
                        $this->getKco()->getQuote()->getShippingAddress()->collectShippingRates();

                        $shippingMethod = $this->_getShippingMethod($selectedOption, $quote);
                        $this->getKco()->saveShippingMethod($shippingMethod);
                    }

                    $this->getKco()->getQuote()->collectTotals()->save();
                }
            }

            try {
                $this->getKco()->setQuote($quote);
                $response = $this->getKco()->getApiInstance()->getGeneratedUpdateRequest();
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_sendBadRequestResponse('Unknown error');

                return;
            }

            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        } catch (Klarna_Kco_Model_Api_Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHeader('HTTP/1.1', '503 Service Unavailable')->sendResponse();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    /**
     * Without these lines we risk a never ending loading issue on the checkout page.
     * For example when the iframe is loaded after the customer added the billing address in some cases
     * no gateway information are stored to the database.
     * In this action we get the default selected KSS shipping method.
     * When we do not register it to the database then we will still return the previous method
     * (not KSS method).
     * In such a case a never ending loading happens until the customer refreshes the page.
     *
     * @param array $selectedOption
     * @param string $klarnaOrderId
     */
    private function _handleInitialKssActivation(array $selectedOption, $klarnaOrderId)
    {
        if (!Mage::helper('klarna_kco/checkout')->isShopShippingMethod($selectedOption['id']) &&
            !$this->getKco()->hasActiveKlarnaShippingGateway()
        ) {
            $shipping = Mage::getModel('klarna_kco/klarnashippingmethodgateway')
                ->loadByKlarnaCheckoutId($klarnaOrderId);

            $shipping = Mage::helper('klarna_kco/checkout')
                ->updateShippingGatewayMetrics($shipping, $selectedOption);
            $shipping->setIsActive(true);
            $shipping->setKlarnaCheckoutId($klarnaOrderId);
            $shipping->save();

            /**
             * This line needs to be called because after activating the kss logic we need
             * register the klarna shipping method in the list of allowed shipping methods
             */
            $this->getKco()->getQuote()->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        }
    }

    /**
     * When a KSS fallback happens then we get back a shop shipping method from the Klarna system.
     * In this case we need to set the flag is_active to false to avoid that the last selected KSS
     * shipping method is still used.
     * With it we avoid that this controller action returns wrong values.
     * Moreover without these lines the fallback will totally break the checkout page since the flag
     * would be still set to true and the request has invalid totals and/or shipping costs (when
     * for example reloading the page or try to place the order after a error happened).
     *
     * @param array $selectedOption
     * @param string $klarnaOrderId
     */
    private function _handleKssFallback(array $selectedOption, $klarnaOrderId)
    {
        if (Mage::helper('klarna_kco/checkout')->isShopShippingMethod($selectedOption['id']) &&
            $this->getKco()->hasActiveKlarnaShippingGateway()
        ) {
            $shipping = Mage::getModel('klarna_kco/klarnashippingmethodgateway')
                ->loadByKlarnaCheckoutId($klarnaOrderId);
            $shipping->setIsActive(false);
            $shipping->save();


            $this->getKco()->clearShippingGateway();

            /**
             * We need to collect the totals because we need full quote object (means there are all
             * necessary information) before collecting the shipping rates.
             * Else we risk that the default selected shipping method of the shop has a double
             * price based on its original price.
             * This happens when this shipping method has the type "Per order"
             */
            $this->getKco()->getQuote()->getShippingAddress()->collectTotals();

            /**
             * We remove the klarna shipping gateway from the list of the registered shipping rates
             * to remove possible side effects.
             */
            $this->getKco()->getQuote()->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        }

        $this->getKco()->clearShippingGateway();
    }

    /**
     * Getting back the selected shipping method
     *
     * @param array $selectedOption
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    protected function _getShippingMethod(array $selectedOption, $quote)
    {
        $shippingMethod = $selectedOption['id'];

        if ($this->getKco()->hasActiveKlarnaShippingGateway() &&
            !empty($selectedOption['shipping_method'])
        ) {
            $shippingGateway = $this->getKco()->getKlarnaShippingGateway();
            $shippingGateway = Mage::helper('klarna_kco/checkout')
                ->updateShippingGatewayMetrics(
                    $shippingGateway,
                    $selectedOption
                );

            $shippingGateway->save();
            $shippingMethod = Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY;
            $quote->collectTotals();
        }

        return $shippingMethod;
    }

    /**
     * API call to notify Magento that the order is now ready to receive order management calls
     */
    public function pushAction()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        $helper             = Mage::helper('klarna_kco');
        $checkoutHelper     = Mage::helper('klarna_kco/checkout');
        $checkoutId         = $this->getRequest()->getParam('id');
        $responseCodeObject = new Varien_Object(
            array(
                'response_code' => 200
            )
        );

        try {
            $klarnaOrder = $this->getKlarnaOrder($checkoutId);
            $order       = $this->getMagentoOrder($klarnaOrder);

            if (!$order->getId()) {
                throw new Klarna_Kco_Exception('Order not found');
            }

            $store = $order->getStore();

            Mage::dispatchEvent(
                'kco_push_notification_before', array(
                    'order'                => $order,
                    'klarna_order_id'      => $checkoutId,
                    'response_code_object' => $responseCodeObject,
                )
            );

            $api = $helper->getApiInstance($store);

            // Add comment to order and update status if still in payment review
            if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
                /** @var Mage_Sales_Model_Order_Payment $payment */
                $payment = $order->getPayment();
                $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true);

                $statusObject = new Varien_Object(
                    array(
                        'status' => $checkoutHelper->getProcessedOrderStatus($order->getStore())
                    )
                );

                Mage::dispatchEvent(
                    'kco_push_notification_before_set_state', array(
                        'order'         => $order,
                        'klarna_order'  => $klarnaOrder,
                        'status_object' => $statusObject
                    )
                );

                if (Mage_Sales_Model_Order::STATE_PROCESSING == $order->getState()) {
                    $order->addStatusHistoryComment(
                        $helper->__('Order processed by Klarna.'),
                        $statusObject->getStatus()
                    );
                }
            }

            $checkoutType = $checkoutHelper->getCheckoutType($store);
            Mage::dispatchEvent(
                "kco_push_notification_after_type_{$checkoutType}", array(
                    'order'                => $order,
                    'klarna_order'         => $klarnaOrder,
                    'response_code_object' => $responseCodeObject,
                )
            );

            Mage::dispatchEvent(
                'kco_push_notification_after', array(
                    'order'                => $order,
                    'klarna_order'         => $klarnaOrder,
                    'response_code_object' => $responseCodeObject,
                )
            );

            // Update order references
            $api->updateMerchantReferences($checkoutId, $order->getIncrementId());

            // Acknowledge order
            if ($order->getState() != Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW && !$klarnaOrder->getIsAcknowledged()) {
                $api->acknowledgeOrder($checkoutId);
                $order->addStatusHistoryComment('Acknowledged request sent to Klarna');
                $klarnaOrder->setIsAcknowledged(1);
                $klarnaOrder->save();
            }

            // Cancel order in Klarna if cancelled on store
            if ($order->isCanceled()) {
                $this->_cancelFailedOrder($klarnaOrder->getKlarnaReservationId(), $order->getStore(), 'Order was already canceled in Magento');
            }

            $order->save();
        } catch (Klarna_Kco_Exception $e) {
            $responseCodeObject->setResponseCode(500);
            $cancelObject = new Varien_Object(
                array(
                    'cancel_order' => true
                )
            );
            Mage::dispatchEvent(
                'kco_push_notification_order_not_found', array(
                    'klarna_order_id'      => $checkoutId,
                    'cancel_object'        => $cancelObject,
                    'response_code_object' => $responseCodeObject,
                    'controller_action'    => $this
                )
            );
            if ($cancelObject->getCancelOrder()) {
                $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($checkoutId);
                if ($klarnaQuote->getId()) {
                    $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($klarnaQuote->getQuoteId());
                    if ($quote->getId() && $checkoutHelper->getDelayedPushNotification($quote->getStore())) {
                        $api = $helper->getApiInstance($quote->getStore());
                        $api->initKlarnaCheckout($checkoutId);
                        $reservationId = $api->getReservationId();
                        $this->_cancelFailedOrder($reservationId, $quote->getStore(), $e->getMessage());

                        // Only log if api supports Delayed Push Notification.
                        // Otherwise, the exception log will be full of useless logs due to race conditions.
                        Mage::logException($e);
                        Mage::log('Canceling order due to exception. checkout-id: ' . $checkoutId, Zend_Log::ALERT, 'klarna_errors.log');
                    }
                }
            }
        } catch (Exception $e) {
            $responseCodeObject->setResponseCode(500);
            Mage::dispatchEvent(
                'kco_push_notification_failed', array(
                    'order'                => $order,
                    'klarna_order_id'      => $checkoutId,
                    'response_code_object' => $responseCodeObject,
                    'controller_action'    => $this
                )
            );
            Mage::logException($e);
        }

        $this->getResponse()->setHttpResponseCode($responseCodeObject->getResponseCode());
    }

    /**
     * We check if we can find a Klarna order. If not, we start creation of a new Magento order.
     *
     * @param $checkoutId
     * @return mixed
     * @throws Klarna_Kco_Exception
     */
    private function getKlarnaOrder($checkoutId)
    {
        $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByCheckoutId($checkoutId);
        if (!$klarnaOrder->getId()) {
            $klarnaOrder = $this->preCheck($checkoutId, $klarnaOrder);
        }
        return $klarnaOrder;
    }

    /**
     * Retrieve Magento order by Klarna order.
     *
     * @param $klarnaOrder
     * @return Mage_Core_Model_Abstract
     */
    private function getMagentoOrder($klarnaOrder)
    {
        try {
            $order = Mage::getModel('sales/order')->load($klarnaOrder->getOrderId());
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $order;
    }

    /**
     * We check for V2 and if the quote is existing.
     *
     * @param $checkoutId
     * @param $klarnaOrder
     * @return mixed
     * @throws Klarna_Kco_Exception
     */
    private function preCheck($checkoutId, $klarnaOrder)
    {
        $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($checkoutId);
        $quote       = Mage::getModel('sales/quote')->load($klarnaQuote->getQuoteId());

        $isV2 = $this->isV2($quote->getStore());
        if ($isV2) {
            $message = __('We don´t create V2 orders on Push.');
            Mage::log('Push: ' . $message . $checkoutId, Zend_Log::ALERT, 'klarna_errors.log');
            throw new Klarna_Kco_Exception($message . $checkoutId);
        }

        if (!$quote->getId()) {
            $message = __('Unable to create the order because the quote id is not correct.');
            Mage::log('Push: ' . $message . $checkoutId, Zend_Log::ALERT, 'klarna_errors.log');
            throw new Klarna_Kco_Exception($message . $checkoutId);
        }

        $this->createOrder($quote, $klarnaOrder);
        return $this->getKlarnaOrder($checkoutId);
    }

    /**
     * We create the order.
     * If you change code here, please review KlarnaController::confirmationAction to see if it needs to be changed also.
     *
     * @param $quote
     * @param $klarnaOrder
     * @throws Klarna_Kco_Exception
     */
    private function createOrder($quote, $klarnaOrder)
    {
        $checkout      = $this->getKco()->setQuote($quote)->getKlarnaCheckout();
        $reservationId = $this->getKco()->getApiInstance()->getReservationId();

        // Check if checkout is complete before placing the order
        if ($checkout->getStatus() != 'checkout_complete' && $checkout->getStatus() != 'created') {
            $message = __('Klarna checkout is not complete yet.');
            Mage::log('Push: ' . $message, Zend_Log::ALERT, 'klarna_errors.log');
            throw new Klarna_Kco_Exception($message);
        }

        // Make sure Magento Addresses match Klarna
        $this->_updateOrderAddresses($checkout);

        $quote->collectTotals();

        // Validate order totals
        $this->_validateOrderTotal($checkout, $quote);

        Mage::dispatchEvent('kco_confirmation_create_order_before', array(
            'quote'           => $quote,
            'checkout'        => $checkout,
            'klarna_order_id' => $checkout->getOrderId(),
        ));

        $order = $this->getKco()->setQuote($quote)->saveOrder();
        $quote->save();

        //handle recurring order
        if (null === $order) {
            $orderId = Mage::getSingleton('checkout/session')->getCurrentKcoRecurringOrderId();
            if ($orderId) {
                $order = Mage::getModel('sales/order')->load($orderId);
            }
        }

        Mage::dispatchEvent('kco_confirmation_create_order_after', array(
            'quote'           => $quote,
            'order'           => $order,
            'klarna_order_id' => $checkout->getOrderId(),
        ));

        $klarnaOrder->setData(array(
            'klarna_checkout_id'    => $checkout->getOrderId(),
            'klarna_reservation_id' => $reservationId,
            'order_id'              => $order->getId()
        ));
        $klarnaOrder->save();

        Mage::dispatchEvent('kco_confirmation_create_order_success', array(
            'quote'           => $quote,
            'order'           => $order,
            'klarna_order'    => $klarnaOrder,
            'klarna_order_id' => $checkout->getOrderId(),
        ));
    }

    /**
     * API call to validate a quote via callback from Klarna before the order is placed
     */
    public function validateAction()
    {
        if (!$this->getRequest()->isPost()) {
            return;
        }

        $checkoutId = $this->getRequest()->getParam('id');

        try {
            $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($checkoutId);
            if (!$klarnaQuote->getId() || $klarnaQuote->getIsChanged()) {
                Mage::log('Validate: Klarna order not found for checkout-id: ' . $checkoutId, Zend_Log::ALERT, 'klarna_errors.log');
                $this->_setValidateFailedResponse();
                return;
            }

            $quote = Mage::getModel('sales/quote')->load($klarnaQuote->getQuoteId());
            if (!$quote->getId() || !$quote->hasItems() || $quote->getHasError()) {
                Mage::log('Validate: Magento quote not found for checkout-id: ' . $checkoutId . ' expecting quote-id: ' . $klarnaQuote->getQuoteId(), Zend_Log::ALERT, 'klarna_errors.log');
                $this->_setValidateFailedResponse();
                return;
            }

            // Reserve Order ID
            $quote->reserveOrderId();
            $quote->save();

            $body = $this->getRequest()->getRawBody();

            try {
                $checkout = Mage::helper('core')->jsonDecode($body);
                $checkout = new Varien_Object($checkout);
            } catch (Exception $e) {
                Mage::log('Error decoding json for checkout-id: ' . $checkoutId, Zend_Log::ALERT, 'klarna_errors.log');
                $this->_sendBadRequestResponse();
                return;
            }

            // Set address is if it's not set
            if (($quote->isVirtual() && true !== $quote->getShippingAddress()->validate())
                || true !== $quote->getBillingAddress()->validate()
            ) {
                $this->getKco()->setQuote($quote);
                $this->_updateOrderAddresses($checkout);
            }

            $checkoutType = Mage::helper('klarna_kco/checkout')->getCheckoutType($quote->getStore());

            Mage::dispatchEvent(
                "kco_validate_before_order_place_type_{$checkoutType}", array(
                    'quote'    => $quote,
                    'checkout' => $checkout,
                    'response' => $this->getResponse()
                )
            );

            Mage::dispatchEvent(
                'kco_validate_before_order_place', array(
                    'quote'    => $quote,
                    'checkout' => $checkout,
                    'response' => $this->getResponse()
                )
            );

            try {
                $this->_validateOrderTotal($checkout, $quote);
            } catch (Klarna_Kco_Exception $e) {
                Mage::logException($e);
                Mage::log('Validate: ' . $e->getMessage(), Zend_Log::ALERT, 'klarna_errors.log');
                $this->_setValidateFailedResponse();

                return;
            }

            $this->getResponse()->setHttpResponseCode(200);
        } catch (Klarna_Kco_Exception $e) {
            Mage::logException($e);
            // Do not modify header or log exception
        } catch (Klarna_Kco_Model_Api_Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHeader('HTTP/1.1', '503 Service Unavailable')->sendResponse();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setHttpResponseCode(500);
        }
    }

    /**
     * Set the response that validation has failed
     *
     * @throws Zend_Controller_Response_Exception
     */
    protected function _setValidateFailedResponse()
    {
        $checkoutId = $this->getRequest()->getParam('id');
        $failUrl    = Mage::getUrl(
            'checkout/klarna/validateFailed', array(
                '_nosid'  => true,
                '_escape' => false,
                '_query'  => array('id' => $checkoutId),
            )
        );
        $this->getResponse()
            ->setHttpResponseCode(303)
            ->setHeader('Location', $failUrl);
    }

    /**
     * Order update from pending status
     */
    public function notificationAction()
    {
        $helper         = Mage::helper('klarna_kco');
        $checkoutHelper = Mage::helper('klarna_kco/checkout');

        if (!$this->getRequest()->isPost()) {
            return;
        }

        $checkoutId = $this->getRequest()->getParam('id');

        try {
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByCheckoutId($checkoutId);
            $order       = Mage::getModel('sales/order')->load($klarnaOrder->getOrderId());

            if (!$order->getId()) {
                throw new Klarna_Kco_Exception('Order not found');
            }

            $body = $this->getRequest()->getRawBody();

            try {
                $notification = Mage::helper('core')->jsonDecode($body);
                $notification = new Varien_Object($notification);
            } catch (Exception $e) {
                Mage::logException($e);
                $this->_sendBadRequestResponse();

                return;
            }

            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $order->getPayment();

            if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
                || $order->getState() == Mage_Sales_Model_Order::STATE_PROCESSING
            ) {
                switch ($notification->getEventType()) {
                    case Klarna_Kco_Model_Api_Kasper::ORDER_NOTIFICATION_FRAUD_REJECTED:
                        $payment->setNotificationResult(true);
                        $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);
                        break;
                    case Klarna_Kco_Model_Api_Kasper::ORDER_NOTIFICATION_FRAUD_STOPPED:
                        $order->addStatusHistoryComment(
                            $helper->__(
                                'Suspected Fraud: DO NOT SHIP. If already shipped, 
                        please attempt to stop the carrier from delivering.'
                            )
                        );
                        $payment->setNotificationResult(true);
                        $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);
                        break;
                    case Klarna_Kco_Model_Api_Kasper::ORDER_NOTIFICATION_FRAUD_ACCEPTED:
                        $payment->setNotificationResult(true);
                        $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_ACCEPT, false);
                        break;
                }

                $statusObject = new Varien_Object(
                    array(
                        'status' => $checkoutHelper->getProcessedOrderStatus($order->getStore())
                    )
                );

                Mage::dispatchEvent(
                    'kco_push_notification_before_set_state', array(
                        'order'         => $order,
                        'klarna_order'  => $klarnaOrder,
                        'status_object' => $statusObject
                    )
                );

                if (Mage_Sales_Model_Order::STATE_PROCESSING == $order->getState()) {
                    $order->addStatusHistoryComment(
                        $helper->__('Order processed by Klarna.'),
                        $statusObject->getStatus()
                    );
                }

                $order->save();
            } elseif (Klarna_Kco_Model_Api_Kasper::ORDER_NOTIFICATION_FRAUD_REJECTED == $notification->getEventType()
                && $order->getState() != Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
            ) {
                $payment->setNotificationResult(false);
                $payment->setIsFraudDetected(true);
                $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);
                $order->save();
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * We check, if we are using KCO V2 currently.
     *
     * @param $store
     * @return bool
     */
    private function isV2($store)
    {
        return in_array(Mage::getStoreConfig('klarna/api/api_version', $store), ['dach', 'nortic']);
    }
}
