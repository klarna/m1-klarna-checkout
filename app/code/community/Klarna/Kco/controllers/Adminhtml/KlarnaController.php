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
 * @author     Marcin Dancewicz <marcin.dancewicz@klarna.com>
 */

/**
 * Class Klarna_Kco_Adminhtml_AdminController
 */
class Klarna_Kco_Adminhtml_KlarnaController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Admin Klarna Checkout
     */
    public function indexAction()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        try {
            $order = $this->_initOrder();

            if (!Mage::helper('klarna_kco/checkout')->getMotoEnabled($order->getStore())) {
                $this->_getSession()->addError(
                    Mage::helper('klarna_kco')->__('Admin orders are not available for this store.')
                );
                $this->_redirect('*/sales_order/view', array('order_id' => $orderId));

                return;
            }

            if (!$order->getId()) {
                $this->_getSession()->addError(Mage::helper('klarna_kco')->__('This order no longer exists.'));
                $this->_redirect('*/sales_order/view', array('order_id' => $orderId));

                return;
            }

            $this->loadLayout();
            $this->renderLayout();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/sales_order/view', array('order_id' => $orderId));
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Failed to send the order to Klarna.'));
            $this->_redirect('*/sales_order/view', array('order_id' => $orderId));
        }
    }

    /**
     * Updates the order with Klarna details then redirects to success page
     */
    public function confirmationAction()
    {
        $checkoutId    = $this->getRequest()->getParam('id');
        $reservationId = null;

        if ($checkoutId != $this->_getSession()->getKlarnaCheckoutId()) {
            $this->_getSession()->addError('Error completing order');
            $this->_redirect(
                '*/sales_order/view',
                array('order_id' => $this->_getSession()->getKlarnaCheckoutOrderId())
            );

            return;
        }

        $this->_getSession()->setKlarnaCheckoutId(null);

        $order = $this->_initOrder();

        try {
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder');
            $klarnaOrder->loadByCheckoutId($checkoutId);
            if ($klarnaOrder->getId()) {
                $this->_getSession()->addError(
                    Mage::helper('klarna_kco')->__('Order already exist.')
                );

                $this->_redirect('*/sales_order');

                return;
            }

            $api      = Mage::helper('klarna_kco')->getApiInstance();
            $checkout = $api->initKlarnaCheckout($checkoutId, false, false);

            // Check if checkout is complete before placing the order
            if ($checkout->getStatus() != 'checkout_complete' && $checkout->getStatus() != 'created') {
                $this->_getSession()->addError(
                    Mage::helper('klarna_kco')->__('Unable to process order. Please try again')
                );

                $this->_redirect('*/sales_order');

                return;
            }

            $klarnaOrder->setData(
                array(
                'klarna_checkout_id'    => $checkoutId,
                'klarna_reservation_id' => $api->getReservationId(),
                'order_id'              => $order->getId()
                )
            );

            $klarnaOrder->save();

            $payment = $order->getPayment();
            $payment->setTransactionId($api->getReservationId())
                ->setIsTransactionClosed(0);

            if ($transaction = $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH)) {
                $transaction->save();
            }

            $payment->save();
        } catch (Klarna_Kco_Model_Api_Exception $e) {
            $this->_cancelFailedOrder($reservationId);
            Mage::logException($e);

            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__($e->getMessage())
            );

            $this->_redirect('*/sales_order');

            return;
        } catch (Exception $e) {
            $this->_cancelFailedOrder($reservationId);
            Mage::logException($e);

            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__('Unable to complete order. Please try again')
            );

            $this->_redirect('*/sales_order');

            return;
        }

        $this->_redirect('*/klarna/success');
    }

    /**
     * Successful checkout admin action
     */
    public function successAction()
    {
        try {
            $this->_initOrder();
        } catch (Klarna_Kco_Exception $e) {
            $this->_redirect('*/sales_order');
        }

        $this->_getSession()->setKlarnaCheckoutOrderId(null);
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Cancel admin order
     */
    public function cancelAction()
    {
        try {
            $order = $this->_initOrder();
            $order->registerCancellation('Cancelled Klarna Order', false);

            Mage::dispatchEvent('order_cancel_after', array('order' => $order));

            $order->save();

            $this->_getSession()->addSuccess($this->__('Order cancelled'));
            $this->_redirect('*/sales_order/view', array('order_id' => $order->getId()));
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__('Unable to cancel order. Please try again')
            );
            $this->_redirect('*/sales_order');
        }
    }

    /**
     * Initialize order model instance
     *
     * @return Mage_Sales_Model_Order
     * @throws Klarna_Kco_Exception
     */
    protected function _initOrder()
    {
        Mage::unregister('klarna_admin_checkout_order');

        if ($id = $this->getRequest()->getParam('order_id')) {
            $this->_getSession()->setKlarnaCheckoutOrderId($id);
        } else {
            $id = $this->_getSession()->getKlarnaCheckoutOrderId();
        }

        $order = Mage::getModel('sales/order')->load($id);

        if (!$order->getId()) {
            throw new Klarna_Kco_Exception('Order no longer exists!');
        }

        Mage::register('klarna_admin_checkout_order', $order);

        return $order;
    }

    /**
     * Cancel a failed order in Klarna
     *
     * @param string                $reservationId
     * @param Mage_Core_Model_Store $store
     *
     * @return $this
     */
    protected function _cancelFailedOrder($reservationId, $store = null)
    {
        if (empty($reservationId)) {
            return $this;
        }

        try {
            /**
             * This will only cancel orders already available in order management.
             * Orders not yet available for cancellation will be cancelled on the push or will expire
             */
            $api = Mage::helper('klarna_kco')->getApiInstance($store);
            $api->cancel($reservationId);
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $this;
    }

    /**
     * Check is allowed access to action
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/order');
    }
}
