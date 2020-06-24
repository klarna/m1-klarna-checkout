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
 * @author     Fei Chen <jason.grim@klarna.com>
 */

class Klarna_KcoKred_Exception_RecurringOrder_CreateOrderInvoiceException extends Klarna_KcoKred_Exception
{
    /**
     * @var bool|string
     */
    protected $klarnaOrderInvoiceId = false;

    /**
     * @var Mage_Sales_Model_Order
     */
    protected $order;

    /**
     * Klarna_KcoKred_Exception_RecurringOrder_ProcessLocalOrderException constructor.
     *
     * @param string $message
     *
     * @param int $code
     *
     * @param Exception|null $previous
     *
     * @param bool|string $paymentId
     *
     * @param Mage_Sales_Model_Order $order
     */
    public function __construct(
        $message = "",
        $code = 0,
        Exception $previous = null,
        $paymentId = false,
        Mage_Sales_Model_Order $order
    ) {
        $this->klarnaOrderInvoiceId = $paymentId;
        $this->order = $order;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return bool|string
     */
    public function getKlarnaOrderInvoiceId()
    {
        return $this->klarnaOrderInvoiceId;
    }

    /**
     * @return Mage_Sales_Model_Order
     */
    public function getLocalOrder()
    {
        return $this->order;
    }

}
