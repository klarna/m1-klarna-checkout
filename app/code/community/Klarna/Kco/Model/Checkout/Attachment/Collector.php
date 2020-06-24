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
 * Klarna kco attachment collector
 */
class Klarna_Kco_Model_Checkout_Attachment_Collector
{
    /**
     * Corresponding store object
     *
     * @var Mage_Core_Model_Store
     */
    protected $_store;

    /**
     * Sorted models
     *
     * @var array
     */
    protected $_collectors = array();

    /**
     * Init corresponding models
     *
     * @param array $options
     */
    public function __construct($options)
    {
        if (isset($options['store'])) {
            $this->_store = $options['store'];
        } else {
            $this->_store = Mage::app()->getStore();
        }

        $this->_initCollectors();
    }

    /**
     * Get models for calculation logic
     *
     * @return array
     */
    public function getCollectors()
    {
        return $this->_collectors;
    }

    /**
     * Initialize models configuration and objects
     *
     * @return Klarna_Kco_Model_Checkout_Attachment_Collector
     *
     * @throws Mage_Core_Exception
     */
    protected function _initCollectors()
    {
        $checkoutType = Mage::helper('klarna_kco/checkout')->getCheckoutType($this->_store);
        $totalsConfig = Mage::getConfig()->getNode(sprintf('klarna/attachment/%s', $checkoutType));

        if (!$totalsConfig) {
            return $this;
        }

        foreach ($totalsConfig->children() as $totalCode => $totalConfig) {
            $class = $totalConfig->getClassName();
            if (!empty($class)) {
                $this->_collectors[$totalCode] = $this->_initModelInstance($class, $totalCode);
            }
        }

        return $this;
    }


    /**
     * Init model class by configuration
     *
     * @param $class
     *
     * @param $totalCode
     *
     * @return false|Klarna_Kco_Model_Checkout_Attachment_Abstract
     *
     * @throws Mage_Core_Exception
     */
    protected function _initModelInstance($class, $totalCode)
    {
        $model = Mage::getModel($class);
        if (!$model instanceof Klarna_Kco_Model_Checkout_Attachment_Abstract) {
            Mage::throwException(
                Mage::helper('klarna_kco')
                    ->__('The order item model should be extended from Klarna_Kco_Model_Checkout_Attachment_Abstract.')
            );
        }

        $model->setCode($totalCode);
        return $model;
    }
}
