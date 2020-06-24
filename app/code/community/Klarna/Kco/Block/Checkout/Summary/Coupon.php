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
 * Klarna checkout summary coupon
 */
class Klarna_Kco_Block_Checkout_Summary_Coupon extends Klarna_Kco_Block_Checkout_Summary_Abstract
{
    /**
     * Get the current coupon code applied to quote
     *
     * @return string
     */
    public function getCouponCode()
    {
        return $this->getQuote()->getCouponCode();
    }

    /**
     * Return form action url
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('checkout/klarna/couponPost', array('_secure' => true));
    }
}
