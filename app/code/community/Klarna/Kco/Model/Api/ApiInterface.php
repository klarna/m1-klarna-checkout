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
 * Klarna api integration interface
 */
interface Klarna_Kco_Model_Api_ApiInterface
{
    /**
     * Create or update an order in the checkout API
     *
     * @param string     $checkoutId
     * @param bool|false $createIfNotExists
     * @param bool|false $updateItems
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function initKlarnaCheckout($checkoutId = null, $createIfNotExists = false, $updateItems = false);

    /**
     * Get Klarna Checkout Reservation Id
     *
     * @return string
     */
    public function getReservationId();

    /**
     * Capture an amount on an order
     *
     * @param string                         $orderId
     * @param float                          $amount
     * @param Mage_Sales_Model_Order_Invoice $invoice
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function capture($orderId, $amount, $invoice = null);

    /**
     * Refund for an order
     *
     * @param string                            $orderId
     * @param float                             $amount
     * @param Mage_Sales_Model_Order_Creditmemo $creditMemo
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function refund($orderId, $amount, $creditMemo = null);

    /**
     * Cancel an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function cancel($orderId);

    /**
     * Release the authorization for an order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function release($orderId);

    /**
     * Get Klarna checkout order details
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function getKlarnaOrder();

    /**
     * Set Klarna checkout order details
     *
     * @param Varien_Object $klarnaOrder
     *
     * @return $this
     */
    public function setKlarnaOrder(Varien_Object $klarnaOrder);

    /**
     * Get Klarna checkout html snippets
     *
     * @return string
     */
    public function getKlarnaCheckoutGui();

    /**
     * Get order details for a completed Klarna order
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function getPlacedKlarnaOrder($orderId);

    /**
     * Acknowledge an order in order management
     *
     * @param string $orderId
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function acknowledgeOrder($orderId);

    /**
     * Update merchant references for a Klarna order
     *
     * @param string $orderId
     * @param string $reference1
     * @param string $reference2
     *
     * @return Klarna_Kco_Model_Api_Response
     */
    public function updateMerchantReferences($orderId, $reference1, $reference2 = null);

    /**
     * Get the fraud status of an order to determine if it should be accepted or denied within Magento
     *
     * Return value of 1 means accept
     * Return value of 0 means still pending
     * Return value of -1 means deny
     *
     * @param string $orderId
     *
     * @return int
     */
    public function getFraudStatus($orderId);
}
