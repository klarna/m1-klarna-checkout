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

class Klarna_Kco_Block_Admin_System_Config_Form_Field_Yesno
    extends Mage_Core_Block_Html_Select
{
    /**
     * @return string
     */
    public function _toHtml()
    {
        $options = Mage::getSingleton('adminhtml/system_config_source_yesno')
            ->toOptionArray();
        foreach ($options as $option) {
            $this->addOption($option['value'], $option['label']);
        }

        return parent::_toHtml();
    }

    /**
     * @param $value
     * @return mixed
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }
}