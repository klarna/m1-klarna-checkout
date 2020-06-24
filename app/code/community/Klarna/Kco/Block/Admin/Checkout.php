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
 * @author     Marcin Dancewicz <marcin.dancewicz@klarna.com>
 */
class Klarna_Kco_Block_Admin_Checkout extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * Init block
     */
    public function _construct()
    {
        parent::_construct();

        $coreHelper        = Mage::helper('core');
        $this->_headerText = $this->__('Klarna Checkout');

        if ($order = Mage::registry('klarna_admin_checkout_order')) {
            $backUrl = $this->getUrl('*/sales_order/view', array('order_id' => $order->getId()));
        } else {
            $backUrl = $this->getUrl('*/sales_order');
        }

        $this->_addButton(
            'back', array(
            'label'   => Mage::helper('adminhtml')->__('Back'),
            'onclick' => 'window.location.href=\'' . $backUrl . '\'',
            'class'   => 'back',
            )
        );

        $confirmationMessage = $coreHelper->jsQuoteEscape(
            Mage::helper('sales')->__('Are you sure you want to cancel this order?')
        );
        $this->_addButton(
            'order_cancel', array(
            'label'   => Mage::helper('sales')->__('Cancel Order'),
            'onclick' => 'deleteConfirm(\'' . $confirmationMessage . '\', \'' . $this->getUrl('*/*/cancel') . '\')',
            )
        );
    }

    /**
     * Get Klarna snippet from api
     *
     * @return string
     * @throws Klarna_Kco_Exception
     */
    public function getKlarnaHtml()
    {
        Mage::getSingleton('adminhtml/session')->setKlarnaCheckoutId(null);

        try {
            if ($order = Mage::registry('klarna_admin_checkout_order')) {
                $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($order->getQuoteId());

                if (!$quote->getId()) {
                    throw new Klarna_Kco_Exception('Unable to load quote for order.');
                }

                $api = Mage::helper('klarna_kco')->getApiInstance($quote->getStore())
                    ->setIsAdmin(true)
                    ->setQuote($quote);

                $api->initKlarnaCheckout(null, true, false);

                Mage::getSingleton('adminhtml/session')->setKlarnaCheckoutId($api->getReservationId());

                if ($snippet = $api->getKlarnaCheckoutGui()) {
                    return $snippet;
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return Mage::helper('klarna_kco')->__(
            'Klarna Checkout has failed to load. Please <a href="javascript:;" onclick="location.reload(true)">reload checkout.</a>'
        );
    }
}
