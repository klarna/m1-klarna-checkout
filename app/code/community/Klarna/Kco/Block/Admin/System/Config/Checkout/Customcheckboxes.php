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

class Klarna_Kco_Block_Admin_System_Config_Checkout_Customcheckboxes
    extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{
    /**
     * prepare render elements
     */
    public function _prepareToRender()
    {
        $this->addColumn(
            'id',
            array(
                'label' => __('Checkbox Id'),
                'style' => 'width:120px'
            )
        );

        $this->addColumn(
            'checked',
            array(
                'label' => __('Checked By Default'),
                'renderer' => $this->_getRendererChecked(),
            )
        );

        $this->addColumn(
            'required',
            array(
                'label' => __('Required By Default'),
                'renderer' => $this->_getRendererRequired()
            )
        );

        $this->addColumn(
            'text',
            array(
                'label' => __('Checkbox Text'),
                'style' => 'width:200px'
            )
        );

        $this->_addAfter = false;
        $this->_addButtonLabel = Mage::helper('klarna_kco')->__('Add');
    }

    /**
     * @return Mage_Core_Block_Abstract|mixed
     */
    protected function _getRendererChecked()
    {
        if (!$this->_itemRendererChecked) {
            $this->_itemRendererChecked = $this->getLayout()->createBlock(
                'klarna_kco/admin_system_config_form_field_yesno', '',
                array('is_render_to_js_template' => true)
            );
        }

        return $this->_itemRendererChecked;
    }

    /**
     * @return Mage_Core_Block_Abstract|mixed
     */
    protected function  _getRendererRequired()
    {
        if (!$this->_itemRendererRequired) {
            $this->_itemRendererRequired = $this->getLayout()->createBlock(
                'klarna_kco/admin_system_config_form_field_yesno', '',
                array('is_render_to_js_template' => true)
            );
        }

        return $this->_itemRendererRequired;
    }

    /**
     * @param Varien_Object $row
     */
    protected function _prepareArrayRow(Varien_Object $row)
    {
        $row->setData(
            'option_extra_attr_' . $this->_getRendererChecked()
                ->calcOptionHash($row->getData('checked')),
            'selected="selected"'
        );

        $row->setData(
            'option_extra_attr_' . $this->_getRendererRequired()
                ->calcOptionHash($row->getData('required')),
            'selected="selected"'
        );
    }
}
