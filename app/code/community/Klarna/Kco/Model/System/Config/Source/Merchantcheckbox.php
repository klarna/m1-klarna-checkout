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
 * Merchant checkbox support options
 */
class Klarna_Kco_Model_System_Config_Source_Merchantcheckbox
{
    /**
     * Get merchant checkbox option
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper   = Mage::helper('klarna_kco');
        $options  = array(
            array(
                'label' => $helper->__('Disabled'),
                'value' => -1
            )
        );
        $_options = Mage::getConfig()->getNode('klarna/merchant_checkbox');
        if ($_options) {
            foreach ($_options->children() as $option) {
                $options[] = array(
                    'label' => (string)$option->label,
                    'value' => $option->getName()
                );
            }
        }

        return $options;
    }
}
