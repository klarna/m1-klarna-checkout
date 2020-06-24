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
 * Class Klarna_Kco_Block_Dach_Prefillnotice
 */
class Klarna_Kco_Block_Dach_Prefillnotice extends Mage_Core_Block_Template
{
    /**
     * Disable cache for block
     */
    public function _construct()
    {
        $this->setCacheLifetime(null);
    }

    /**
     * Check if notice is enabled before rending block html
     */
    protected function _toHtml()
    {
        if ($this->getCheckoutSession()->getKlarnaFillNoticeTerms()
            || !$this->helper('klarna_kco/dach')->isPrefillNoticeEnabled()
        ) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * Get Klarna terms url
     *
     * @return string
     */
    public function getUserTermsUrl()
    {
        $merchantId = $this->getCheckoutHelper()->getPaymentConfig('merchant_id');
        $locale     = strtolower(Mage::app()->getLocale()->getLocaleCode());

        return sprintf('https://cdn.klarna.com/1.0/shared/content/legal/terms/%s/%s/checkout', $merchantId, $locale);
    }

    /**
     * Get url to continue to checkout
     */
    public function getAcceptTermsUrl()
    {
        $urlParams = array(
            '_nosid'         => true,
            '_forced_secure' => true
        );

        return Mage::getUrl('*/*/*/terms/accept', $urlParams);
    }

    /**
     * Get checkout session
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::helper('checkout')->getCheckout();
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
