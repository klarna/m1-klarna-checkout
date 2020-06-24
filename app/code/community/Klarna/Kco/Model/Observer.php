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
 * Klarna checkout events
 */
class Klarna_Kco_Model_Observer
{
    /**
     * Load Klarna checkout instead of standard checkout
     *
     * @param Varien_Event_Observer $observer
     */
    public function loadKlarnaCheckout(Varien_Event_Observer $observer)
    {
        $overrideObject = new Varien_Object(
            array(
                'force_disabled' => false,
                'force_enabled'  => false,
                'redirect_url'   => Mage::getUrl('checkout/klarna')
            )
        );

        Mage::dispatchEvent(
            'kco_override_load_checkout',
            array(
                'override_object' => $overrideObject,
                'parent_observer' => $observer
            )
        );

        if ($overrideObject->getForceEnabled()
            || (!$overrideObject->getForceDisabled()
                && !$this->_getCheckoutSession()
                    ->getKlarnaOverride()
                && Mage::helper('klarna_kco')->kcoEnabled())
        ) {
            $observer->getControllerAction()
                ->getResponse()
                ->setRedirect($overrideObject->getRedirectUrl())
                ->sendResponse();
        }
    }

    /**
     * Check if a quote has changed for a Klarna order and mark it as changed
     *
     * @param Varien_Event_Observer $observer
     */
    public function kcoCheckIfQuoteHasChanged(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote         = $observer->getQuote();
        $paymentMethod = $quote->getPayment()->getMethod();

        if (Mage::getSingleton('checkout/session')->getCartWasUpdated() && Mage::helper('klarna_kco')->kcoEnabled()
            && $paymentMethod == 'klarna_kco'
        ) {
            $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadActiveByQuote($quote);
            if ($klarnaQuote->getId() && !$klarnaQuote->getIsChanged()) {
                $klarnaQuote->setIsChanged(1);
                $klarnaQuote->save();
            }
        }
    }

    /**
     * Generate item list for payment capture
     *
     * @param Varien_Event_Observer $observer
     */
    public function prepareCapture(Varien_Event_Observer $observer)
    {
        $payment = $observer->getPayment();

        if ($payment->getMethod() != 'klarna_kco') {
            return;
        }

        $payment->setInvoice($observer->getInvoice());
    }

    /**
     * Set additional payment details on an order during the push notification
     *
     * @param Varien_Event_Observer $observer
     */
    public function kcoAddOrderDetailsOnPush(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getOrder();
        /** @var Klarna_Kco_Model_Klarnaorder $klarnaOrder */
        $klarnaOrder = $observer->getKlarnaOrder();
        /** @var Klarna_Kco_Model_Api_Response $klarnaOrderDetails */
        $klarnaOrderDetails = Mage::helper('klarna_kco')
            ->getApiInstance($order->getStore())
            ->getPlacedKlarnaOrder($klarnaOrder->getKlarnaCheckoutId());

        // Add invoice to order details
        if ($klarnaReference = $klarnaOrderDetails->getKlarnaReference()) {
            $order->getPayment()->setAdditionalInformation('klarna_reference', $klarnaReference);
        }

        // add initial payment info to order detail
        $initialPaymentInfo = $klarnaOrderDetails->getInitialPaymentMethod();
        if ($initialPaymentInfo && Mage::helper('klarna_kco')->isKcoPaymentInfoValid($initialPaymentInfo)) {
            $order->getPayment()->setAdditionalInformation(
                'klarna_payment_method_type',
                $initialPaymentInfo['type']
            );
            $order->getPayment()->setAdditionalInformation(
                'klarna_payment_method_description',
                $initialPaymentInfo['description']
            );
        }
    }

    /**
     * Sign a user up to merchant checkbox when they check the box
     *
     * @param Varien_Event_Observer $observer
     */
    public function merchantCheckboxNewsletterSignup(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getQuote();
        if ($observer->getChecked() && ($email = ($quote->getCustomerEmail() ?: $quote->getCustomer()->getEmail()))) {
            $session         = Mage::getSingleton('core/session');
            $customerSession = Mage::getSingleton('customer/session');
            $helper          = Mage::helper('klarna_kco');

            try {
                if (!Zend_Validate::is($email, 'EmailAddress')) {
                    Mage::throwException($helper->__('Please enter a valid email address.'));
                }

                if (Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG) != 1
                    && !$customerSession->isLoggedIn()
                ) {
                    Mage::throwException(
                        $helper->__(
                            'Sorry, but administrator denied subscription for guests. Please <a href="%s">register</a>.',
                            Mage::helper('customer')->getRegisterUrl()
                        )
                    );
                }

                $ownerId = Mage::getModel('customer/customer')
                    ->setWebsiteId(Mage::app()->getStore()->getWebsiteId())
                    ->loadByEmail($email)
                    ->getId();
                if ($ownerId !== null && $ownerId != $customerSession->getId()) {
                    Mage::throwException($helper->__('This email address is already assigned to another user.'));
                }

                $status = Mage::getModel('newsletter/subscriber')->subscribe($email);
                if ($status == Mage_Newsletter_Model_Subscriber::STATUS_NOT_ACTIVE) {
                    $session->addSuccess($helper->__('Confirmation request has been sent.'));
                } else {
                    $session->addSuccess($helper->__('Thank you for your subscription.'));
                }
            } catch (Mage_Core_Exception $e) {
                Mage::logException($e);
                $session->addException(
                    $e,
                    $helper->__('There was a problem with the subscription: %s', $e->getMessage())
                );
            } catch (Exception $e) {
                Mage::logException($e);
                $session->addException($e, $helper->__('There was a problem with the subscription.'));
            }
        }
    }

    /**
     * Validate the merchant checkbox should display for newsletter signup
     *
     * @param Varien_Event_Observer $observer
     */
    public function merchantCheckboxNewsletterSignupValidation(Varien_Event_Observer $observer)
    {
        $customerSession = Mage::getSingleton('customer/session');
        if ((Mage::getStoreConfig(Mage_Newsletter_Model_Subscriber::XML_PATH_ALLOW_GUEST_SUBSCRIBE_FLAG) != 1
                && !$customerSession->isLoggedIn())
            || !Mage::helper('core')->isModuleOutputEnabled('Mage_Newsletter')
        ) {
            $observer->setEnabled(false);

            return;
        }

        /** @var Mage_Sales_Model_Quote $quote */
        $quote         = $observer->getQuote();
        $customerEmail = $quote->getCustomerEmail() ?: $quote->getCustomer()->getEmail();
        $newsLetter    = Mage::getModel('newsletter/subscriber')->loadByEmail($customerEmail);

        $observer->setEnabled(!$newsLetter->isSubscribed());
    }

    /**
     * Register a new user when they check the box
     *
     * @param Varien_Event_Observer $observer
     */
    public function merchantCheckboxCreateAccount(Varien_Event_Observer $observer)
    {
        if ($observer->getChecked() && Mage::helper('customer')->isRegistrationAllowed()) {
            /** @var Mage_Sales_Model_Quote $quote */
            $quote = $observer->getQuote();

            if ($quote->getCustomerId() || $this->_checkIfObjectCustomerAlreadyExist($quote)) {
                return;
            }

            $customer = $quote->getCustomer();
            $password = $customer->generatePassword();
            $customer->setPassword($password);
            $customer->setPasswordConfirmation($password);
            $customer->setConfirmation($password);

            $quote->setPasswordHash($customer->encryptPassword($customer->getPassword()));
            $quote->setCheckoutMethod(Klarna_Kco_Model_Checkout_Type_Kco::METHOD_REGISTER);
        }
    }

    /**
     * Validate the merchant checkbox should display for user signup
     *
     * @param Varien_Event_Observer $observer
     */
    public function merchantCheckboxCreateAccountValidation(Varien_Event_Observer $observer)
    {
        $customerExist = $this->_checkIfObjectCustomerAlreadyExist($observer->getQuote());
        $enabled       = !$customerExist && Mage::helper('customer')->isRegistrationAllowed();
        $observer->setEnabled($enabled);
    }

    /**
     * Check if a guest checkout user already has an account. Register the order with that customer.
     *
     * @param Varien_Event_Observer $observer
     */
    public function associateGuestOrderWithRegisteredCustomer(Varien_Event_Observer $observer)
    {
        /** @var Mage_Sales_Model_Quote $quote */
        $quote = $observer->getCheckout()->getQuote();

        if ($quote->getCustomerId()) {
            return;
        }

        $customer = $this->_getObjectCustomer($quote);

        if (!$customer->getId()) {
            return;
        }

        $quote->setCustomer($customer)
            ->setCheckoutMethod(Klarna_Kco_Model_Checkout_Type_Kco::METHOD_CUSTOMER);
    }

    /**
     * Check if an objects customer already exist
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function _getObjectCustomer($object)
    {
        return Mage::getModel('customer/customer')
            ->setWebsiteId($object->getStore()->getWebsiteId())
            ->loadByEmail($object->getCustomerEmail());
    }

    /**
     * Determine if a customer already exist on an order or quote
     *
     * @param Mage_Sales_Model_Quote|Mage_Sales_Model_Order $object
     *
     * @return bool
     */
    protected function _checkIfObjectCustomerAlreadyExist($object)
    {
        if (!$object->getCustomerEmail()) {
            return false;
        }

        $customer = $this->_getObjectCustomer($object);

        return (bool)$customer->getId();
    }

    /**
     * Get customer checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function adminhtmlWidgetContainerHtmlBefore(Varien_Event_Observer $observer)
    {
        $block = $observer->getBlock();
        $order = Mage::registry('current_order');

        if ($order instanceof Mage_Sales_Model_Order && 'klarna_kco' == $order->getPayment()->getMethod()
            && Mage::helper('klarna_kco/checkout')->getMotoEnabled($order->getStore())
            && Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW == $order->getState()
        ) {
            $klarnaOrder = Mage::getModel('klarna_kco/klarnaorder')->loadByOrder($order);
            if (!$klarnaOrder->getId()) {
                if ($block instanceof Mage_Adminhtml_Block_Sales_Order_View) {
                    $block->addButton(
                        'pay_with_klarna',
                        array(
                            'label'   => Mage::helper('klarna_kco')->__('Pay with Klarna'),
                            'onclick' => 'setLocation(\'' . $block->getUrl('*/klarna') . '\')',
                            'class'   => 'go'
                        )
                    );
                }
            }
        }
    }

    /**
     * Handle if a user accepts pre-fill terms
     *
     * @param Varien_Event_Observer $observer
     */
    public function preFillNotice(Varien_Event_Observer $observer)
    {
        /** @var Klarna_Kco_Model_Api_Builder_Abstract $builder */
        $builder         = $observer->getBuilder();
        $create          = $builder->getRequest();
        $checkoutSession = Mage::helper('checkout')->getCheckout();

        if ('accept' != $checkoutSession->getKlarnaFillNoticeTerms()
            && Mage::helper('klarna_kco/dach')->isPrefillNoticeEnabled($builder->getObject()->getStore())
        ) {
            unset($create['customer']);
            unset($create['billing_address']);
            unset($create['shipping_address']);
            $observer->getBuilder()->setRequest($create);
        }
    }


    /**
     * Check if the pre-fill notice has been accepted
     *
     * @param Varien_Event_Observer $observer
     */
    public function preFillNoticeCheckAccept(Varien_Event_Observer $observer)
    {
        /** @var Mage_Core_Controller_Varien_Action $controllerAction */
        $controllerAction = $observer->getControllerAction();
        $termsParam       = $controllerAction->getRequest()->getParam('terms');
        $checkoutSession  = Mage::helper('checkout')->getCheckout();

        if ($termsParam) {
            $checkoutSession->setKlarnaFillNoticeTerms($termsParam);
        }

        if ('accept' == $termsParam) {
            $quote       = Mage::helper('checkout')->getQuote();
            $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadActiveByQuote($quote);

            if ($klarnaQuote->getId()) {
                $klarnaQuote->setIsActive(0);
                $klarnaQuote->save();
            }

            $controllerAction->setRedirectWithCookieCheck('checkout/klarna');
            $controllerAction->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        }
    }

    /**
     * Save care of on an order with packstation
     *
     * @param Varien_Event_Observer $observer
     */
    public function orderConfirmationPackstationSave(Varien_Event_Observer $observer)
    {
        $checkout = $observer->getCheckout();
        $helper = Mage::helper('klarna_kco/dach');
        $checkoutHelper = Mage::helper('klarna_kco/checkout');
        if (!$observer->getQuote()->isVirtual()) {
            $careOfShipping = $checkout->getData('shipping_address/care_of');
            if ($careOfShipping) {
                $shippingAddress = new Varien_Object($checkout->getShippingAddress());
                $shippingAddress->setStreetAddress2($helper->__('C/O ') . $careOfShipping);
                $checkoutHelper->updateKcoCheckoutAddress(
                    $shippingAddress,
                    Mage_Sales_Model_Quote_Address::TYPE_SHIPPING
                );
            }
        }

        $careOfBilling = $checkout->getData('billing_address/care_of');
        if ($careOfBilling) {
            $billingAddress = new Varien_Object($checkout->getBillingAddress());
            $billingAddress->setStreetAddress2($helper->__('C/O ') . $careOfBilling);
            $checkoutHelper->updateKcoCheckoutAddress($billingAddress, Mage_Sales_Model_Quote_Address::TYPE_BILLING);
        }
    }



    /**
     * Download and store Kred invoices
     *
     * @param Varien_Event_Observer $observer
     *
     * @return $this
     */
    public function downloadAndStoreInvoice(Varien_Event_Observer $observer)
    {
        if (!Mage::getStoreConfigFlag('checkout/klarna_kco/invoice_download')) {
            return;
        }

        try {
            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $observer->getPayment();

            if ($payment->getMethod() != 'klarna_kco') {
                return $this;
            }

            $invoiceId    = $payment->getTransactionId();
            $store        = $payment->getOrder()->getStore();
            $mid          = Mage::getStoreConfig('payment/klarna_kco/merchant_id', $store);
            $sharedSecret = Mage::getStoreConfig('payment/klarna_kco/shared_secret', $store);

            $authHash = urlencode(base64_encode(hash('sha512', sprintf('%s:%s:%s', $mid, $invoiceId, $sharedSecret), true)));

            if ($invoiceId
                && 'kred' == Mage::helper('klarna_kco/checkout')->getCheckoutType($store)
            ) {
                $invoiceUrl    = Mage::getStoreConfigFlag('payment/klarna_kco/test_mode', $store)
                    ? sprintf('https://online.testdrive.klarna.com/invoices/%s.pdf?secret=%s', $invoiceId, $authHash)
                    : sprintf('https://online.klarna.com/invoices/%s.pdf?secret=%s', $invoiceId, $authHash);
                $saveDirectory = Mage::getConfig()->getVarDir() . DS . 'klarnainvoices';

                if (!Mage::getConfig()->createDirIfNotExists($saveDirectory)) {
                    Mage::throwException(sprintf('Unable to create Klarna invoice directory "%s"', $saveDirectory));
                }

                $client = new Zend_Http_Client(
                    $invoiceUrl, array(
                        'keepalive' => true
                    )
                );

                $client->setStream();
                $response = $client->request('GET');
                if ($response->isSuccessful()) {
                    copy($response->getStreamName(), $saveDirectory . DS . $invoiceId . '.pdf');
                } else {
                    Mage::throwException(
                        sprintf(
                            'Header status "%s" when attempted to download invoice pdf #%s for order #%s.',
                            $response->getStatus(), $invoiceId, $payment->getOrder()->getIncrementId()
                        )
                    );
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
            throw $e;
        }

        return $this;
    }
}
