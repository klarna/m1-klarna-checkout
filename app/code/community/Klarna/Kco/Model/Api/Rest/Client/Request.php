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
 * Used for building a request to send to the API
 *
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setResponseType($string)
 * @method string getResponseType()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setMethod($string)
 * @method string getMethod()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setDefaultErrorMessage($string)
 * @method string getDefaultErrorMessage()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setUrl($array)
 * @method array getUrl()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setIdField($string)
 * @method string getIdField()
 * @method string getValidatorMethod()
 * @method array getIds()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setDefaultParamFormat($string)
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setCacheLifetime($int)
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setCacheTags($array)
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setPostJson($boolean)
 * @method boolean getPostJson()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setRequestTimeout($int)
 * @method int getRequestTimeout()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setFollowLocationHeader($bool)
 * @method bool getFollowLocationHeader()
 */
class Klarna_Kco_Model_Api_Rest_Client_Request extends Varien_Object
{
    /**
     * Single item class name response
     *
     * @var string
     */
    const RESPONSE_TYPE_SINGLE = 'klarna_kco/api_rest_client_response';

    /**
     * Single item class name response
     *
     * @var string
     */
    const RESPONSE_TYPE_RAW = 'raw';

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
     * Request method for delete
     *
     * @var string
     */
    const REQUEST_METHOD_DELETE = Zend_Http_Client::DELETE;

    /**
     * Request parameter format array
     *
     * @var string
     */
    const REQUEST_PARAMS_FORMAT_TYPE_ARRAY = 'ARRAY';

    /**
     * Request parameter format json
     *
     * @var string
     */
    const REQUEST_PARAMS_FORMAT_TYPE_JSON = 'JSON';

    /**
     * Cache group Tag
     */
    const CACHE_GROUP = 'klarna_api';

    /**
     * Build the default values for the object
     */
    protected function _construct()
    {
        $this->setData(
            array(
            'response_type'          => self::RESPONSE_TYPE_SINGLE,
            'method'                 => self::REQUEST_METHOD_GET,
            'default_error_message'  => 'Error: unable to find object in api',
            'url'                    => array(),
            'id_field'               => null,
            'ids'                    => array(),
            'cache_lifetime'         => null,
            'post_json'              => true,
            'follow_location_header' => false,
            )
        );
    }

    /**
     * Set the expected IDs in the response.
     *
     * Currently, the API does not return results for IDs that do not exist. This allows error checking to see if a
     * a response for a ID was not returned.
     *
     * @param $id
     *
     * @return $this
     */
    public function setIds($id)
    {
        if (!is_array($id)) {
            $id = array($id);
        }

        $this->setData('ids', $id);

        return $this;
    }

    /**
     * Set data for sending
     *
     * @param array       $params
     * @param bool|string $type
     *
     * @return $this
     */
    public function setParams($params, $type = false)
    {
        if (!is_array($params) || empty($params)) {
            return $this;
        }

        if (!$type || !is_string($type)) {
            $type = $this->getMethod() ?: 'global';
        }

        $this->_data['params'][$type] = $params;

        $this->_hasDataChanges = true;

        return $this;
    }

    /**
     * Get data to be sent
     *
     * @param bool|string|array $type
     * @param string            $format
     *
     * @return array|mixed|string
     */
    public function getParams($type = false, $format = null)
    {
        if (is_array($type)) {
            $data = array();
            foreach ($type as $_type) {
                $_params = $this->getParams($_type, self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY);
                $data    = array_merge($data, $_params);
            }
        } else {
            if (!$type || !is_string($type)) {
                $type = 'global';
            }

            if (isset($this->_data['params'][$type])) {
                $data = $this->_data['params'][$type];
            } else {
                $data = array();
            }
        }

        if (null === $format) {
            $format = $this->getDefaultParamFormat();
        }

        switch ($format) {
            case self::REQUEST_PARAMS_FORMAT_TYPE_JSON:
                return json_encode($data);
            case self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY:
            default:
                if (is_array($data)) {
                    return $data;
                } elseif (null !== $data) {
                    return array($data);
                } else {
                    return array();
                }
        }
    }

    /**
     * Get the request action name
     *
     * @param string $delimiter
     * @param bool   $allowNumeric
     *
     * @return string
     */
    public function getFullActionName($delimiter = '_', $allowNumeric = false)
    {
        $actionPath = $allowNumeric
            ? $this->getUrl()
            : array_filter(
                $this->getUrl(),
                function ($v) {
                    return !is_numeric($v);
                }
            );
        $actionName = implode($delimiter, $actionPath);

        return $actionName;
    }

    /**
     * Get default format to get the data in
     *
     * @return mixed|string
     */
    public function getDefaultParamFormat()
    {
        $format = $this->getData('default_param_format');

        return null === $format ? ($this->getPostJson()
            ? self::REQUEST_PARAMS_FORMAT_TYPE_JSON : self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY) : $format;
    }

    /**
     * Get cache key informative items
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        $_params    = $this->getParams(false, self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY);
        $paramsPost = $this->getParams(self::REQUEST_METHOD_POST, self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY);
        $paramsGet  = $this->getParams(self::REQUEST_METHOD_GET, self::REQUEST_PARAMS_FORMAT_TYPE_ARRAY);
        $params     = array_merge($_params, $paramsPost, $paramsGet);
        asort($params);

        return array(
            implode('/', $this->getUrl()),
            $this->getMethod(),
            implode(':', $params)
        );
    }

    /**
     * Get Key for caching api calls
     *
     * @return string
     */
    public function getCacheKey()
    {
        if ($this->hasData('cache_key')) {
            return $this->getData('cache_key');
        }

        $key = $this->getCacheKeyInfo();
        $key = array_values($key);
        $key = implode('|', $key);
        $key = sha1($key);

        return $key;
    }

    /**
     * Get tags array for saving cache
     *
     * @return array
     */
    public function getCacheTags()
    {
        if (!$this->hasData('cache_tags')) {
            $tags = array();
        } else {
            $tags = $this->getData('cache_tags');
        }

        $tags[] = self::CACHE_GROUP;

        return $tags;
    }

    /**
     * Get block cache life time
     *
     * @return int
     */
    public function getCacheLifetime()
    {
        if ($this->getMethod() != self::REQUEST_METHOD_GET) {
            return null;
        }

        return $this->getData('cache_lifetime');
    }

    /**
     * Set validation before sending request.
     *
     * @param callback $function
     * The function to be called. Class methods may also be invoked
     * statically using this function by passing
     * array($classname, $methodname) to this parameter.
     * Additionally class methods of an object instance may be called by passing
     * array($objectinstance, $methodname) to this parameter.
     *
     * @return $this
     */
    public function setValidatorMethod($function)
    {
        if ((is_string($function) && function_exists($function))
            || (is_array($function) && isset($function[0], $function[1]) && method_exists($function[0], $function[1]))
        ) {
            $this->setData('validator_method', $function);
        }

        return $this;
    }

    /**
     * Validates method parameters based off a validation method
     *
     * @return bool
     */
    public function validate()
    {
        if ($this->getValidatorMethod()) {
            $result = call_user_func($this->getValidatorMethod(), $this);

            if ($result instanceof $this) {
                $data = $result->getData();
                $this->setData($data);
            } elseif (false === $result) {
                return false;
            }
        }

        return true;
    }
}
