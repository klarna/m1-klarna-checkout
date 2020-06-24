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
 * Response from the API
 *
 * @method Klarna_Kco_Model_Api_Rest_Client_Request getRequest()
 * @method Klarna_Kco_Model_Api_Rest_Client_Request setResponseObject($string)
 * @method Zend_Http_Response getResponseObject()
 */
class Klarna_Kco_Model_Api_Rest_Client_Response extends Klarna_Kco_Model_Api_Response
{
    /**
     * Model class name
     */
    const RESPONSE_TYPE = 'klarna_kco/api_rest_client_response';

    /**
     * Get the error message to display for invalid items.
     *
     * @return string
     */
    public function getDefaultErrorMessage()
    {
        return (null !== $this->getRequest()) ? $this->getRequest()->getData('default_error_message')
            : 'Unknown error';
    }

    /**
     * Set the raw response array from the API call
     *
     * @param array $response
     *
     * @return $this
     */
    public function setResponse(array $response)
    {
        // Remove first node for a response for one item
        $keys = array_keys($response);
        if (1 === count($keys) && 0 === $keys[0]) {
            $response = $response[0];
        }

        $idField = (null !== $this->getRequest()) ? $this->getRequest()->getData('id_field') : null;
        if (null !== $idField && (empty($response) || isset($response['error_code']))) {
            $id        = $this->getRequest()->getIds();
            $_response = array(
                'error'         => true,
                'error_message' => $this->getDefaultErrorMessage(),
            );

            if (null !== $idField) {
                $_response[$idField] = $id ?: null;
            }

            $response = array_merge($response, $_response);
        }

        $this->addData($response);

        return $this;
    }

    /**
     * Set the request used to load the data
     *
     * @param Klarna_Kco_Model_Api_Rest_Client_Request $request
     *
     * @return $this
     */
    public function setRequest(Klarna_Kco_Model_Api_Rest_Client_Request $request)
    {
        $this->setIdFieldName($request->getData('id_field') ? $request->getData('id_field') : null);
        $this->setData('request', $request);

        return $this;
    }

}
