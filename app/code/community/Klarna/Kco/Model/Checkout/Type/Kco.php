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
 * Klarna checkout model
 */
class Klarna_Kco_Model_Checkout_Type_Kco
{
    /**
     * Checkout types: Checkout as Guest, Register, Logged In Customer
     */
    const METHOD_GUEST    = 'guest';
    const METHOD_REGISTER = 'register';
    const METHOD_CUSTOMER = 'customer';

    /**
     * Error message of "customer already exists"
     *
     * @var string
     */
    private $_customerEmailExistsMessage = '';

    /**
     * @var Mage_Customer_Model_Session
     */
    protected $_customerSession;

    /**
     * @var Mage_Checkout_Model_Session
     */
    protected $_checkoutSession;

    /**
     * @var Klarna_Kco_Model_Api_Abstract
     */
    protected $_apiInstance;

    /**
     * @var Varien_Object
     */
    protected $_klarnaCheckout;

    /**
     * @var Klarna_Kco_Model_Klarnaquote
     */
    protected $_klarnaQuote;

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Klarna_Kco_Helper_Checkout
     */
    protected $_helper;

    /** @var $_shippingGateway Klarna_Kco_Model_Klarnashippingmethodgateway */
    private $_shippingGateway = null;

    /**
     * Class constructor
     * Set customer already exists message
     */
    public function __construct()
    {
        $this->_helper                     = Mage::helper('klarna_kco/checkout');
        $this->_customerEmailExistsMessage = Mage::helper('klarna_kco')
            ->__('There is already a customer registered using this email address. Please login using this email address or enter a different email address to register your account.');
        $this->_checkoutSession            = Mage::getSingleton('checkout/session');
        $this->_customerSession            = Mage::getSingleton('customer/session');
        $this->_apiInstance                = Mage::helper('klarna_kco')->getApiInstance();
    }

    /**
     * Get api instance
     *
     * @return Klarna_Kco_Model_Api_Abstract
     */
    public function getApiInstance()
    {
        return $this->_apiInstance;
    }

    /**
     * Get frontend checkout session object
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return $this->_checkoutSession;
    }

    /**
     * Quote object getter
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        if ($this->_quote === null) {
            return $this->_checkoutSession->getQuote();
        }

        return $this->_quote;
    }

    /**
     * Declare checkout quote instance
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return $this
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;

        return $this;
    }

    /**
     * Get customer session object
     *
     * @return Mage_Customer_Model_Session
     */
    public function getCustomerSession()
    {
        return $this->_customerSession;
    }

    /**
     * If checkout is allowed for the current customer
     *
     * Checks if guest checkout is allowed and if the customer is a guest or not
     *
     * @return bool
     */
    public function allowCheckout()
    {
        if ($this->getQuote()->hasRecurringItems() && $this->_reurringOrderNotAllowed()) {
            return false;
        }

        return Mage::helper('customer')->isLoggedIn()
            || (Mage::helper('klarna_kco/checkout')->isAllowedGuestCheckout($this->getQuote())
                && !Mage::helper('customer')->isLoggedIn());
    }

    /**
     * @return bool
     */
    protected function _reurringOrderNotAllowed()
    {
        $version = Mage::getStoreConfig('payment/klarna_kco/api_version', $this->getQuote()->getStore());
        return  $version !== 'nortic' ? true : false;
    }

    /**
     * Initialize quote state to be valid for one page checkout
     *
     * @return $this
     */
    public function initCheckout()
    {
        $customerSession = $this->getCustomerSession();

        /**
         * Reset multishipping flag before any manipulations with quote address
         * addAddress method for quote object related on this flag
         */
        if ($this->getQuote()->getIsMultiShipping()) {
            $this->getQuote()->setIsMultiShipping(false);
            $this->getQuote()->save();
        }

        /*
        * want to load the correct customer information by assigning to address
        * instead of just loading from sales/quote_address
        */
        $customer = $customerSession->getCustomer();
        if ($customer) {
            $this->getQuote()->assignCustomer($customer);
        }

        $this->checkShippingMethod();
        if (!$this->getQuote()->isVirtual()) {
            $this->getQuote()->getShippingAddress()->setCollectShippingRates(true);
        }

        $this->savePayment();

        if ($this->allowCheckout()) {
            try {
                $this->_initKlarnaCheckout();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return $this;
    }

    /**
     * Initialize Klarna checkout
     *
     * Will create or update the checkout order in the Klarna API
     *
     * @param bool $createIfNotExists
     * @param bool $updateItems
     *
     * @return Varien_Object
     */
    protected function _initKlarnaCheckout($createIfNotExists = true, $updateItems = true)
    {
        $klarnaCheckoutId      = $this->getKlarnaQuote()->getKlarnaCheckoutId();
        $this->_klarnaCheckout = $this->getApiInstance()
            ->initKlarnaCheckout($klarnaCheckoutId, $createIfNotExists, $updateItems);

        $this->setKlarnaQuoteKlarnaCheckoutId($this->_klarnaCheckout->getId());

        if ($createIfNotExists || $updateItems) {
            $this->getKlarnaQuote()->setIsChanged(0)->save();
        }

        return $this->_klarnaCheckout;
    }

    /**
     * Get Klarnaquote object based off current checkout quote
     *
     * @return Klarna_Kco_Model_Klarnaquote
     */
    public function getKlarnaQuote()
    {
        if (null === $this->_klarnaQuote) {
            $this->_klarnaQuote = Mage::getModel('klarna_kco/klarnaquote')->loadActiveByQuote($this->getQuote());
        }

        return $this->_klarnaQuote;
    }

    /**
     * Set Klarnaquote object
     *
     * @param Klarna_Kco_Model_Klarnaquote $klarnaQuote
     *
     * @return $this
     */
    public function setKlarnaQuote($klarnaQuote)
    {
        $this->_klarnaQuote = $klarnaQuote;

        return $this;
    }

    /**
     * Set the Klarna checkout id
     *
     * @param string $klarnaCheckoutId
     *
     * @return $this
     */
    public function setKlarnaQuoteKlarnaCheckoutId($klarnaCheckoutId)
    {
        $klarnaCheckoutId = trim($klarnaCheckoutId);

        if ('' == $klarnaCheckoutId) {
            return $this;
        }

        $klarnaQuote = $this->getKlarnaQuote();

        if ($klarnaQuote->getId()) {
            if ($klarnaQuote->getKlarnaCheckoutId() != $klarnaCheckoutId) {
                $klarnaQuote->setIsActive(0);
                $klarnaQuote->save();

                $klarnaQuote = $this->_createNewKlarnaQuote($klarnaCheckoutId);
            }
        } else {
            $klarnaQuote = $this->_createNewKlarnaQuote($klarnaCheckoutId);
        }

        $this->setKlarnaQuote($klarnaQuote);

        return $this;
    }

    /**
     * Create a new klarna quote object
     *
     * @param $klarnaCheckoutId
     *
     * @return Klarna_Kco_Model_Klarnaquote
     * @throws Exception
     */
    protected function _createNewKlarnaQuote($klarnaCheckoutId)
    {
        $klarnaQuote = Mage::getModel('klarna_kco/klarnaquote');
        $klarnaQuote->setData(
            array(
            'klarna_checkout_id' => $klarnaCheckoutId,
            'is_active'          => 1,
            'quote_id'           => $this->getQuote()->getId(),
            )
        );
        $klarnaQuote->save();

        return $klarnaQuote;
    }

    /**
     * Get current Klarna checkout object
     *
     * @return Varien_Object
     */
    public function getKlarnaCheckout()
    {
        if (null === $this->_klarnaCheckout) {
            $this->_initKlarnaCheckout(false, false);
        }

        return $this->_klarnaCheckout;
    }

    /**
     * Update state of cart to Klarna
     *
     * @return $this
     */
    public function updateKlarnaTotals()
    {
        $this->_initKlarnaCheckout(false);

        return $this;
    }

    /**
     * Get quote checkout method
     *
     * @return string
     */
    public function getCheckoutMethod()
    {
        if ($this->getCustomerSession()->isLoggedIn()) {
            return self::METHOD_CUSTOMER;
        }

        if (!$this->getQuote()->getCheckoutMethod()) {
            if ($this->_helper->isAllowedGuestCheckout($this->getQuote())) {
                $this->getQuote()->setCheckoutMethod(self::METHOD_GUEST);
            } else {
                $this->getQuote()->setCheckoutMethod(self::METHOD_REGISTER);
            }
        }

        return $this->getQuote()->getCheckoutMethod();
    }

    /**
     * Specify checkout method
     *
     * @param   string $method
     *
     * @return bool
     * @throws Klarna_Kco_Exception
     */
    public function saveCheckoutMethod($method)
    {
        if (empty($method)) {
            throw new Klarna_Kco_Exception($this->_helper->__('Invalid method type "%s"', $method));
        }

        $this->getQuote()->setCheckoutMethod($method)->save();

        return true;
    }

    /**
     * Get customer address by identifier
     *
     * @param   int $addressId
     *
     * @return  Mage_Customer_Model_Address
     */
    public function getAddress($addressId)
    {
        $address = Mage::getModel('customer/address')->load((int)$addressId);
        $address->explodeStreetAddress();
        if ($address->getRegionId()) {
            $address->setRegion($address->getRegionId());
        }

        return $address;
    }

    /**
     * Save billing address information to quote
     *
     * @param array $data
     * @param int   $customerAddressId
     * @param bool  $saveQuote
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     * @throws Mage_Core_Exception
     */
    public function saveBilling($data, $customerAddressId, $saveQuote = true)
    {
        if (empty($data)) {
            throw new Klarna_Kco_Exception($this->_helper->__('Invalid billing details'));
        }

        $address = $this->getQuote()->getBillingAddress();
        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $this->getQuote()->getCustomerId()) {
                    throw new Klarna_Kco_Exception($this->_helper->__('Customer Address is not valid.'));
                }

                $address->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
                $addressForm->setEntity($address);
                $addressErrors = $addressForm->validateData($address->getData());
                if ($addressErrors !== true) {
                    throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $addressErrors)));
                }
            }
        } else {
            $addressForm->setEntity($address);
            $addressData   = $addressForm->extractData($addressForm->prepareRequest($data));
            $addressErrors = $addressForm->validateData($addressData);
            if ($addressErrors !== true) {
                throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", array_values($addressErrors))));
            }

            $addressForm->compactData($addressData);
            //unset billing address attributes which were not shown in form
            foreach ($addressForm->getAttributes() as $attribute) {
                if (!isset($data[$attribute->getAttributeCode()])) {
                    $address->setData($attribute->getAttributeCode(), null);
                }
            }

            $address->setCustomerAddressId(null);
            $address->setSaveInAddressBook(0);
        }

        // set email for newly created user
        if (!$address->getEmail() && $this->getQuote()->getCustomerEmail()) {
            $address->setEmail($this->getQuote()->getCustomerEmail());
        }

        // validate billing address
        if (version_compare(Mage::getVersion(), '1.7', '>=') && ($validateRes = $address->validate()) !== true) {
            throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $validateRes)));
        }

        $address->implodeStreetAddress();

        $this->_validateCustomerData($data);

        if (!$this->getQuote()->getCustomerId() && self::METHOD_REGISTER == $this->getQuote()->getCheckoutMethod()) {
            if ($this->_customerEmailExists($address->getEmail(), Mage::app()->getWebsite()->getId())) {
                throw new Klarna_Kco_Exception($this->_helper->__($this->_customerEmailExistsMessage));
            }
        }

        if (!$this->getQuote()->isVirtual()) {
            /**
             * Billing address using otions
             */
            $usingCase = isset($data['same_as_other']) ? (int)$data['same_as_other'] : 0;

            switch ($usingCase) {
                case 0:
                    $shipping = $this->getQuote()->getShippingAddress();
                    $shipping->setSameAsBilling(0);
                    break;
                case 1:
                    $billing = clone $address;
                    $billing->unsAddressId()->unsAddressType();
                    $shipping       = $this->getQuote()->getShippingAddress();
                    $shippingMethod = $shipping->getShippingMethod();

                    // Billing address properties that must be always copied to shipping address
                    $requiredBillingAttributes = array('customer_address_id');

                    // don't reset original shipping data, if it was not changed by customer
                    foreach ($shipping->getData() as $shippingKey => $shippingValue) {
                        if (null !== $shippingValue && null !== $billing->getData($shippingKey)
                            && !isset($data[$shippingKey])
                            && !in_array($shippingKey, $requiredBillingAttributes)
                        ) {
                            $billing->unsetData($shippingKey);
                        }
                    }

                    $shipping->unsetData('region_id');
                    $shipping->addData($billing->getData())
                        ->setSameAsBilling(1)
                        ->setSaveInAddressBook(0)
                        ->setShippingMethod($shippingMethod)
                        ->setCollectShippingRates(true);
                    break;
            }
        }

        if ($saveQuote) {
            $this->getQuote()->collectTotals()->save();
        }

        return $this;
    }

    /**
     * Validate customer data and set some its data for further usage in quote
     * Will return either true or array with error messages
     *
     * @param array $data
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     */
    protected function _validateCustomerData(array $data)
    {
        /** @var $customerForm Mage_Customer_Model_Form */
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('checkout_register')
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        $quote = $this->getQuote();
        if ($quote->getCustomerId()) {
            $customer = $quote->getCustomer();
            $customerForm->setEntity($customer);
            $customerData = $quote->getCustomer()->getData();
        } else {
            /* @var $customer Mage_Customer_Model_Customer */
            $customer = Mage::getModel('customer/customer');
            $customerForm->setEntity($customer);
            $customerRequest = $customerForm->prepareRequest($data);
            $customerData    = $customerForm->extractData($customerRequest);
        }

        $customerErrors = $customerForm->validateData($customerData);
        if ($customerErrors !== true) {
            throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $customerErrors)));
        }

        if ($quote->getCustomerId()) {
            return $this;
        }

        $customerForm->compactData($customerData);

        // Spoof customer password
        $password = $customer->generatePassword();
        $customer->setPassword($password);
        $customer->setPasswordConfirmation($password);
        $customer->setConfirmation($password);
        // set NOT LOGGED IN group id explicitly,
        // otherwise copyFieldset('customer_account', 'to_quote') will fill it with default group id value
        $customer->setGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

        $result = $customer->validate();
        if (true !== $result && is_array($result)) {
            throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $result)));
        }

        if ($quote->getCheckoutMethod() == self::METHOD_REGISTER) {
            // save customer encrypted password in quote
            $quote->setPasswordHash($customer->encryptPassword($customer->getPassword()));
        }

        // copy customer/guest email to address
        $quote->getBillingAddress()->setEmail($customer->getEmail());

        // copy customer data to quote
        Mage::helper('core')->copyFieldset('customer_account', 'to_quote', $customer, $quote);

        return $this;
    }

    /**
     * Set default shipping method if one exist
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return $this
     * @throws Exception
     */
    public function checkShippingMethod($quote = null)
    {
        if (null === $quote) {
            $quote = $this->getQuote();
        }

        if (!$quote->isVirtual()) {
            $shippingAddress = $quote->getShippingAddress();

            if (!$shippingAddress->getCountryId()) {
                $defaultDestination = Mage::helper('klarna_kco/checkout')->getDefaultDestinationAddress($quote->getStore());
                $shippingAddress->addData($defaultDestination->toArray());
            }

            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()->save();

            $defaultRate         = null;
            $selectedMethodExist = false;
            $rates               = $quote->getShippingAddress()->getGroupedAllShippingRates();
            foreach ($rates as $carrierRates) {
                foreach ($carrierRates as $rate) {
                    if (null === $defaultRate) {
                        $defaultRate = $rate->getCode();
                    }

                    if ($rate->getCode() == $shippingAddress->getShippingMethod()) {
                        $selectedMethodExist = true;
                    }
                }
            }

            if ($this->hasActiveKlarnaShippingGateway()) {
                $defaultRate = Klarna_Kco_Model_Carrier_Klarna::GATEWAY_KEY;
            }

            if (!$selectedMethodExist) {
                $shippingAddress->setShippingMethod($defaultRate);
            }

            $quote->setTotalsCollectedFlag(false);
        }

        return $this;
    }

    /**
     * Save checkout shipping address
     *
     * @param array $data
     * @param int   $customerAddressId
     * @param bool  $saveQuote
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     */
    public function saveShipping($data, $customerAddressId, $saveQuote = true)
    {
        if (empty($data)) {
            throw new Klarna_Kco_Exception($this->_helper->__('Invalid billing details'));
        }

        $address = $this->getQuote()->getShippingAddress();

        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
            ->setIsAjaxRequest(Mage::app()->getRequest()->isAjax());

        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $this->getQuote()->getCustomerId()) {
                    throw new Klarna_Kco_Exception($this->_helper->__('Customer Address is not valid.'));
                }

                $address->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
                $addressForm->setEntity($address);
                $addressErrors = $addressForm->validateData($address->getData());
                if ($addressErrors !== true) {
                    throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $addressErrors)));
                }
            }
        } else {
            $addressForm->setEntity($address);
            // emulate request object
            $addressData   = $addressForm->extractData($addressForm->prepareRequest($data));
            $addressErrors = $addressForm->validateData($addressData);
            if ($addressErrors !== true) {
                throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $addressErrors)));
            }

            $addressForm->compactData($addressData);
            // unset shipping address attributes which were not shown in form
            foreach ($addressForm->getAttributes() as $attribute) {
                if (!isset($data[$attribute->getAttributeCode()])) {
                    $address->setData($attribute->getAttributeCode(), null);
                }
            }

            $address->setCustomerAddressId(null);
            $address->setSaveInAddressBook(empty($data['save_in_address_book']) ? 0 : 1);
            $address->setSameAsBilling(empty($data['same_as_other']) ? 0 : 1);
        }

        $address->implodeStreetAddress();
        $address->setCollectShippingRates(true);

        if (($validateRes = $address->validate()) !== true) {
            throw new Klarna_Kco_Exception(sprintf('%s', implode("\n", $validateRes)));
        }

        if ($saveQuote) {
            $this->getQuote()->collectTotals()->save();
        }

        return $this;
    }

    /**
     * Specify quote shipping method
     *
     * @param   string $shippingMethod
     *
     * @return $this
     * @throws Klarna_Kco_Exception
     */
    public function saveShippingMethod($shippingMethod)
    {
        if (empty($shippingMethod)) {
            throw new Klarna_Kco_Exception($this->_helper->__('Invalid shipping method.'));
        }

        $rate = $this->getQuote()->getShippingAddress()->getShippingRateByCode($shippingMethod);
        if (!$rate) {
            throw new Klarna_Kco_Exception($this->_helper->__('Invalid shipping method.'));
        }

        $this->getQuote()->getShippingAddress()
            ->setShippingMethod($shippingMethod);

        return $this;
    }

    /**
     * Specify quote payment method
     *
     * @param   array $data
     *
     * @return  $this
     */
    public function savePayment($data = array())
    {
        $data['method'] = 'klarna_kco';
        $quote          = $this->getQuote();

        if ($quote->isVirtual()) {
            $quote->getBillingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        } else {
            $quote->getShippingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        }

        // shipping totals may be affected by payment method
        if (!$quote->isVirtual() && $quote->getShippingAddress()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        $payment = $quote->getPayment();
        $payment->importData($data);

        $quote->save();

        return $this;
    }

    /**
     * Validate quote state to be integrated with klarna checkout process
     */
    public function validate()
    {
        $quote = $this->getQuote();
        if ($quote->getIsMultiShipping()) {
            Mage::throwException($this->_helper->__('Invalid checkout type.'));
        }

        if ($quote->getCheckoutMethod() == self::METHOD_GUEST && !$quote->isAllowedGuestCheckout()) {
            Mage::throwException(
                $this->_helper
                ->__('Sorry, guest checkout is not enabled. Please try again or contact store owner.')
            );
        }
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return $this
     */
    protected function _prepareGuestQuote()
    {
        $quote = $this->getQuote();
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);

        return $this;
    }

    /**
     * Prepare quote for customer registration and customer order submit
     *
     * @return $this
     */
    protected function _prepareNewCustomerQuote()
    {
        $quote    = $this->getQuote();
        $billing  = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $quote->getCustomer();
        /* @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else {
            $customerBilling->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $quote->setCustomer($customer)
            ->setCustomerId(true);
    }

    /**
     * Prepare quote for customer order submit
     *
     * @return $this
     */
    protected function _prepareCustomerQuote()
    {
        $quote    = $this->getQuote();
        $billing  = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        $customer = $this->getCustomerSession()->getCustomer();
        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }

        if ($shipping && !$shipping->getSameAsBilling()
            && (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }

        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        } else if (isset($customerBilling) && !$customer->getDefaultShipping()) {
            $customerBilling->setIsDefaultShipping(true);
        }

        $quote->setCustomer($customer);
    }

    /**
     * Involve new customer to system
     *
     * @return $this
     */
    protected function _involveNewCustomer()
    {
        $customer = $this->getQuote()->getCustomer();
        if ($customer->isConfirmationRequired()) {
            $customer->sendNewAccountEmail('confirmation', '', $this->getQuote()->getStoreId());
            $url = Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail());
            $this->getCustomerSession()->addSuccess(
                Mage::helper('customer')
                    ->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please <a href="%s">click here</a>.', $url)
            );
        } else {
            $customer->sendNewAccountEmail('registered', '', $this->getQuote()->getStoreId());
            $this->getCustomerSession()->loginById($customer->getId());
        }

        return $this;
    }

    /**
     * Create order based on checkout type. Create customer if necessary.
     *
     * @return Mage_Sales_Model_Order
     */
    public function saveOrder()
    {
        $this->validate();
        $this->_initKlarnaCheckout(false, false);

        try {
            if (($merchantCheckboxMethod = $this->_helper->getCheckoutConfig('merchant_checkbox')) != -1) {
                $this->_helper->dispatchMerchantCheckboxMethod(
                    $merchantCheckboxMethod, array(
                    'quote'        => $this->getQuote(),
                    'klarna_quote' => $this->getKlarnaQuote(),
                    'checked'      => (bool)$this->getKlarnaCheckout()->getData('merchant_requested/additional_checkbox')
                    )
                );
            }

            $checkboxes = $this->getKlarnaCheckout()->getData('merchant_requested/additional_checkboxes');
            if (!empty($checkboxes)) {
                $this->_helper->dispatchMultipleCheckboxesEvent(
                    $this->getKlarnaCheckout()->getData('merchant_requested/additional_checkboxes'),
                    $this->getQuote(),
                    $this->getKlarnaQuote()
                );
            }
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            // Don't stop order from saving just because the checkbox processing failed
        }

        $isNewCustomer = false;
        switch ($this->getCheckoutMethod()) {
            case self::METHOD_GUEST:
                $this->_prepareGuestQuote();
                break;
            case self::METHOD_REGISTER:
                $this->_prepareNewCustomerQuote();
                $isNewCustomer = true;
                break;
            default:
                $this->_prepareCustomerQuote();
                break;
        }

        Mage::dispatchEvent(
            'kco_checkout_save_order_before', array(
            'checkout' => $this
            )
        );

        /**
         * For older versions of Magento state cannot be removed as a requirement
         * for shipping which is necessary in some countries.
         *
         * This checks if the Magento version is below 1.7 and disabled address validation
         */
        if (version_compare(Mage::getVersion(), '1.7.0', '<')) {
            $this->getQuote()->getBillingAddress()->setShouldIgnoreValidation(true);
            if (!$this->getQuote()->isVirtual()) {
                $this->getQuote()->getShippingAddress()->setShouldIgnoreValidation(true);
            }
        }

        $service = Mage::getModel('sales/service_quote', $this->getQuote());
        $service->submitAll();

        if ($isNewCustomer) {
            try {
                $this->_involveNewCustomer();
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        $this->_checkoutSession->setLastQuoteId($this->getQuote()->getId())
            ->setLastSuccessQuoteId($this->getQuote()->getId())
            ->clearHelperData();

        /** @var Mage_Sales_Model_Order $order */
        $order = $service->getOrder();
        if ($order) {
            Mage::dispatchEvent(
                'checkout_type_kco_save_order_after', array(
                'order' => $order,
                'quote' => $this->getQuote()
                )
            );

            if ($order->getCanSendNewEmailFlag()) {
                try {
                    $order->sendNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }

            // add order information to the session
            $this->_checkoutSession
                ->setLastOrderId($order->getId())
                ->setLastRealOrderId($order->getIncrementId());

            // as well a billing agreement can be created
            $agreement = $order->getPayment()->getBillingAgreement();
            if ($agreement) {
                $this->_checkoutSession->setLastBillingAgreementId($agreement->getId());
            }
        }

        Mage::dispatchEvent(
            'checkout_submit_all_after',
            array('order' => $order, 'quote' => $this->getQuote(), 'recurring_profiles' => array())
        );

        return $order;
    }

    /**
     * Check if customer email exists
     *
     * @param string $email
     * @param int    $websiteId
     *
     * @return false|Mage_Customer_Model_Customer
     */
    protected function _customerEmailExists($email, $websiteId = null)
    {
        $customer = Mage::getModel('customer/customer');
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }

        $customer->loadByEmail($email);
        if ($customer->getId()) {
            return $customer;
        }

        return false;
    }

    /**
     * Get last order increment id by order id
     *
     * @return string
     */
    public function getLastOrderId()
    {
        $lastId  = $this->getCheckout()->getLastOrderId();
        $orderId = false;
        if ($lastId) {
            $order = Mage::getModel('sales/order');
            $order->load($lastId);
            $orderId = $order->getIncrementId();
        }

        return $orderId;
    }

    /**
     * Get the value of a merchant checkbox
     *
     * @param string $checkboxId
     * @return bool
     */
    protected function getCheckboxValue($checkboxId)
    {
        $checkboxes = $this->getKlarnaCheckout()->getData('merchant_requested/additional_checkboxes');
        foreach ($checkboxes as $checkbox) {
            if ($checkbox['id'] == $checkboxId) {
                return $checkbox['checked'];
            }
        }

        return false;
    }

    /**
     * Returns true when we have shipping method gateway information and they can be used
     *
     * @return bool
     */
    public function hasActiveKlarnaShippingGateway()
    {
        $klarnaQuote = $this->getKlarnaQuote();
        if (!$klarnaQuote->getIsActive()) {
            return false;
        }

        $shipping = $this->getKlarnaShippingGateway();
        if ($shipping === null) {
            return false;
        }

        if ($shipping->getId() === null) {
            return false;
        }

        if (!$shipping->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * Clearing the shipping gateway attribute
     *
     * @return $this
     */
    public function clearShippingGateway()
    {
        $this->_shippingGateway = null;
        return $this;
    }

    /**
     * Getting back the Klarna shipping method gateway
     *
     * @return Klarna_Kco_Model_Klarnashippingmethodgateway|null
     */
    public function getKlarnaShippingGateway()
    {
        if ($this->_shippingGateway !== null) {
            return $this->_shippingGateway;
        }

        $klarnaQuote = $this->getKlarnaQuote();
        if ($klarnaQuote->getKlarnaCheckoutId() === null) {
            return null;
        }

        $this->_shippingGateway = Mage::getModel('klarna_kco/klarnashippingmethodgateway')
            ->loadByKlarnaCheckoutId($klarnaQuote->getKlarnaCheckoutId());

        if ($this->_shippingGateway->getId() === null) {
            $this->_shippingGateway = null;
        }

        return $this->_shippingGateway;
    }
}
