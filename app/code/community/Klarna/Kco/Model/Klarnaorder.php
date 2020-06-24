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
 * Klarna order to associate a Klarna order with a Magento order
 *
 * @method Klarna_Kco_Model_Klarnaquote setKlarnaCheckoutId()
 * @method string getKlarnaCheckoutId()
 * @method Klarna_Kco_Model_Klarnaquote setKlarnaReservationId()
 * @method string getKlarnaReservationId()
 * @method Klarna_Kco_Model_Klarnaquote setOrderId()
 * @method int getOrderId()
 * @method Klarna_Kco_Model_Klarnaquote setIsAcknowledged(int $value)
 * @method int getIsAcknowledged()
 */
class Klarna_Kco_Model_Klarnaorder extends Mage_Core_Model_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kco/klarnaorder');
    }

    /**
     * Load by checkout id
     *
     * @param string $checkoutId
     *
     * @return Klarna_Kco_Model_Klarnaorder
     */
    public function loadByCheckoutId($checkoutId)
    {
        return $this->load($checkoutId, 'klarna_checkout_id');
    }

    /**
     * Load by an order
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return Klarna_Kco_Model_Klarnaorder
     */
    public function loadByOrder(Mage_Sales_Model_Order $order)
    {
        return $this->load($order->getId(), 'order_id');
    }
}
