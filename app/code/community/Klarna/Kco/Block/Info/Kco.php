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
 * Klarna payment info
 */
class Klarna_Kco_Block_Info_Kco extends Mage_Payment_Block_Info
{
    /**
     * Default Payment method title
     */
    const DEFAULT_TITLE = 'Klarna Checkout';

    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('klarna/payment/info.phtml');
    }

    /**
     * Prepare information for payment
     *
     * @param Varien_Object|array $transport
     *
     * @return Varien_Object
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $info = $this->getInfo();
        $klarnaReferenceId = $info->getAdditionalInformation('klarna_reference');
        $order = $info->getOrder();
        $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order);
        $isAdminBlock = $this->getParentBlock()
            && $this->getParentBlock() instanceof Mage_Adminhtml_Block_Sales_Order_Payment;

        if ($isAdminBlock && $klarnaOrder->getId() && $klarnaOrder->getKlarnaCheckoutId()) {
            $transport->setData($this->helper('klarna_kco')->__('Checkout ID'), $klarnaOrder->getKlarnaCheckoutId());
            
            $merchantPortalLink = $this->helper('klarna_kco')->getOrderMerchantPortalLink($order, $klarnaOrder);
            if ($merchantPortalLink) {
                $transport->setData(
                    $this->helper('klarna_kco')->__('Merchant Portal'),
                    $merchantPortalLink
                );
            }

            if ($klarnaOrder->getKlarnaReservationId()
                && $klarnaOrder->getKlarnaReservationId() != $klarnaOrder->getKlarnaCheckoutId()
            ) {
                $transport->setData(
                    $this->helper('klarna_kco')->__('Reservation'),
                    $klarnaOrder->getKlarnaReservationId()
                );
            }
        }

        if ($klarnaReferenceId) {
            $transport->setData($this->helper('klarna_kco')->__('Reference'), $klarnaReferenceId);
        }

        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice) {
            if ($invoice->getTransactionId()) {
                $invoiceKey = $this->helper('klarna_kco')->__('Invoice ID (#%s)', $invoice->getIncrementId());
                $transport->setData($invoiceKey, $invoice->getTransactionId());
            }
        }

        return $transport;
    }

    /**
     * Check if string is a url
     *
     * @param $string
     * @return bool
     */
    public function isStringUrl($string)
    {
        return (bool)filter_var($string, FILTER_VALIDATE_URL);
    }

    /**
     * Return Kco payment method description
     *
     * @return string
     */
    public function getKcoPaymentMethodDescription()
    {
        $info = $this->getInfo();
        $klarnaPaymentMethodDescription = $info->getAdditionalInformation('klarna_payment_method_description');
        if ($klarnaPaymentMethodDescription) {
            return self::DEFAULT_TITLE . ' - ' . $klarnaPaymentMethodDescription;
        }

        return self::DEFAULT_TITLE;
    }
}
