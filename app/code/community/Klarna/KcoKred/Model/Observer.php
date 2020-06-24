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
 * Klarna Kred API Observer
 */
class Klarna_KcoKred_Model_Observer
{
    /**
     * Acknowledge Kred orders on confirmation page and avoid push notifcation
     *
     * @param Varien_Event_Observer $observer
     */
    public function acknowledgeKredOrderOnConfirmation(Varien_Event_Observer $observer)
    {
        $helper         = Mage::helper('klarna_kco');
        $checkoutHelper = Mage::helper('klarna_kco/checkout');

        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        /** @var Klarna_Kco_Model_Klarnaorder $klarnaOrder */
        $klarnaOrder = $observer->getKlarnaOrder();

        $checkoutId   = $klarnaOrder->getKlarnaCheckoutId();
        $checkoutType = $checkoutHelper->getCheckoutType($order->getStore());
        $pushQueue    = Mage::getModel('klarna_kcokred/pushqueue')->loadByCheckoutId($checkoutId);

        try {
            if ('kred' == $checkoutType && !$klarnaOrder->getIsAcknowledged() && $pushQueue->getId()) {
                /** @var Mage_Sales_Model_Order_Payment $payment */
                $payment = $order->getPayment();
                $payment->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_UPDATE, true);
                $status = false;

                if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
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
                        $status = $statusObject->getStatus();
                    }
                }

                $order->addStatusHistoryComment($helper->__('Order processed by Klarna.'), $status);

                $api = Mage::helper('klarna_kco')->getApiInstance($order->getStore());
                $api->updateMerchantReferences($checkoutId, $order->getIncrementId());
                $api->acknowledgeOrder($checkoutId);
                $order->addStatusHistoryComment('Acknowledged request sent to Klarna');
                $order->save();

                $klarnaOrder->setIsAcknowledged(1);
                $klarnaOrder->save();
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Log orders that were not found during push notification
     *
     * @param Varien_Event_Observer $observer
     */
    public function logOrderPushNotification(Varien_Event_Observer $observer)
    {
        $checkoutHelper = Mage::helper('klarna_kco/checkout');
        $klarnaQuote    = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($observer->getKlarnaOrderId());
        if ($klarnaQuote->getId()) {
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($klarnaQuote->getQuoteId());
            if ($quote->getId() && !$checkoutHelper->getDelayedPushNotification($quote->getStore())) {
                $pushQueue = Mage::getModel('klarna_kcokred/pushqueue')
                    ->loadByCheckoutId($klarnaQuote->getKlarnaCheckoutId());
                if (!$pushQueue->getId()) {
                    $pushQueue->setKlarnaCheckoutId($observer->getKlarnaOrderId());
                }

                $pushQueue->setCount((int)$pushQueue->getCount() + 1);
                $pushQueue->save();

                $cancelCountObject = new Varien_Object(
                    array(
                    'cancel_ceiling' => 2
                    )
                );

                Mage::dispatchEvent(
                    'kco_add_to_push_queue', array(
                    'klarna_quote'        => $klarnaQuote,
                    'quote'               => $quote,
                    'push_queue'          => $pushQueue,
                    'cancel_count_object' => $cancelCountObject
                    )
                );

                if (false !== $cancelCountObject->getCancelCeiling()
                    && $pushQueue->getCount() >= $cancelCountObject->getCancelCeiling()
                ) {
                    try {
                        $helper = Mage::helper('klarna_kco');
                        $api    = $helper->getApiInstance($quote->getStore());

                        $api->initKlarnaCheckout($observer->getKlarnaOrderId());

                        if ($api->getReservationId()) {
                            $api->cancel($api->getReservationId());
                        }
                    } catch (Exception $e) {
                        Mage::logException($e);
                    }
                }

                if ($observer->hasResponseCodeObject()) {
                    $observer->getResponseCodeObject()->setResponseCode(200);
                }
            }
        }
    }

    /**
     * Disable partial payments for orders with discounts
     *
     * This is due to some limitations using Klarna with Magento in certain markets on Kred
     *
     * @param Varien_Event_Observer $observer
     */
    public function disablePartialPaymentsForOrdersWithDiscounts(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();

        if (0 > $order->getBaseDiscountAmount()) {
            $observer->getFlagObject()->setCanPartial(false);
        }
    }

    /**
     * Clean claimed and expired orders from the push queue
     *
     * @param Varien_Event_Observer $observer
     */
    public function pushQueueCleanup(Varien_Event_Observer $observer)
    {
        Mage::getResourceModel('klarna_kcokred/pushqueue')->clean($observer->getLog());
    }

    /**
     * mark session as recurring order
     *
     * @param Varien_Event_Observer $observer
     */
    public function beforeApiRequestGenerate(Varien_Event_Observer $observer)
    {
        $quote = $observer->getQuote();
        if ($quote->hasRecurringItems()) {
            Mage::getSingleton('checkout/session')->setIsKlarnaRecurringOrder(true);
        }
    }


    /**
     * update api request for recurring order
     *
     * @param Varien_Event_Observer $observer
     */
    public function requestUpdateForRecurringOrder(Varien_Event_Observer $observer)
    {
        $requestBuilder = $observer->getBuilder();
        $request = $requestBuilder->getRequest();
        $quote = $observer->getQuote();
        if ($quote->hasRecurringItems()) {
            $request['recurring'] = true;
        }

        $requestBuilder->setRequest($request);
    }

    /**
     * set recurring token value in session
     *
     * @param Varien_Event_Observer $observer
     */
    public function requestUpdateForRecurringToken(Varien_Event_Observer $observer)
    {
        $klarnaCheckout = $observer->getCheckout();
        if ($klarnaCheckout->getRecurringToken()) {
            Mage::getSingleton('checkout/session')->setCurrentKcoRecurringToken($klarnaCheckout->getRecurringToken());
            Mage::getSingleton('checkout/session')->setCurrentKcoOrderReservation($klarnaCheckout->getReservation());
        }
    }


    /**
     * process eligible recurring profile and create orders
     *
     * @throws Exception
     */
    public function processRecurringProfile()
    {
        $kcoKredHelper = Mage::helper('klarna_kcokred');
        $eligibleProfiles = $kcoKredHelper->loadEligibleRecurringProfiles();
        foreach ($eligibleProfiles as $profileData) {
            $profile = Mage::getModel('sales/recurring_profile')->load($profileData->getProfileId());

            Mage::dispatchEvent(
                'kco_recurring_profile_process_before', array(
                    'profile' => $profile
                )
            );

            try {
                $order = $kcoKredHelper->createRecurringOrder($profile, true);
                if ($order->getId()) {
                    try {
                        $remoteOrder = $kcoKredHelper->createRemoteRecurringOrder($profile, $order);
                    } catch (Exception $e) {
                        $exceptionMessage = 'Unable to process payment for recurring order '.$order->getIncrementId();
                        throw new Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException(
                            $exceptionMessage,
                            0, null, false, $order
                        );
                    }

                    if ($remoteOrder['success'] && isset($remoteOrder['reservation_id'])) {
                        $kcoKredHelper->createKlarnaOrder($order, $remoteOrder['reservation_id']);
                        $kcoKredHelper->invoiceMagentoOrder($order, $remoteOrder['reservation_id']);
                        $profile->setUpdatedAt(date('Y-m-d H:i:s'), time());
                    }else{
                        $exceptionMessage = 'Unable to process payment for recurring order '.$order->getIncrementId();
                        throw new Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException(
                            $exceptionMessage,
                            0, null, false, $order
                        );
                    }
                } else {
                    $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
                }
            } catch (Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException $e) {
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);

                if ($e->getKlarnaOrderReservationId()) {
                    $kcoKredHelper->cancelKlarnaReservationOrder($e->getKlarnaOrderReservationId(), $profile);
                }

                if ($e->getLocalOrder()) {
                    $kcoKredHelper->cancelLocalRecurringOrder($e->getLocalOrder(), $e->getMessage());
                }

                Mage::logException($e);
            } catch (Klarna_KcoKred_Exception_RecurringOrder_CreateOrderInvoiceException $e) {
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);

                if ($e->getKlarnaOrderInvoiceId()) {
                    $kcoKredHelper->refundKlarnaOrderInvoice($e->getKlarnaOrderInvoiceId(), $profile);
                }

                if ($e->getLocalOrder()) {
                    $kcoKredHelper->cancelLocalRecurringOrder($e->getLocalOrder(), $e->getMessage());
                }

                Mage::logException($e);
            } catch (Exception $e) {
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
                Mage::logException($e);
            }

            Mage::dispatchEvent(
                'kco_recurring_profile_process_after', array(
                    'profile' => $profile
                )
            );

            $profile->save();
        }
    }
}
