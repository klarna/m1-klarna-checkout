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
 * Abstract class for rest resource integration
 *
 * @method Klarna_Kco_Model_Api_Rest_Client_Abstract setStore(Mage_Core_Model_Store $store)
 * @method Klarna_Kco_Model_Api_Rest_Client_Abstract setConfig(Varien_Object $config)
 * @method Klarna_Kco_Model_Api_Rest_Client_Abstract setUserAgent(Mage_Core_Model_Store $store)
 */
abstract class Klarna_Kco_Model_Api_Rest_Client_Abstract extends Varien_Object
{
    /**
     * Translation support
     *
     * @param string $value
     *
     * @return string
     */
    public function __($value)
    {
        return $this->getHelper()->__($value);
    }

    /**
     * Get current client store
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        if (!$this->hasStore()) {
            $this->setData('store', Mage::app()->getStore());
        }

        return $this->getData('store');
    }

    /**
     * Get rest helper
     *
     * @return Klarna_Kco_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('klarna_kco');
    }

    /**
     * Get a new request object for building a request
     *
     * @return Klarna_Kco_Model_Api_Rest_Client_Request
     */
    public function getNewRequestObject()
    {
        return $this->getRestClient()->getNewRequestObject();
    }

    /**
     * Perform a request
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Request $request
     *
     * @throws Klarna_Kco_Model_Api_Exception
     * @return Klarna_Kco_Model_Api_Rest_Client_Response|string
     */
    public function request($request)
    {
        return $this->getRestClient()
            ->setStore($this->getStore())
            ->setConfig($this->getConfig())
            ->request($request);
    }

    /**
     * Get rest client singleton
     *
     * @return Klarna_Kco_Model_Api_Rest_Client
     */
    public function getRestClient()
    {
        return Mage::getSingleton('klarna_kco/api_rest_client', (array)$this->getUserAgent());
    }

    /**
     * Get user agent details for rest client
     *
     * @return array
     */
    public function getUserAgent()
    {
        return (array)$this->getData('user_agent');
    }

    /**
     * Get resource id from Location URL
     *
     * This assumes the ID is the last url path
     *
     * @param string|Klarna_Kco_Model_Api_Rest_Client_Response $location
     *
     * @return string
     */
    public function getLocationResourceId($location)
    {
        if ($location instanceof Klarna_Kco_Model_Api_Rest_Client_Response) {
            $location = $location->getResponseObject()->getHeader('Location');
        }

        $location = rtrim($location, '/');

        return array_pop(explode('/', $location));
    }
}
