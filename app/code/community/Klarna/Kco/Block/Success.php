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
 * Klarna checkout success
 *
 * @method Klarna_Kco_Block_Success setKlarnaSuccessHtml($string)
 * @method string getKlarnaSuccessHtml()
 */
class Klarna_Kco_Block_Success extends Mage_Core_Block_Template
{
    /**
     * Initialize data and prepare it for output
     */
    protected function _beforeToHtml()
    {
        $this->_prepareLastOrder();

        return parent::_beforeToHtml();
    }

    /**
     * Get last order ID from session, fetch it and check whether it can be viewed, printed etc
     */
    protected function _prepareLastOrder()
    {
        $session = Mage::getSingleton('checkout/session');
        $orderId = $session->getLastOrderId();
        //display order info for recurring order
        if (!$orderId && Mage::getSingleton('checkout/session')->getCurrentKcoRecurringOrderId()) {
            $orderId = Mage::getSingleton('checkout/session')->getCurrentKcoRecurringOrderId();
        }

        if ($orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId()) {
                $isVisible = !in_array(
                    $order->getState(),
                    Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates()
                );
                $this->addData(
                    array(
                    'is_order_visible' => $isVisible,
                    'view_order_id'    => $this->getUrl('sales/order/view/', array('order_id' => $orderId)),
                    'print_url'        => $this->getUrl('sales/order/print', array('order_id' => $orderId)),
                    'can_print_order'  => $isVisible,
                    'can_view_order'   => Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible,
                    'order_id'         => $order->getIncrementId(),
                    'order'            => $order
                    )
                );

                $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order);
                if ($klarnaOrder->getId()) {
                    try {
                        $api = Mage::helper('klarna_kco')->getApiInstance($order->getStore());
                        $api->initKlarnaCheckout($klarnaOrder->getKlarnaCheckoutId());
                        $html = $api->getKlarnaCheckoutGui();
                    } catch (Exception $e) {
                        $html = $e->getMessage();
                    }

                    $this->setKlarnaSuccessHtml($html);
                }
            }
        }
    }
}
