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
 * Klarna REST api client
 *
 * @method Klarna_Kco_Model_Api_Rest_Client_Request getRequest()
 * @method Mage_Core_Model_Store getStore()
 * @method Klarna_Kco_Model_Api_Rest_Client setResponseType($string)
 * @method string getResponseType()
 * @method Klarna_Kco_Model_Api_Rest_Client setConfig(Varien_Object $config)
 * @method Varien_Object getConfig()
 */
class Klarna_Kco_Model_Api_Rest_Client extends Varien_Object
{
    /**
     * Current open client connection
     *
     * @var Zend_Http_Client
     */
    protected $_client;

    /**
     * Stores the parameters sent.
     *
     * @var array
     */
    protected $_parameters = array();

    /**
     * Request method used for get
     *
     * @var string
     */
    const REQUEST_METHOD_GET = Zend_Http_Client::GET;

    /**
     * Request method used for post
     *
     * @var string
     */
    const REQUEST_METHOD_POST = Zend_Http_Client::POST;

    /**
     * Request method used for patch
     *
     * @var string
     */
    const REQUEST_METHOD_PATCH = 'PATCH';

    /**
     * Default request object type
     *
     * @var string
     */
    protected $_requestObject = 'klarna_kco/api_rest_client_request';

    /**
     * Response of last request
     *
     * @var Varien_Object|mixed
     */
    protected $_responseObject = null;

    /**
     * Name of log file for request
     *
     * @var string
     */
    const LOG_RAW_FILE = 'kco_kasper_api.log';

    /**
     * Response type for RAW data
     *
     * @var string
     */
    const RAW_RESPONSE_TYPE = 'raw';

    /**
     * Cache group Tag
     */
    const CACHE_GROUP = 'klarna_api';

    /**
     * JSON encoding type string
     *
     * @var string
     */
    const ENC_JSON = 'application/json';

    /**
     * Default values for the request configuration.
     *
     * @var array
     */
    protected $_requestConfig = array(
        'maxredirects'    => 5,
        'strictredirects' => false,
        'useragent'       => 'Magento_KCO_Client',
        'timeout'         => 30,
        'adapter'         => 'Zend_Http_Client_Adapter_Socket',
        'httpversion'     => Zend_Http_Client::HTTP_1,
        'keepalive'       => true,
        'storeresponse'   => true,
        'strict'          => true,
        'output_stream'   => false,
        'encodecookies'   => true,
        'rfc3986_strict'  => false
    );

    /**
     * Init connection client
     *
     * @param array $userAgent
     *
     * @return $this
     */
    protected function _construct($userAgent = array())
    {
        $moduleVersion  = Mage::getConfig()->getModuleConfig('Klarna_Kco')->version;
        $edition        = version_compare(Mage::getVersion(), '1.7', '<') ? 'unknown' : Mage::getEdition();
        $magentoVersion = Mage::getVersion();
        $phpVersion     = phpversion();

        $userAgent = array_merge(
            array(
            "Magento_KCO_Client_v{$moduleVersion}",
            "Magento/{$edition}_{$magentoVersion}",
            "Language/PHP_{$phpVersion}"
            ), $userAgent
        );

        $this->setRequestConfig('useragent', implode(' ', $userAgent));

        $this->_client = $this->getClient();

        return $this;
    }

    /**
     * Load the connection client
     *
     * @param null $url
     *
     * @return Zend_Http_Client
     */
    public function getClient($url = null)
    {
        if (null === $this->_client) {
            $client = new Zend_Http_Client(null, $this->getRequestConfig());

            $client->setHeaders(
                array(
                'Accept-encoding' => 'gzip,deflate',
                'accept'          => 'application/json',
                'content-type'    => 'application/json'
                )
            );

            $client->setAuth($this->_getUsername(), $this->_getPassword());

            $this->_client = $client;
        }

        if (null !== $url) {
            try {
                if (!is_string($url) || null === parse_url($url, PHP_URL_SCHEME)) {
                    if (!is_array($url)) {
                        $url = array($url);
                    }

                    $urlBase = $this->_getBaseRequestUrl();
                    $urlBase = rtrim($urlBase, '/');

                    array_unshift($url, $urlBase);

                    $url = implode('/', $url);
                }

                $url = preg_replace('/\s+/', '', $url);

                $this->_client->setUri($url);
            } catch (Zend_Uri_Exception $e) {
                $this->_debug($e, Zend_Log::CRIT);
            }
        }

        return $this->_client;
    }

    /**
     * Set request
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Request $request
     *
     * @return $this
     */
    public function setRequest(Klarna_Kco_Model_Api_Rest_Client_Request $request)
    {
        if ($request->getStore()) {
            $this->setStore($request->getStore());
        }

        $this->setData('request', $request);

        return $this;
    }

    /**
     * Set request store scope
     *
     * @param Mage_Core_Model_Store $store
     *
     * @return $this
     */
    public function setStore($store)
    {
        $this->setData('store', $store);
        $this->resetClient();

        return $this;
    }

    /**
     * Get API url
     *
     * @return string
     */
    protected function _getBaseRequestUrl()
    {
        return Mage::getStoreConfigFlag('payment/klarna_kco/test_mode', $this->getStore()) ?
            $this->getConfig()->getTestdriveUrl()
            : $this->getConfig()->getProductionUrl();
    }

    /**
     * Get api username
     *
     * @return string
     */
    protected function _getUsername()
    {
        return Mage::getStoreConfig('payment/klarna_kco/merchant_id', $this->getStore());
    }

    /**
     * Get api password
     *
     * @return string
     */
    protected function _getPassword()
    {
        return Mage::getStoreConfig('payment/klarna_kco/shared_secret', $this->getStore());
    }

    /**
     * Reset client connection
     *
     * @return $this
     */
    public function resetClient()
    {
        $this->_client = null;

        return $this;
    }

    /**
     * Covert response into response object
     *
     * @throws Klarna_Kco_Model_Api_Exception
     * @return mixed
     */
    protected function _getResponseObject()
    {
        if (null === $this->_responseObject) {
            $responseArray  = array();
            $responseObject = $this->_getResponseType();
            $isRawResponse  = self::RAW_RESPONSE_TYPE === $responseObject;
            $isSuccessful   = false;

            /** @var Zend_Http_Response $response */
            try {
                $response = $this->getLastResponse();
                if ($response !== false) {
                    if (!$isRawResponse) {
                        try {
                            $responseArray = Mage::helper('core')->jsonDecode($response->getBody());
                            if (!$responseArray) {
                                $responseArray = array();
                            }
                        } catch (Exception $e) {
                            $responseArray = array();
                        }

                        if ($response->isSuccessful()) {
                            $isSuccessful = true;
                        }
                    }
                }
            } catch (Zend_Http_Client_Exception $e) {
                $this->_debug($e, Zend_Log::CRIT);
            }

            if ($isRawResponse) {
                if ($response instanceof Zend_Http_Response) {
                    $response = $response->getBody();
                }

                $this->_responseObject = $response;
            } elseif (is_object($responseObject)) {
                $responseObject
                    ->setRequest($this->getData('request'))
                    ->setResponseObject($response)
                    ->setIsSuccessful($isSuccessful)
                    ->setResponse($responseArray);

                $this->_responseObject = $responseObject;
            } else {
                $this->_responseObject = $response;
            }
        }

        return $this->_responseObject;
    }

    /**
     * Get the proper response type for the request
     *
     * @return bool|false|Varien_Object
     * @throws Klarna_Kco_Model_Api_Exception
     */
    protected function _getResponseType()
    {
        if ($this->getData('response_type')) {
            if (self::RAW_RESPONSE_TYPE == strtolower($this->getData('response_type'))) {
                return self::RAW_RESPONSE_TYPE;
            }

            $responseModel = Mage::getModel($this->getData('response_type'));
            if (!$responseModel) {
                throw new Klarna_Kco_Model_Api_Exception('Invalid response type.');
            }

            return $responseModel;
        }

        return false;
    }

    /**
     * Perform a GET request
     *
     * @param       $url
     *
     * @return Varien_Object
     */
    protected function _requestGet($url)
    {
        return $this->_requestByMethod(self::REQUEST_METHOD_GET, $url);
    }

    /**
     * Perform a POST request
     *
     * @param string $url
     *
     * @return Varien_Object
     */
    protected function _requestPost($url)
    {
        return $this->_requestByMethod(self::REQUEST_METHOD_POST, $url);
    }

    /**
     * Perform a PATCH request
     *
     * @param string $url
     *
     * @return Varien_Object
     */
    protected function _requestPatch($url)
    {
        return $this->_requestByMethod(self::REQUEST_METHOD_PATCH, $url);
    }

    /**
     * Do a request by method
     *
     * @param string $method
     * @param string $url
     *
     * @return mixed
     */
    protected function _requestByMethod($method, $url)
    {
        $this->getClient($url);

        $this->_doRequest($method);

        $this->_responseObject = null;

        $response = $this->_getResponseObject();
        $request  = $this->getRequest();

        Mage::dispatchEvent("kco_api_{$request->getFullActionName()}_{$method}_after", $this->_getEventData());
        Mage::dispatchEvent("kco_api_request_{$method}_after", $this->_getEventData());
        Mage::dispatchEvent("kco_api_request_after", $this->_getEventData());

        return $response;
    }

    /**
     * Get the last response from the API
     *
     * @return mixed|Zend_Http_Response
     */
    public function getLastResponse()
    {
        $lastResponse = $this->getData('last_response');

        if ($lastResponse instanceof Zend_Http_Response) {
            return $lastResponse;
        }

        if (!empty($lastResponse)) {
            return Zend_Http_Response::fromString($lastResponse);
        }

        if (null === $lastResponse) {
            return false;
        }

        return $lastResponse;
    }

    /**
     * Perform the request
     *
     * @param string $method
     *
     * @throws Exception
     * @return Zend_Http_Response
     */
    protected function _doRequest($method = self::REQUEST_METHOD_GET)
    {
        /** @var Klarna_Kco_Model_Api_Rest_Client_Request $request */
        $request  = $this->getRequest();
        $response = $this->_loadCache($request);

        if (false !== $response) {
            $this->setData('last_response', $response);

            return $response;
        }

        $client = $this->getClient();

        // Validate request
        try {
            $request->validate();
        } catch (Exception $e) {
            $this->_debug($client, Zend_Log::DEBUG);
            $this->_debug($e, Zend_Log::ERR);
            throw $e;
        }

        // Set GET params
        $paramsGet = $request->getParams(
            self::REQUEST_METHOD_GET,
            Klarna_Kco_Model_Api_Rest_Client_Request::REQUEST_PARAMS_FORMAT_TYPE_ARRAY
        );
        if (!empty($paramsGet)) {
            $client->setParameterGet($paramsGet);
        }

        // Set POST & PATCH params
        $paramsPost = $request->getParams(
            array(
            self::REQUEST_METHOD_POST,
            self::REQUEST_METHOD_PATCH
            )
        );
        if (!empty($paramsPost)) {
            if ($request->getPostJson()) {
                $client->setRawData($paramsPost, self::ENC_JSON);
            } else {
                $client->setParameterPost($paramsPost);
            }
        }

        // Set METHOD Type params (global params)
        $paramsGlobal = $request->getParams(
            false,
            Klarna_Kco_Model_Api_Rest_Client_Request::REQUEST_PARAMS_FORMAT_TYPE_ARRAY
        );
        if (!empty($paramsGlobal)) {
            switch ($method) {
                case self::REQUEST_METHOD_POST:
                    if ($request->getPostJson()) {
                        $client->setRawData($paramsGlobal, self::ENC_JSON);
                    } else {
                        $client->setParameterPost($paramsGlobal);
                    }
                    break;
                case self::REQUEST_METHOD_GET:
                default:
                    $client->setParameterGet($paramsGlobal);
            }
        }

        // Do the request
        try {
            Mage::dispatchEvent("kco_api_{$request->getFullActionName()}_{$method}_before", $this->_getEventData());
            Mage::dispatchEvent("kco_api_request_{$method}_before", $this->_getEventData());
            Mage::dispatchEvent("kco_api_request_before", $this->_getEventData());

            $response = $client->request($method);

            if ($this->getRequest()->getFollowLocationHeader() && $response->isSuccessful()
                && ($location = $response->getHeader('Location'))
            ) {
                $this->_debug($client->getLastResponse(), Zend_Log::DEBUG);
                $this->_debug('Following Location header', Zend_Log::DEBUG);

                $client   = $this->getClient($location);
                $response = $client->request(self::REQUEST_METHOD_GET);
            }

            $this->_saveCache($request, $response);
        } catch (Exception $e) {
            $this->_debug($e, Zend_Log::CRIT);
            $code = $e->getCode();
            if (!in_array($code, array(500, 501, 502, 503, 504, 505, 509))) {
                $code = 500;
            }

            $response = new Zend_Http_Response($code, array(), $e->getMessage(), '1.1', $e->getMessage());
        }

        $this->_debug($client->getLastRequest(), Zend_Log::DEBUG);
        $this->_debug($client->getLastResponse(), Zend_Log::DEBUG);

        $this->setData('last_response', $response);

        $timeout = $request->getRequestTimeout();
        if (null !== $timeout) {
            $oldTimeout = $this->getData('_temp_timeout', $this->getRequestConfig('timeout'));
            $this->setRequestConfig('timeout', $oldTimeout);
            $this->getClient()->setConfig($this->getRequestConfig());
        }

        return $response;
    }

    /**
     * Perform a request
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Request $request
     *
     * @throws Klarna_Kco_Model_Api_Exception
     * @return Klarna_Kco_Model_Api_Rest_Client_Response|string
     */
    public function request(Klarna_Kco_Model_Api_Rest_Client_Request $request)
    {
        $timeout = $request->getRequestTimeout();
        if (null !== $timeout) {
            $this->setData('_temp_timeout', $this->getRequestConfig('timeout'));
            $this->setRequestConfig('timeout', $timeout);
            $this->getClient()->setConfig($this->getRequestConfig());
        }

        $this->setData('request', $request);
        $this->setData('response_type', $request->getData('response_type'));

        $method = strtoupper(trim($request->getMethod()));

        $this->setData('method', $method);

        switch ($method) {
            case self::REQUEST_METHOD_PATCH:
                return $this->_requestPatch($request->getUrl());
            case self::REQUEST_METHOD_POST:
                return $this->_requestPost($request->getUrl());
            case self::REQUEST_METHOD_GET:
                return $this->_requestGet($request->getUrl());
            case '':
            case null:
                throw new Klarna_Kco_Model_Api_Exception('Request method must be defined.');
            default:
                return $this->_requestByMethod($method, $request->getUrl());
        }
    }

    /**
     * Get a new request object for building a request
     *
     * @return Klarna_Kco_Model_Api_Rest_Client_Request
     */
    public function getNewRequestObject()
    {
        return Mage::getModel($this->_requestObject);
    }

    /**
     * The request configuration used for the request.
     * If a field type is provided then the raw data will be returned. Otherwise, the data will be formatted to be used
     * for the HTTP request.
     *
     * @see self::_getRequestConfig()
     *
     * @param string $name
     *
     * @return array
     */
    public function getRequestConfig($name = null)
    {
        if (null === $name) {
            return $this->_getRequestConfig();
        } else {
            if (isset($this->_requestConfig[$name])) {
                return $this->_requestConfig[$name];
            }
        }

        return null;
    }

    /**
     * Prepares the request configuration array to be used in the HTTP request.
     *
     * @return array
     */
    protected function _getRequestConfig()
    {
        $_requestConfigCurrent = $this->_requestConfig;
        $_requestConfigNew     = array();
        foreach ($_requestConfigCurrent as $name => $value) {
            if (!empty($value)) {
                if (is_array($value)) {
                    $value = implode(',', $value);
                }

                $_requestConfigNew[$name] = $value;
            }
        }

        return $_requestConfigNew;
    }

    /**
     * Set the configuration for sending a request.
     *
     * @param array|string $name
     * @param mixed        $value
     *
     * @return $this
     */
    public function setRequestConfig($name, $value = null)
    {
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $this->_requestConfig[$k] = $v;
            }
        } else {
            $this->_requestConfig[$name] = $value;
        }

        return $this;
    }

    /**
     * Load block html from cache storage
     *
     * @param $request
     *
     * @return string | false
     */
    protected function _loadCache($request)
    {
        if (null === $request->getCacheLifetime() || !Mage::app()->useCache(self::CACHE_GROUP)) {
            return false;
        }

        $cacheKey  = $request->getCacheKey();
        $cacheData = Mage::app()->loadCache($cacheKey);

        return $cacheData;
    }

    /**
     * Save block content to cache storage
     *
     * @param        $request
     * @param string $data
     *
     * @return $this
     */
    protected function _saveCache($request, $data)
    {
        if (!Mage::app()->useCache(self::CACHE_GROUP) || null === $request->getCacheLifetime()) {
            return false;
        }

        $cacheKey = $request->getCacheKey();
        Mage::app()->saveCache($data, $cacheKey, $request->getCacheTags(), $request->getCacheLifetime());

        return $this;
    }

    /**
     * Log debug messages
     *
     * @param $message
     * @param $level
     */
    protected function _debug($message, $level)
    {
        if (Zend_Log::DEBUG != $level || Mage::getStoreConfigFlag('payment/klarna_kco/debug', $this->getStore())) {
            Mage::log($this->_rawDebugMessage($message), $level, self::LOG_RAW_FILE, true);
        }
    }

    /**
     * Raw debug message for logging
     *
     * @param $message
     *
     * @return string
     */
    protected function _rawDebugMessage($message)
    {
        if ($message instanceof Zend_Http_Client) {
            $client  = $message;
            $message = $client->getLastRequest();

            if ($response = $client->getLastResponse()) {
                $message .= "\n\n" . $response->asString();
            }
        } elseif ($message instanceof Zend_Http_Response) {
            $message = $message->getHeadersAsString(true, "\n") . "\n" . $message->getBody();
        } elseif ($message instanceof Exception) {
            $message = $message->__toString();
        }

        return $message;
    }

    /**
     * Get array of objects transferred to default events processing
     *
     * @return array
     */
    protected function _getEventData()
    {
        $request = $this->getRequest();
        $client  = $this->getClient();

        $responseArray = array(
            'request'     => $request,
            'raw_request' => $client->getLastRequest(),
        );

        if ($this->getLastResponse()) {
            $responseArray['response']     = $this->_getResponseObject();
            $responseArray['raw_response'] = $client->getLastResponse();
        }

        return $responseArray;
    }
}
