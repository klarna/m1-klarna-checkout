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
 * Klarna Payment
 *
 * @method Mage_Core_Model_Store getStore()
 * @method Klarna_Kco_Model_Payment_Kco setStore()
 */
class Klarna_Kco_Model_Payment_Kco extends Mage_Payment_Model_Method_Abstract implements
    Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code          = 'klarna_kco';
    protected $_formBlockType = 'klarna_kco/form_kco';
    protected $_infoBlockType = 'klarna_kco/info_kco';

    /**
     * Availability options
     */
    protected $_isGateway                 = false;
    protected $_canOrder                  = false;
    protected $_canAuthorize              = false;
    protected $_canCapture                = true;
    protected $_canCapturePartial         = true;
    protected $_canRefund                 = true;
    protected $_canRefundInvoicePartial   = true;
    protected $_canVoid                   = true;
    protected $_canUseInternal            = false;
    protected $_canUseCheckout            = false;
    protected $_canUseForMultishipping    = false;
    protected $_canFetchTransactionInfo   = true;
    protected $_canCreateBillingAgreement = false;
    protected $_canReviewPayment          = false;
    protected $_isInitializeNeeded        = true;

    /**
     * Check whether payment method can be used in the admin
     *
     * @param Mage_Sales_Model_Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        if ($quote) {
            $this->_canUseInternal = Mage::helper('klarna_kco')->kcoEnabled($quote->getStore());
        }

        return parent::isAvailable($quote);
    }

    /**
     * Method that will be executed instead of authorize or capture
     * if flag isInitializeNeeded set to true
     *
     * @param string $paymentAction
     * @param object $stateObject
     *
     * @return $this
     */
    public function initialize($paymentAction, $stateObject)
    {
        $payment = $this->getInfoInstance();
        $order   = $payment->getOrder();
        $store   = $order->getStore();
        if (0 >= $order->getGrandTotal()) {
            $stateObject->setState(Mage_Sales_Model_Order::STATE_NEW);
        } elseif (Mage::helper('klarna_kco')->getVersionConfig($store)->getPaymentReview()) {
            $stateObject->setStatus('pending_payment');
            $stateObject->setState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW);
        } else {
            $stateObject->setStatus(Mage::helper('klarna_kco/checkout')->getProcessedOrderStatus($store));
            $stateObject->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
        }

        $stateObject->setIsNotified(false);

        $transactionId = $this->getApi()->getReservationId();

        if (!$transactionId) {
            $quote         = $order->getQuote();
            $klarnaQuote   = Mage::getModel('klarna_kco/klarnaquote')->loadActiveByQuote($quote);
            $transactionId = $klarnaQuote->getKlarnaCheckoutId();
        }

        $payment->setTransactionId($transactionId)
            ->setIsTransactionClosed(0);

        if ($transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH)) {
            $transaction->save();
        }

        return $this;
    }

    /**
     * Check partial capture availability
     *
     * @return bool
     */
    public function canCapturePartial()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getInfoInstance()->getOrder();

        if ($order && $order->getId()) {
            $canCapturePartialObject = new Varien_Object(
                array(
                'can_partial' => Mage::helper('klarna_kco')->getPartialPaymentSupport($order->getStore())
                )
            );

            $checkoutType = Mage::helper('klarna_kco/checkout')->getCheckoutType($order->getStore());
            $eventData    = array(
                'flag_object' => $canCapturePartialObject,
                'order'       => $order
            );

            Mage::dispatchEvent('kco_payment_can_capture_partial', $eventData);
            Mage::dispatchEvent("kco_payment_type_{$checkoutType}_can_capture_partial", $eventData);

            return $canCapturePartialObject->getCanPartial();
        }

        return parent::canCapturePartial();
    }

    /**
     * Check partial refund availability for invoice
     *
     * @return bool
     */
    public function canRefundPartialPerInvoice()
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $this->getInfoInstance()->getOrder();

        if ($order && $order->getId()) {
            $canInvoicePartialObject = new Varien_Object(
                array(
                'can_partial' => Mage::helper('klarna_kco')->getPartialPaymentSupport($order->getStore())
                )
            );

            $checkoutType = Mage::helper('klarna_kco/checkout')->getCheckoutType($order->getStore());
            $eventData    = array(
                'flag_object' => $canInvoicePartialObject,
                'order'       => $order
            );

            Mage::dispatchEvent('kco_payment_can_refund_partial_per_invoice', $eventData);
            Mage::dispatchEvent("kco_payment_type_{$checkoutType}_can_refund_partial_per_invoice", $eventData);

            return $canInvoicePartialObject->getCanPartial();
        }

        return parent::canRefundPartialPerInvoice();
    }

    /**
     * Get payment action for method
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        return self::ACTION_AUTHORIZE;
    }

    /**
     * Fetch transaction info
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string                  $transactionId
     *
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        $order = $payment->getOrder();
        $store = $this->getStore();

        if (Mage::helper('klarna_kco')->getVersionConfig($store)->getPaymentReview()) {
            if (null === $transactionId) {
                $klarnaOrder   = Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order);
                $transactionId = $klarnaOrder->getKlarnaReservationId();
            }

            if (null === $transactionId) {
                $payment->setIsTransactionDenied(true);

                return array();
            }

            $orderStatus = $this->getApi()->getFraudStatus($transactionId);

            if ($orderStatus == 1) {
                $payment->setIsTransactionApproved(true);
            } elseif ($orderStatus == -1) {
                $payment->setIsTransactionDenied(true);
                $payment->getAuthorizationTransaction()->closeAuthorization();
            }
        }

        return array();
    }

    /**
     * Capture payment method
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function capture(Varien_Object $payment, $amount)
    {
        parent::capture($payment, $amount);

        $requestData =  Mage::app()->getRequest()->getPost();
        $klarnaOrder = $this->getKlarnaOrder($payment->getOrder());

        if (!$klarnaOrder->getId() || !$klarnaOrder->getKlarnaReservationId()) {
            Mage::throwException('Unable to capture payment for this order.');
        }

        $response = $this->getApi()->capture($klarnaOrder->getKlarnaReservationId(), $amount, $payment->getInvoice());

        if (!$response->getIsSuccessful()) {
            Mage::throwException('Payment capture failed, please try again.');
        }

        if ($response->getTransactionId()) {
            $payment->setTransactionId($response->getTransactionId());
            if (isset($requestData['invoice']['do_shipment'])
                && $response->getCaptureId()
                && $requestData['invoice']['do_shipment'] == true
                && isset($requestData['tracking'])) {
                $this->addShippingInfoToCapture(
                    $response->getCaptureId(),
                    $klarnaOrder->getKlarnaReservationId(),
                    $requestData['tracking'],
                    $payment->getOrder(),
                    $payment->getInvoice()
                );
            }
        }

        return $this;
    }

    /**
     * Add shipping info to capture
     *
     * @param $captureId
     * @param $klarnaOrderId
     * @param $trackingData
     * @param $order
     * @param $invoice
     */
    private function addShippingInfoToCapture($captureId, $klarnaOrderId, $trackingData, $order, $invoice)
    {
        $response = $this->getApi($order)
            ->addShippingInfo($klarnaOrderId, $captureId, $trackingData);

        if (!$response->getIsSuccessful()) {
            foreach ($response->getErrorMessages() as $message) {
                $invoice->addComment($message, false, false);
            }
        } else {
            $invoice->addComment("Shipping info sent to Klarna API", false, false);
        }
    }

    /**
     * Cancel payment method
     *
     * @param Varien_Object $payment
     *
     * @return $this
     */
    public function cancel(Varien_Object $payment)
    {
        parent::cancel($payment);

        $klarnaOrder = $this->getKlarnaOrder($payment->getOrder());

        if (!$klarnaOrder->getId() || !$klarnaOrder->getKlarnaReservationId()) {
            Mage::throwException('Unable to cancel payment for this order.');
        }

        $response = $this->processCancellation($klarnaOrder, $payment->getOrder());

        if (!$response->getIsSuccessful()) {
            Mage::throwException('Order cancellation failed, please try again.');
        }

        if ($response->getTransactionId()) {
            $payment->setTransactionId($response->getTransactionId());
        }

        return $this;
    }

    /**
     * If order has invoices already, release remaining authorization. Otherwise, cancel it
     *
     * @param Klarna_Kco_Model_Klarnaorder $klarnaOrder
     * @param Mage_Sales_Model_Order       $order
     * @return Klarna_Kco_Model_Api_Response
     */
    private function processCancellation(Klarna_Kco_Model_Klarnaorder $klarnaOrder, Mage_Sales_Model_Order $order)
    {
        if ($order->hasInvoices()) {
            return $this->getApi()->release($klarnaOrder->getKlarnaReservationId());
        }

        return $this->getApi()->cancel($klarnaOrder->getKlarnaReservationId());
    }

    /**
     * Void payment
     *
     * Same as cancel
     *
     * @param Varien_Object $payment
     *
     * @return $this
     */
    public function void(Varien_Object $payment)
    {
        return $this->cancel($payment);
    }

    /**
     * Refund specified amount for payment
     *
     * @param Varien_Object $payment
     * @param float         $amount
     *
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        parent::refund($payment, $amount);

        $klarnaOrder = $this->getKlarnaOrder($payment->getOrder());

        if (!$klarnaOrder->getId() || !$klarnaOrder->getKlarnaReservationId()) {
            Mage::throwException('Unable to refund payment for this order.');
        }

        $response = $this->getApi()->refund($klarnaOrder->getKlarnaReservationId(), $amount, $payment->getCreditmemo());

        if (!$response->getIsSuccessful()) {
            Mage::throwException('Payment refund failed, please try again.');
        }

        if ($response->getTransactionId()) {
            $payment->setTransactionId($response->getTransactionId());
        }

        return $this;
    }

    /**
     * Get a Klarna order
     *
     * @param $order
     *
     * @return Klarna_Kco_Model_Klarnaorder
     */
    public function getKlarnaOrder($order)
    {
        if (!$this->hasData('klarna_order')) {
            $this->setData('klarna_order', Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order));
        }

        return $this->getData('klarna_order');
    }

    /**
     * Get api class
     *
     * @return Klarna_Kco_Model_Api_Abstract
     * @throws Klarna_Kco_Model_Api_Exception
     */
    public function getApi()
    {
        if (!$this->hasData('api')) {
            $api = Mage::helper('klarna_kco')->getApiInstance($this->getStore());
            $this->setData('api', $api);
        }

        return $this->getData('api');
    }

    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }

    /**
     * Submit to the gateway (first try)
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(
        Mage_Payment_Model_Recurring_Profile $profile,
        Mage_Payment_Model_Info $payment
    ) {
        $payment->setSkipTransactionCreation(true);
        if (Mage::getSingleton('checkout/session')->getCurrentKcoOrderReservation()) {
            $profile->setReferenceId(Mage::getSingleton('checkout/session')->getCurrentKcoOrderReservation());
        }

        if (Mage::getSingleton('checkout/session')->getCurrentKcoRecurringToken()) {
            $vendorProfileInfo = array('kco_recurring_token' => Mage::getSingleton('checkout/session')->getCurrentKcoRecurringToken());
            $profile->setProfileVendorInfo(json_encode($vendorProfileInfo));
        }

        $kcoHelper = Mage::helper('klarna_kcokred');
        $order = $kcoHelper->createRecurringOrder($profile);
        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
        Mage::getSingleton('checkout/session')->setCurrentKcoRecurringOrderId($order->getId());
        return $this;
    }

    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        return $this;
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails()
    {
        return true;
    }

    /**
     * Update recurring profile
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        return $this;
    }
}
