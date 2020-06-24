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

/**
 * Klarna Kred Data Helper
 */
class Klarna_KcoKred_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * check if order is recurring
     *
     * @return mixed
     */
    public function isKcoRecurringOrder()
    {
        return Mage::getSingleton('checkout/session')->getIsKlarnaRecurringOrder();
    }

    /**
     * create order from recurring profile
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function createRecurringOrder(
        Mage_Payment_Model_Recurring_Profile $profile
    ) {
        $productItemInfo = new Varien_Object;
        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $productItemInfo->setPrice($profile->getBillingAmount());

        $order = $profile->createOrder($productItemInfo);
        $trans_id = Mage::getSingleton('checkout/session')->getCurrentKcoOrderReservation();

        $payment = $order->getPayment();
        $payment->setTransactionId($trans_id)->setIsTransactionClosed(1);
        $order->save();
        $profile->addOrderRelation($order->getId());
        $order->save();
        $payment->save();

        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setTxnId($trans_id);
        $transaction->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
        $transaction->setPaymentId($payment->getId());
        $transaction->setOrderId($order->getId());
        $transaction->setOrderPaymentObject($payment);
        $transaction->setIsClosed(1);
        $transaction->save();

        return $order;
    }


    /**
     * create recurring order through Klarna API
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function createRemoteRecurringOrder(Mage_Payment_Model_Recurring_Profile $profile, Mage_Sales_Model_Order $order)
    {
        $kcoHelper = Mage::helper('klarna_kco');
        $store = Mage::getModel('core/store')->load($profile->getStoreId());
        $apiModel = $kcoHelper->getApiInstance($store);
        return $apiModel->createRecurringOrder($profile, $order);
    }

    /**
     * load recurring profiles to be process
     *
     * @return mixed
     */
    public function loadEligibleRecurringProfiles()
    {
        $currentTimeStamp = date('Y-m-d H:i:s', time());
        $collection = Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToSelect('profile_id')//load all fields, see EAV below
            ->addFieldToFilter('method_code', array('eq' => 'klarna_kco'))
            ->addFieldToFilter('state', array('eq' => 'active'))
            ->addFieldToFilter('updated_at', array('lt' => $currentTimeStamp))
            ->addFieldToFilter('start_datetime', array('lt' => $currentTimeStamp))
            ->addFieldToFilter(
                new Zend_Db_Expr('now()'), array(
                'gt' => new Zend_Db_Expr(
                    'CASE period_unit
              WHEN "day" 			THEN DATE_ADD(updated_at, INTERVAL period_frequency DAY)
              WHEN "week" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency WEEK)
              WHEN "semi_month" 	THEN DATE_ADD(updated_at, INTERVAL (period_frequency * 2) WEEK)
              WHEN "month" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency MONTH)
              WHEN "year" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency YEAR)
              END'
                )
                )
            );

        return $collection;
    }

    /**
     * get recurring token from profile object
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @return bool|string
     */
    public function getProfileRecurringToken(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $vendorInfo = json_decode($profile->getProfileVendorInfo(), true);
        return (is_array($vendorInfo) && isset($vendorInfo['kco_recurring_token'])) ? $vendorInfo['kco_recurring_token'] : false;
    }

    /**
     * cancel reserved order on kco
     *
     * @param $reservationId
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     *
     * @return bool
     */
    public function cancelKlarnaReservationOrder($reservationId, Mage_Payment_Model_Recurring_Profile $profile)
    {
        $store = Mage::getModel('core/store')->load($profile->getStoreId());
        $api = Mage::helper('klarna_kco')->getApiInstance($store);
        $response = $api->cancel($reservationId);
        if (!$response->getIsSuccessful()) {
            return false;
        }

        return true;
    }

    /**
     * cancel magento recurring order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @param $message
     *
     * @throws Exception
     */
    public function cancelLocalRecurringOrder(Mage_Sales_Model_Order $order, $message)
    {
        if ($order->canCancel()) {
            $order->addStatusHistoryComment($message);
            $order->cancel()
                ->save();
        }
    }

    /**
     * create invoice online for local order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @param $klarnaReservationId
     *
     * @throws Klarna_KcoKred_Exception_RecurringOrder_CreateOrderInvoiceException
     *
     * @throws Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException
     */
    public function invoiceMagentoOrder(Mage_Sales_Model_Order $order, $klarnaReservationId)
    {
        if ($order->canInvoice()) {
            try {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                $invoice->register()->pay();
            } catch (Exception $e) {
                $exceptionMessage = 'Error with capture payment: ' . $e->getMessage();
                throw new Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException(
                    $exceptionMessage,
                    0, null, $klarnaReservationId, $order
                );
            }

            try {
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();

                $invoice->sendEmail();
            } catch (Exception $e) {
                $exceptionMessage = 'Error with create invoice: ' . $e->getMessage();
                throw new Klarna_KcoKred_Exception_RecurringOrder_CreateOrderInvoiceException(
                    $exceptionMessage,
                    0, null, $invoice->getTransactionId(), $order
                );
            }
        }

    }

    /**
     * create recurring order on magento
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @param $klarnaReservationId
     *
     * @throws Exception
     */
    public function createKlarnaOrder(Mage_Sales_Model_Order $order, $klarnaReservationId)
    {
        try {
            //throw new Exception('exception save klarna order');
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder');
            $klarnaOrder->setData(
                array(
                    'klarna_checkout_id' => '',
                    'klarna_reservation_id' => $klarnaReservationId,
                    'order_id' => $order->getId(),
                    'is_recurring' => 1
                )
            );
            $klarnaOrder->save();
        } catch (Exception $e) {
            $exceptionMessage = 'Error with process order: ' . $e->getMessage();
            throw new Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException(
                $exceptionMessage,
                0, null, $klarnaReservationId, $order
            );
        }

    }

    /**
     * @param $invoiceId
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return mixed
     */
    public function refundKlarnaOrderInvoice($invoiceId, Mage_Payment_Model_Recurring_Profile $profile)
    {
        $store = Mage::getModel('core/store')->load($profile->getStoreId());
        $api = Mage::helper('klarna_kco')->getApiInstance($store);
        $response = $api->refundByKlarnaInvoiceId($invoiceId);
        return $response;
    }
}
