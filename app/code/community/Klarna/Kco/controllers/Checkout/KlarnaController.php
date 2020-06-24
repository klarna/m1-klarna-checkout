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
 * Klarna checkout controller
 */
class Klarna_Kco_Checkout_KlarnaController extends Klarna_Kco_Controller_Klarna
{
    /**
     * Checkout page
     */
    public function indexAction()
    {
        if (!Mage::helper('klarna_kco')->kcoEnabled()) {
            $this->norouteAction();

            return;
        }

        $quote = $this->_getQuote();

        if (!$quote->hasItems() || $quote->getHasError()) {
            $this->_redirect('checkout/cart');

            return;
        }

        if (!$quote->validateMinimumAmount()) {
            $error = Mage::getStoreConfig('sales/minimum_order/error_message')
                ? Mage::getStoreConfig('sales/minimum_order/error_message')
                : Mage::helper('checkout')->__('Subtotal must exceed minimum order amount');

            Mage::getSingleton('checkout/session')->addError($error);
            $this->_redirect('checkout/cart');

            return;
        }

        Mage::getSingleton('checkout/session')->setCartWasUpdated(false);
        Mage::getSingleton('customer/session')->setBeforeAuthUrl(Mage::getUrl('*/*/*', array('_secure' => true)));
        try {
            $this->getKco()->initCheckout();
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('checkout/cart');

            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Klarna Checkout'));
        $this->renderLayout();
    }

    /**
     * Save customer details before address entry
     */
    public function saveCustomerAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        $result = array();

        try {
            $customerDetails = new Varien_Object($this->getRequest()->getParams());
            $quote           = $this->_getQuote();

            $quote->setCustomerEmail($customerDetails->getEmail());
            $quote->setCustomerFirstname($customerDetails->getGivenName());
            $quote->setCustomerLastname($customerDetails->getFamilyName());
            $quote->save();
        } catch (Exception $e) {
            Mage::logException($e);
        }

        /**
         * In this condition we can not check if we have a active klarna shipping gateway entry.
         * When having this check we wil never update the klarna order and therefore the kco event
         * "shipping_option_change" is always the preselected native shop shipping method.
         * In the end we will have a not ending loop in the frontend because it always thinks that the new shipping
         * method is the native one but this one is not displayed when using the KSS shipping methods
         */
        if ($quote !== null) {
            $this->getKco()->getQuote()->getShippingAddress()->collectShippingRates();
            $quote->collectTotals();
            $checkoutId = $this->getKco()->getKlarnaQuote()->getKlarnaCheckoutId();

            /**
             * We need to run this for the initial page loading when using a api shipping gateway.
             * In this case we make a request and get back the gateway methods.
             * After that we update the quote and our tax and shipping adjustment code logic run.
             */
            $oldKssCheck = $this->getKco()->hasActiveKlarnaShippingGateway();
            $this->getKco()->getApiInstance()->initKlarnaCheckout($checkoutId, false, true);

            /**
             * We clear the attribute for the case a fallback to the native shop shipping methods
             * happened and we want later get the latest state of shipping gateway model.
             */
            $this->getKco()->clearShippingGateway();

            $newKssCheck = $this->getKco()->hasActiveKlarnaShippingGateway();

            /**
             * We just need to execute a "extra" collecting and api request if there is a change
             * of a kss usage (switching from not kss not returned via the api to kss is returned from the
             * api and vice versa). In this way we ensure that we display the correct values (especially
             * for the case when after this request the reloadSummary request will happen)
             */
            if ($oldKssCheck !== $newKssCheck) {
                if ($newKssCheck) {
                    $quote->getShippingAddress()->setShippingMethod(Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY);
                }

                $this->getKco()->checkShippingMethod();
                $this->getKco()->getQuote()->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();

                $quote->collectTotals()->save();
                $this->getKco()->getApiInstance()->initKlarnaCheckout($checkoutId, false, true);
            }
        }

        $this->_getSummaryResponse($result);
    }

    /**
     * Save shipping address
     *
     * This method is used when backend callbacks are not supported in the Klarna market
     */
    public function saveShippingAddressAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        $result = array();
        $quote  = $this->_getQuote();

        try {
            $addressData = new Varien_Object($this->getRequest()->getParams());
            $addressData->setSameAsOther(1);

            // Update quote details
            $quote->setCustomerEmail($addressData->getEmail());
            $quote->setCustomerFirstname($addressData->getGivenName());
            $quote->setCustomerLastname($addressData->getFamilyName());
            $quote->save();

            // Update billing address
            $this->_updateOrderAddress($addressData, Mage_Sales_Model_Quote_Address::TYPE_BILLING);

            $this->getKco()->updateKlarnaTotals();
        } catch (Exception $e) {
            Mage::logException($e);
            $result['redirect_url'] = Mage::getUrl('checkout/cart');
        }

        $this->_getSummaryResponse($result);
    }

    /**
     * Shipping method save action
     *
     * This method is used when backend shipping method callbacks are not supported in the Klarna market
     */
    public function saveShippingMethodAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        if ($this->getRequest()->isPost()) {
            $shippingMethod = $this->getRequest()->getPost('shipping_method', '');

            $isShopShippingMethod = Mage::helper('klarna_kco/checkout')->isShopShippingMethod($shippingMethod);
            if (!$isShopShippingMethod) {
                $shippingMethod = Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY;
            }

            $result = array();

            try {
                $this->getKco()->saveShippingMethod($shippingMethod);
                $this->_getQuote()->collectTotals()->save();
                $this->getKco()->updateKlarnaTotals();
            } catch (Klarna_Kco_Model_Api_Exception $e) {
                Mage::logException($e);
                Mage::dispatchEvent(
                    'checkout_controller_onepage_save_shipping_method', array(
                    'request' => $this->getRequest(),
                    'quote'   => $this->_getQuote()
                    )
                );
                $this->_getQuote()->collectTotals();

                $result = array(
                    'error' => $e->getMessage()
                );
            } catch (Exception $e) {
                Mage::logException($e);

                $result = array(
                    'error' => Mage::helper('klarna_kco')->__('Unable to select shipping method. Please try again.')
                );
            }

            $this->_getSummaryResponse($result);
        }
    }

    /**
     * Save gift message
     */
    public function saveMessageAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        Mage::dispatchEvent(
            'kco_controller_save_giftmessage', array(
            'request' => $this->getRequest(),
            'quote'   => $this->_getQuote()
            )
        );

        $this->_getSummaryResponse();
    }

    /**
     * Manage cart coupon code
     */
    public function couponPostAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        $result = array();

        /**
         * No reason continue with empty shopping cart
         */
        if (!$this->_getCart()->getQuote()->getItemsCount()) {
            $this->_ajaxRedirect(Mage::getUrl('checkout/cart'));

            return;
        }

        $couponCode = (string)$this->getRequest()->getParam('coupon_code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }

        $oldCouponCode = $this->_getQuote()->getCouponCode();

        if (!empty($couponCode) && !empty($oldCouponCode)) {
            $this->_getSummaryResponse();
            return;
        }

        try {
            $codeLength        = strlen($couponCode);
            $isCodeLengthValid = $codeLength && $codeLength <= 255;

            $this->_getQuote()->getShippingAddress()->setCollectShippingRates(true);
            $this->_getQuote()->setCouponCode($isCodeLengthValid ? $couponCode : '')
                ->collectTotals()
                ->save();

            if ($codeLength) {
                if ($isCodeLengthValid && $couponCode == $this->_getQuote()->getCouponCode()) {
                    $result['success'] = $this->__(
                        'Coupon code "%s" was applied.', Mage::helper('core')
                        ->escapeHtml($couponCode)
                    );
                } else {
                    $result['error'] = $this->__(
                        'Coupon code "%s" is not valid.', Mage::helper('core')
                        ->escapeHtml($couponCode)
                    );
                }
            } else {
                $result['success'] = $this->__('Coupon code was canceled.');
            }

            $this->getKco()->updateKlarnaTotals();
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            $result['error'] = $e->getMessage();
        } catch (Exception $e) {
            $result['error'] = $this->__('Cannot apply the coupon code.');
            Mage::logException($e);
        }

        $this->_getSummaryResponse($result);
    }

    /**
     * Reload checkout details container
     */
    public function reloadSummaryAction()
    {
        if ($this->_expireAjax()) {
            return;
        }

        if ($this->getKco()->hasActiveKlarnaShippingGateway()) {
            $this->getKco()->updateKlarnaTotals();
            $this->getKco()->checkShippingMethod();
            $this->_getQuote()->setTotalsCollectedFlag(false);
            $this->_getQuote()->collectTotals();
        }

        $this->_getSummaryResponse();
    }

    /**
     * Failure action
     */
    public function failureAction()
    {
        $lastQuoteId = $this->getKco()->getCheckout()->getLastQuoteId();
        $lastOrderId = $this->getKco()->getCheckout()->getLastOrderId();

        if (!$lastQuoteId || !$lastOrderId) {
            $this->_redirect('checkout/cart');

            return;
        }

        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Send redirect ajax response
     *
     * @param string $redirect
     *
     * @return $this
     */
    protected function _ajaxRedirect($redirect)
    {
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode(
                array(
                'redirect' => $redirect
                )
            )
        );

        return $this;
    }

    /**
     * Get the response for the order summary
     *
     * @param array $result
     */
    protected function _getSummaryResponse($result = array())
    {
        if ($this->getRequest()->isXmlHttpRequest()) {
            $result['update_section'] = array(
                'name' => 'checkout_summary',
                'html' => $this->_getSummaryHtml()
            );

            $resultObject = new Varien_Object($result);
            Mage::dispatchEvent(
                'kco_summary_response', array(
                'controller'    => $this,
                'result_object' => $resultObject
                )
            );

            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($resultObject->toArray()));
        } else {
            $this->_redirectReferer();
        }
    }

    /**
     * Get the html of the checkout details summary
     */
    public function _getSummaryHtml()
    {
        $layout = $this->getLayout();
        $update = $layout->getUpdate();
        $update->load('checkout_klarna_summary');
        $layout->generateXml();
        $layout->generateBlocks();
        $output = $layout->getOutput();
        Mage::getSingleton('core/translate_inline')->processResponseBody($output);

        return $output;
    }

    /**
     * Validation of the order failed on order place
     *
     * @throws Exception
     */
    public function validateFailedAction()
    {
        $this->_getSession()->addError(
            Mage::helper('klarna_kco/checkout')->__('Unable to complete order. Please try again')
        );

        $this->_redirect('checkout/cart');
    }

    /**
     * Create order action when order is placed by Klarna
     * If you change code here, please review ApiController::createOrder to see if it needs to be changed also.
     */
    public function confirmationAction()
    {
        $checkoutId = $this->getRequest()->getParam('id');

        if (!$checkoutId) {
            $this->_redirect('checkout/cart');
        }

        $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadByCheckoutId($checkoutId);
        $quote       = Mage::getModel('sales/quote')->load($klarnaQuote->getQuoteId());
        $order       = null;

        $this->getKco()->setKlarnaQuote($klarnaQuote);

        if (!$quote->getId()) {
            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__('Unable to process order. Please try again')
            );

            $this->_redirect('checkout/cart');

            return;
        }

        $reservationId = null;

        try {
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder');
            $klarnaOrder->loadByCheckoutId($checkoutId);
            if ($klarnaOrder->getId()) {
                $this->_getSession()->addError(
                    Mage::helper('klarna_kco')->__('Order already exist.')
                );

                $this->_redirect('checkout/cart');
                return;
            }

            $checkout      = $this->getKco()->setQuote($quote)->getKlarnaCheckout();
            $reservationId = $this->getKco()->getApiInstance()->getReservationId();

            // Check if checkout is complete before placing the order
            if ($checkout->getStatus() != 'checkout_complete' && $checkout->getStatus() != 'created') {
                $this->_getSession()->addError(
                    Mage::helper('klarna_kco')->__('Unable to process order. Please try again')
                );

                $this->_redirect('checkout/cart');

                return;
            }

            // Make sure Magento Addresses match Klarna
            $this->_updateOrderAddresses($checkout);

            $quote->collectTotals();

            // Validate order totals
            $this->_validateOrderTotal($checkout, $quote);

            Mage::dispatchEvent(
                'kco_confirmation_create_order_before', array(
                'quote'           => $quote,
                'checkout'        => $checkout,
                'klarna_order_id' => $checkoutId,
                )
            );

            $order = $this->getKco()->setQuote($quote)->saveOrder();
            $quote->save();

            //handle recurring order
            if (null === $order) {
                $orderId = Mage::getSingleton('checkout/session')->getCurrentKcoRecurringOrderId();
                if ($orderId) {
                    $order = Mage::getModel('sales/order')->load($orderId);
                }
            }

            Mage::dispatchEvent(
                'kco_confirmation_create_order_after', array(
                'quote'           => $quote,
                'order'           => $order,
                'klarna_order_id' => $checkoutId,
                )
            );

            $klarnaOrder->setData(
                array(
                'klarna_checkout_id'    => $checkoutId,
                'klarna_reservation_id' => $reservationId,
                'order_id'              => $order->getId()
                )
            );
            $klarnaOrder->save();

            Mage::dispatchEvent(
                'kco_confirmation_create_order_success', array(
                'quote'           => $quote,
                'order'           => $order,
                'klarna_order'    => $klarnaOrder,
                'klarna_order_id' => $checkoutId,
                )
            );
        } catch (Klarna_Kco_Model_Api_Exception $e) {
            $this->_cancelFailedOrder($reservationId, null, $e->getMessage());
            Mage::logException($e);

            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__($e->getMessage())
            );

            $this->_redirect('checkout/cart');

            return;
        } catch (Exception $e) {
            $this->_cancelFailedOrder($reservationId, null, $e->getMessage());
            Mage::logException($e);
            Mage::dispatchEvent(
                'kco_confirmation_failed', array(
                'order'           => $order,
                'quote'           => $quote,
                'klarna_order_id' => $checkoutId,
                )
            );

            $this->_getSession()->addError(
                Mage::helper('klarna_kco')->__('Unable to complete order. Please try again')
            );

            $this->_redirect('checkout/cart');

            return;
        }

        $this->_redirect('checkout/klarna/success');
    }

    /**
     * Order success action
     */
    public function successAction()
    {
        $session = $this->getKco()->getCheckout();
        if (!$session->getLastSuccessQuoteId()) {
            $this->_redirect('checkout/cart');

            return;
        }

        $lastQuoteId = $session->getLastQuoteId();
        $lastOrderId = $session->getLastOrderId();
        if (!$lastQuoteId) {
            $this->_redirect('checkout/cart');

            return;
        }

        $session->clear();
        $this->loadLayout();
        $this->_initLayoutMessages('checkout/session');
        Mage::dispatchEvent('checkout_kco_controller_success_action', array('order_ids' => array($lastOrderId)));
        Mage::dispatchEvent('checkout_onepage_controller_success_action', array('order_ids' => array($lastOrderId)));
        $this->renderLayout();
    }

    /**
     * Validation before dispatching controller action
     *
     * @return Klarna_Kco_Checkout_KlarnaController
     */
    public function preDispatch()
    {
        parent::preDispatch();
        $this->_preDispatchValidateCustomer();

        $checkoutSessionQuote = Mage::getSingleton('checkout/session')->getQuote();
        if ($checkoutSessionQuote->getIsMultiShipping()) {
            $checkoutSessionQuote->setIsMultiShipping(false);
            $checkoutSessionQuote->removeAllAddresses();
        }

        if (!$this->_canShowForUnregisteredUsers()) {
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);

            return $this;
        }

        return $this;
    }

    /**
     * Send Ajax redirect response
     *
     * @return Mage_Checkout_OnepageController
     */
    protected function _ajaxRedirectResponse()
    {
        $this->getResponse()
            ->setHeader('HTTP/1.1', '403 Session Expired')
            ->setHeader('Login-Required', 'true')
            ->sendResponse();

        return $this;
    }

    /**
     * Validate ajax request and redirect on failure
     *
     * @return bool
     */
    protected function _expireAjax()
    {
        if (!$this->_getQuote()->hasItems()
            || $this->_getQuote()->getHasError()
            || $this->_getQuote()->getIsMultiShipping()
        ) {
            $this->_ajaxRedirectResponse();

            return true;
        }

        $action                = $this->getRequest()->getActionName();
        $ignoredExpiredActions = new Varien_Object(array('index'));

        Mage::dispatchEvent(
            'checkout_kco_ignored_expired_action', array(
            'ignored_expired_actions' => $ignoredExpiredActions
            )
        );

        if (Mage::getSingleton('checkout/session')->getCartWasUpdated(true)
            && !in_array($action, $ignoredExpiredActions->toArray())
        ) {
            $this->_ajaxRedirectResponse();

            return true;
        }

        return false;
    }

    /**
     * Retrieve shopping cart model object
     *
     * @return Mage_Checkout_Model_Cart
     */
    protected function _getCart()
    {
        return Mage::getSingleton('checkout/cart');
    }

    /**
     * Get current customer quote
     *
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote()
    {
        return $this->_getCart()->getQuote();
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Check can page show for unregistered users
     *
     * @return boolean
     */
    protected function _canShowForUnregisteredUsers()
    {
        return Mage::getSingleton('customer/session')->isLoggedIn()
        || $this->getRequest()->getActionName() == 'index'
        || Mage::helper('klarna_kco/checkout')->isAllowedGuestCheckout($this->_getQuote())
        || !Mage::helper('checkout')->isCustomerMustBeLogged();
    }
}
