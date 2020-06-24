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
 * Api model for the Klarna checkout
 */
class Klarna_Kco_Model_Api_Rest_Checkout extends Klarna_Kco_Model_Api_Rest_Client_Abstract
{
    /**
     * Get Klarna order details
     *
     * @param $id
     *
     * @return Klarna_Kco_Model_Api_Rest_Client_Response
     * @throws Klarna_Kco_Model_Api_Exception
     */
    public function getOrder($id)
    {
        if (null === $id) {
            throw new Klarna_Kco_Model_Api_Exception('Klarna order id required for retrieval');
        }

        $url = array(
            'checkout',
            'v3',
            'orders',
            $id
        );

        $request = $this->getNewRequestObject()
            ->setUrl($url)
            ->setIdField('order_id')
            ->setDefaultErrorMessage('Error: Order not found.');

        return $this->request($request);
    }

    /**
     * Create new order
     *
     * @param array $data
     *
     * @return \Klarna_Kco_Model_Api_Rest_Client_Response
     */
    public function createOrder($data)
    {
        $url = array(
            'checkout',
            'v3',
            'orders'
        );

        $request = $this->getNewRequestObject()
            ->setUrl($url)
            ->setIdField('order_id')
            ->setMethod(Klarna_Kco_Model_Api_Rest_Client_Request::REQUEST_METHOD_POST)
            ->setDefaultErrorMessage('Error: Unable to create order.')
            ->setParams($data);

        return $this->request($request);
    }

    /**
     * Update Klarna order
     *
     * @param string $id
     * @param array  $data
     *
     * @return Klarna_Kco_Model_Api_Rest_Client_Response
     * @throws Klarna_Kco_Model_Api_Exception
     */
    public function updateOrder($id = null, $data)
    {
        if (null === $id) {
            throw new Klarna_Kco_Model_Api_Exception('Klarna order id required for update');
        }

        $url = array(
            'checkout',
            'v3',
            'orders',
            $id
        );

        $request = $this->getNewRequestObject()
            ->setUrl($url)
            ->setIdField('order_id')
            ->setMethod(Klarna_Kco_Model_Api_Rest_Client_Request::REQUEST_METHOD_POST)
            ->setDefaultErrorMessage('Error: Unable to create order.')
            ->setParams($data);

        return $this->request($request);
    }
}
