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
 * @package    Klarna_KcoKred
 * @author     Jason Grim <jason.grim@klarna.com>
 */

/**
 * Order push notification queue item
 *
 * @method int getPushQueueId()
 * @method Klarna_KcoKred_Model_Pushqueue setPushQueueId(int $value)
 * @method string getKlarnaCheckoutId()
 * @method Klarna_KcoKred_Model_Pushqueue setKlarnaCheckoutId(string $value)
 * @method int getCount()
 * @method Klarna_KcoKred_Model_Pushqueue setCount(int $value)
 * @method int getCreationTime()
 * @method Klarna_KcoKred_Model_Pushqueue setCreationTime(int $value)
 * @method int getUpdateTime()
 * @method Klarna_KcoKred_Model_Pushqueue setUpdateTime(int $value)
 */
class Klarna_KcoKred_Model_Pushqueue extends Mage_Core_Model_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kcokred/pushqueue');
    }

    /**
     * Load by checkout id
     *
     * @param string $checkoutId
     *
     * @return Klarna_KcoKred_Model_Pushqueue
     */
    public function loadByCheckoutId($checkoutId)
    {
        return $this->load($checkoutId, 'klarna_checkout_id');
    }
}
