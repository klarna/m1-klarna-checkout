<?php
/**
 * Copyright 2016 Klarna AB
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
 * Klarna DACH Data Helper
 */
class Klarna_Kco_Helper_Dach extends Mage_Core_Helper_Abstract
{
    /**
     * Determine if the pre-fill notice is enabled
     *
     * @var Mage_Core_Model_Store $store
     *
     * @return bool
     */
    public function isPrefillNoticeEnabled($store = null)
    {
        return Mage::helper('customer')->isLoggedIn()
        && $this->getCheckoutHelper()->getCheckoutConfigFlag('prefill_notice', $store)
        && $this->getCheckoutHelper()->getCheckoutConfigFlag('merchant_prefill', $store);
    }

    /**
     * Get checkout helper
     *
     * @return Klarna_Kco_Helper_Checkout
     */
    public function getCheckoutHelper()
    {
        return Mage::helper('klarna_kco/checkout');
    }
}
