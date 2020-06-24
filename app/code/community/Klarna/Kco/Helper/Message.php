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
 * Klarna KCO gift message helper
 */
class Klarna_Kco_Helper_Message extends Mage_GiftMessage_Helper_Message
{
    /**
     * Retrieve inline gift message edit form for specified entity
     *
     * @param string        $type
     * @param Varien_Object $entity
     * @param boolean       $dontDisplayContainer
     *
     * @return string
     */
    public function getInline($type, Varien_Object $entity, $dontDisplayContainer = false)
    {
        $html = parent::getInline($type, $entity, $dontDisplayContainer);

        if (!empty($html)) {
            $block = Mage::getSingleton('core/layout')->createBlock('giftmessage/message_inline')
                ->setId('giftmessage_form_' . $this->_nextId++)
                ->setDontDisplayContainer($dontDisplayContainer)
                ->setEntity($entity)
                ->setType($type)
                ->setTemplate('klarna/gift/message.phtml');

            $html = $block->toHtml();
        }

        return $html;
    }
}
