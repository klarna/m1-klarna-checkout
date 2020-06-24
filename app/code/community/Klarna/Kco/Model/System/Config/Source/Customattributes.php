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
 * @author     Fei Chen <jason.grim@klarna.com>
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/**
 * Organization id custom attribute config source
 */
class Klarna_Kco_Model_System_Config_Source_Customattributes
{
    const ENTITY_CODE_CUSTOMER = 'customer';

    /**
     * Retrieve all user defined customer attributes as array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = array(
            array('label' => 'Unselect', 'value' => 0)
        );
        $type = Mage::getModel('eav/entity_type')->loadByCode(self::ENTITY_CODE_CUSTOMER);
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
            ->addFieldToFilter('is_user_defined', 1)
            ->setEntityTypeFilter($type);

        foreach ($attributes as $option) {
            $options[] = array(
                'label' => $option->getStoreLabel(),
                'value' => $option->getAttributeCode()
            );
        }

        return $options;
    }
}
