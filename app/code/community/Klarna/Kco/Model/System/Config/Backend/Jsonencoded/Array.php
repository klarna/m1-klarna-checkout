<?php
/**
 * Copyright 2019 Klarna Bank AB (publ)
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
 */

/**
 * Klarna backend model to json encode array
 */
class Klarna_Kco_Model_System_Config_Backend_Jsonencoded_Array extends Mage_Core_Model_Config_Data
{
    /**
     * Loading JSON encoded value as array
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterLoad()
    {
        $decodedValue = json_decode($this->getValue(), true);
        $this->setValue($decodedValue);

        return parent::_afterLoad();
    }

    /**
     * Saving array as JSON encoded value
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        /** @var array $values */
        $values          = $this->getValue();
        $filteredValues  = array_filter($values);
        $encodedValues   = json_encode($filteredValues);

        $this->setValue($encodedValues);
        return parent::_beforeSave();
    }
}
