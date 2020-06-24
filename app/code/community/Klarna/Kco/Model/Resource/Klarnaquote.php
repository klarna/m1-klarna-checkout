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
 * Klarna quote resource
 */
class Klarna_Kco_Model_Resource_Klarnaquote extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kco/quote', 'kco_quote_id');
    }

    /**
     * Load only active quote
     *
     * @param Klarna_Kco_Model_Klarnaquote $klarnaQuote
     * @param int                          $quoteId
     *
     * @return Mage_Sales_Model_Resource_Quote
     */
    public function loadActive($klarnaQuote, $quoteId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $this->_getLoadSelect('quote_id', $quoteId, $klarnaQuote)
            ->where('is_active = ?', 1);

        $data = $adapter->fetchRow($select);
        if ($data) {
            $klarnaQuote->setData($data);
        }

        $this->_afterLoad($klarnaQuote);

        return $this;
    }
}
