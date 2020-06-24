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
 * @author     Fei Chen <fei.chen@klarna.com>
 */

/**
 * Klarna order line abstract
 */
abstract class Klarna_Kco_Model_Checkout_Attachment_Abstract
{
    /**
     * Order line code name
     *
     * @var string
     */
    protected $_code;


    /**
     * @var Klarna_Kco_Model_Api_Builder_Abstract
     */
    protected $_object = null;


    /**
     * Set code name
     *
     * @param string $code
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->_code = $code;

        return $this;
    }

    /**
     * Retrieve code name
     *
     * @return string
     */
    public function getCode()
    {
        return $this->_code;
    }

    /**
     * Collect process.
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $object
     *
     * @return $this
     */
    public function collect($object)
    {
        $this->_setObject($object);

        return $this;
    }

    /**
     * Fetch
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $object
     *
     * @return $this
     */
    public function fetch($object)
    {
        $this->_setObject($object);

        return $this;
    }

    /**
     * Set the object which can be used inside totals calculation
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $object
     *
     * @return $this
     */
    protected function _setObject($object)
    {
        $this->_object = $object;

        return $this;
    }

    /**
     * Get object
     *
     * @return Klarna_Kco_Model_Api_Builder_Abstract
     */
    protected function _getObject()
    {
        if ($this->_object === null) {
            Mage::throwException(
                Mage::helper('klarna_kco')->__('Object model is not defined.')
            );
        }

        return $this->_object;
    }
}
