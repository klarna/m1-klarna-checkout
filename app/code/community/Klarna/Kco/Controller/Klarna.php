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
 * Klarna checkout abstract checkout controller
 */
abstract class Klarna_Kco_Controller_Klarna extends Mage_Checkout_Controller_Action
{
    /**
     * Update addresses on an order using api data
     *
     * @param Varien_Object $checkout
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     */
    protected function _updateOrderAddresses($checkout)
    {
        // we need to collect them here because there are scenarios where we use a the old state of the quote
        $this->getKco()->getQuote()->collectTotals();

        if ($checkout->hasCustomer() || $checkout->hasBillingAddress() || $checkout->hasShippingAddress()) {
            try {
                $updateErrors = array();
                $customer = new Varien_Object($checkout->getCustomer());
                $billingAddress = new Varien_Object($checkout->getBillingAddress());
                $shippingAddress = new Varien_Object($checkout->getShippingAddress());
                $sameAsOther = false;

                if ($checkout->hasBillingAddress() && $checkout->hasShippingAddress()) {
                    $sameAsOther = $checkout->getShippingAddress() == $checkout->getBillingAddress();
                    if ($this->getKco()->hasActiveKlarnaShippingGateway()) {
                        $sameAsOther = false;
                        $shippingAddress = $this->_addKssAddressToShippingAddress($checkout, $shippingAddress);
                    }
                }

                if ($checkout->hasCustomer()) {
                    if ($customer->hasDateOfBirth()) {
                        $this->getKco()
                             ->getQuote()
                             ->setCustomerDob(
                                 Mage::app()
                                     ->getLocale()
                                     ->date($customer->getDateOfBirth(), null, null, false)
                                     ->toString(Zend_Date::DATE_MEDIUM)
                             );
                    }

                    if ($customer->hasGender()) {
                        $maleId = $femaleId = false;
                        $options = Mage::getResourceSingleton('customer/customer')
                                       ->getAttribute('gender')
                                       ->getSource()
                                       ->getAllOptions(false);

                        foreach ($options as $option) {
                            switch (strtolower($option['label'])) {
                                case 'male':
                                    $maleId = $option['value'];
                                    break;
                                case 'female':
                                    $femaleId = $option['value'];
                                    break;
                            }
                        }

                        switch ($customer->getGender()) {
                            case 'male':
                                if (false !== $maleId) {
                                    $this->getKco()->getQuote()->setCustomerGender($maleId);
                                }
                                break;
                            case 'female':
                                if (false !== $femaleId) {
                                    $this->getKco()->getQuote()->setCustomerGender($femaleId);
                                }
                                break;
                        }
                    }
                }

                if ($checkout->hasBillingAddress()) {
                    $billingAddress->setSameAsOther($sameAsOther);

                    // Update quote details
                    $this->getKco()->getQuote()->setCustomerEmail($billingAddress->getEmail());
                    $this->getKco()->getQuote()->setCustomerFirstname($billingAddress->getGivenName());
                    $this->getKco()->getQuote()->setCustomerLastname($billingAddress->getFamilyName());

                    if ($this->getKco()->getQuote()->getCustomerGender()) {
                        $billingAddress->setGender($this->getKco()->getQuote()->getCustomerGender());
                    }

                    if ($this->getKco()->getQuote()->getCustomerDob()) {
                        $billingAddress->setDob($this->getKco()->getQuote()->getCustomerDob());
                    }

                    // Update billing address
                    try {
                        $this->_updateOrderAddress(
                            $billingAddress,
                            Mage_Sales_Model_Quote_Address::TYPE_BILLING,
                            false
                        );
                    } catch (Klarna_Kco_Exception $e) {
                        $updateErrors[] = $e->getMessage();
                    }
                }

                if ($checkout->hasShippingAddress() & !$sameAsOther) {
                    $this->getKco()->getQuote()->setTotalsCollectedFlag(false);

                    // Update shipping address
                    try {
                        $this->_updateOrderAddress(
                            $shippingAddress,
                            Mage_Sales_Model_Quote_Address::TYPE_SHIPPING,
                            false
                        );
                    } catch (Klarna_Kco_Exception $e) {
                        $updateErrors[] = $e->getMessage();
                    }
                }

                if (!empty($updateErrors)) {
                    $prettyErrors = implode("\n", $updateErrors);
                    $prettyJson = json_encode($checkout->toArray(), JSON_PRETTY_PRINT);

                    Mage::log(
                        "$prettyErrors\n$prettyJson\n",
                        Zend_Log::ALERT,
                        'klarna_shipping_address_update_error.log',
                        true
                    );

                    $this->_sendBadAddressRequestResponse($updateErrors);

                    throw new Klarna_Kco_Exception('Shipping address update error');
                }

                $this->getKco()->checkShippingMethod();

                $this->getKco()->getQuote()->collectTotals()->save();
            } catch (Klarna_Kco_Exception $e) {
                throw $e;
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return $this;
    }

    /**
     * Adding the kss pickup location address to the shipping address.
     * When no pickup location is chosen (for example "To Door Delivery") then we don't change the
     * shipping address since the correct values are already added to it before
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Response|Varien_Object $checkout
     * @param Varien_Object $shippingAddress
     * @return Varien_Object
     */
    protected function _addKssAddressToShippingAddress($checkout, $shippingAddress)
    {
        $shippingOption = $checkout->getSelectedShippingOption();
        if (isset($shippingOption['delivery_details']['pickup_location'])) {
            $pickupAddress = $shippingOption['delivery_details']['pickup_location']['address'];
            $shippingAddress->setStreetAddress($pickupAddress['street_address']);
            $shippingAddress->setPostalCode($pickupAddress['postal_code']);
            $shippingAddress->setCity($pickupAddress['city']);
            $shippingAddress->setCountry($pickupAddress['country']);
        }

        return $shippingAddress;
    }

    /**
     * Get kco checkout model
     *
     * @return Klarna_Kco_Model_Checkout_Type_Kco
     */
    public function getKco()
    {
        return Mage::getSingleton('klarna_kco/checkout_type_kco');
    }

    /**
     * Update quote address using address details from api call
     *
     * @param Varien_Object $klarnaAddressData
     * @param string        $type
     * @param bool          $saveQuote
     *
     * @throws Klarna_Kco_Exception
     */
    protected function _updateOrderAddress(
        $klarnaAddressData,
        $type = Mage_Sales_Model_Quote_Address::TYPE_BILLING,
        $saveQuote = true
    ) {
        Mage::helper('klarna_kco/checkout')->updateKcoCheckoutAddress($klarnaAddressData, $type, $saveQuote);
    }

    /**
     * Send bad address validation response message
     *
     * @param string $message
     *
     * @throws Zend_Controller_Response_Exception
     */
    protected function _sendBadAddressRequestResponse($message = null)
    {
        if (null === $message) {
            $message = Mage::helper('klarna_kco')->__('Bad request');
        }

        if (is_array($message)) {
            $message = implode('\n', $message);
        }

        $message = nl2br($message);

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode(400);
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode(
                array(
                    'error_type' => 'address_update',
                    'error_text' => $message
                )
            )
        );
    }

    /**
     * Verify order totals match with Klarna and Magento
     *
     * @param Varien_Object          $checkout
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return $this
     *
     * @throws Klarna_Kco_Exception
     */
    protected function _validateOrderTotal($checkout, $quote)
    {
        $helper = Mage::helper('klarna_kco/checkout');
        $klarnaTotal = $checkout->getOrderAmount() ?: $checkout->getData('cart/total_price_including_tax');
        //load quote from db
        $quoteFromDb = Mage::getModel('sales/quote')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($quote->getId());
        $quoteTotal = $helper->toApiFloat($quoteFromDb->getGrandTotal());

        Mage::dispatchEvent(
            'kco_confirmation_order_total_validation', array(
                'checkout' => $checkout,
                'quote'    => $quote
            )
        );

        $difference = abs($klarnaTotal - $quoteTotal);

        if ($difference > 2 && !$quote->hasRecurringItems()) {
            $exceptionMessage =
                $helper->__(
                    'Order total does not match for order #%s. Klarna total is %s vs Magento total %s',
                    $quote->getReservedOrderId(), $klarnaTotal, $quoteTotal
                );
            throw new Klarna_Kco_Exception($exceptionMessage);
        }

        return $this;
    }

    /**
     * Cancel a failed order in Klarna
     *
     * @param string                $reservationId
     * @param Mage_Core_Model_Store $store
     * @param string                $message
     *
     * @return $this
     */
    protected function _cancelFailedOrder($reservationId, $store = null, $message = 'Unkown Error')
    {
        if (null === $reservationId) {
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

        Mage::helper('checkout')->sendPaymentFailedEmail($this->getKco()->getQuote(), $message);

        return $this;
    }

    /**
     * Send bad request response header
     *
     * @param array|string|null $message
     *
     * @throws Zend_Controller_Response_Exception
     */
    protected function _sendBadRequestResponse($message = null)
    {
        if (null === $message) {
            $message = Mage::helper('klarna_kco')->__('Bad request');
        }

        if (is_array($message)) {
            $message = implode('\n', $message);
        }

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setHttpResponseCode(400);
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode(
                array(
                    'error_type' => 'address_error',
                    'error_text' => $message
                )
            )
        );
    }
}
