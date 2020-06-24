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
 * Customer group config source
 */
class Klarna_Kco_Model_System_Config_Source_Customergroup
{
    /**
     * Customer groups options array
     *
     * @var array
     */
    protected $_options;

    /**
     * Retrieve customer groups as array
     *
     * @return array
     */
    public function toOptionArray()
    {
        if (null === $this->_options) {
            $this->_options = Mage::getResourceModel('customer/group_collection')->toOptionArray();
            array_unshift($this->_options, array('value' => -1, 'label' => ''));
        }

        return $this->_options;
    }
}
