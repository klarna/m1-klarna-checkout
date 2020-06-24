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
 * Klarna checkout admin success
 *
 * @method Klarna_Kco_Block_Success setKlarnaSuccessHtml($string)
 * @method string getKlarnaSuccessHtml()
 */
class Klarna_Kco_Block_Admin_Success extends Mage_Adminhtml_Block_Widget_Container
{
    /**
     * Init block
     */
    public function _construct()
    {
        parent::_construct();

        $this->_headerText = $this->__('Klarna Checkout Success');

        if ($order = Mage::registry('klarna_admin_checkout_order')) {
            $backUrl = $this->getUrl('*/sales_order/view', array('order_id' => $order->getId()));
            $this->_addButton(
                'back', array(
                'label'   => Mage::helper('adminhtml')->__('View Order Details'),
                'onclick' => 'window.location.href=\'' . $backUrl . '\'',
                'class'   => 'go',
                )
            );
        }

        $this->_addButton(
            'order_grid', array(
            'label'   => Mage::helper('adminhtml')->__('View All Orders'),
            'onclick' => 'window.location.href=\'' . $this->getUrl('*/sales_order') . '\'',
            'class'   => 'go',
            )
        );
    }

    /**
     * Initialize data and prepare it for output
     */
    protected function _beforeToHtml()
    {
        $this->_prepareLastOrder();

        return parent::_beforeToHtml();
    }

    /**
     * Get last order ID and set the success html
     */
    protected function _prepareLastOrder()
    {
        if ($order = Mage::registry('klarna_admin_checkout_order')) {
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order);
            $api         = Mage::helper('klarna_kco')->getApiInstance($order->getStore());
            $klarnaOrder = $api->initKlarnaCheckout($klarnaOrder->getKlarnaReservationId(), false, false);

            if ($klarnaOrder->getId()) {
                try {
                    $html = $api->getKlarnaCheckoutGui();
                } catch (Exception $e) {
                    $html = $e->getMessage();
                }

                $this->setKlarnaSuccessHtml($html);
            }
        } else {
            $this->setKlarnaSuccessHtml('Error loading order details.');
        }
    }
}
