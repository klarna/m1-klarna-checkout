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
 * Response object from a remote request
 *
 * @method bool getIsSuccessful()
 * @method Klarna_Kco_Model_Api_Response setIsSuccessful($boolean)
 * @method string getTransactionId()
 * @method Klarna_Kco_Model_Api_Response setTransactionId($string)
 */
class Klarna_Kco_Model_Api_Response extends Varien_Object
{
    /**
     * Build the default values for the object
     */
    protected function _construct()
    {
        $this->setData(
            array(
            'is_successful' => false
            )
        );
    }
}
