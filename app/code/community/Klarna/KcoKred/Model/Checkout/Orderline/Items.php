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
 * Generate order lines for order items
 */
class Klarna_KcoKred_Model_Checkout_Orderline_Items extends Klarna_Kco_Model_Checkout_Orderline_Items
{
    /**
     * Add order details to checkout request
     *
     * @param Klarna_Kco_Model_Api_Builder_Abstract $checkout
     *
     * @return $this
     */
    public function fetch($checkout)
    {
        if ($checkout->getItems()) {
            foreach ($checkout->getItems() as $item) {
                $item = new Varien_Object($item);
                $checkout->addOrderLine(
                    array(
                        'reference'   => $this->getEncodedText($item->getReference()),
                        'name'        => $this->getEncodedText($item->getName()),
                        'quantity'    => (int)$item->getQuantity(),
                        'unit_price'  => (int)$item->getUnitPrice(),
                        'tax_rate'    => (int)$item->getTaxRate(),
                        'product_url' => $item->getProductUrl(),
                        'image_url'   => $item->getImageUrl()
                    )
                );
            }
        }

        return $this;
    }

    /**
     * Converts non-UTF8 characters to a question mark (?) or mapped values based on rules
     *
     * @param string $text
     * @return string
     */
    private function getEncodedText($text)
    {
        $result = array();

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        $mapping = array(
            '€' => 'EUR',
            '„' => '"',
            '‘' => "'",
            '’' => "'",
            '“' => '"',
            '”' => '"',
            '•' => '-',
            '–' => '-',
            '—' => '-',
            '˜' => '-',
            '™' => '(TM)',
            '›' => '>',
            '‹' => '<'
        );

        foreach ($characters as $character) {
            if (array_key_exists($character, $mapping)) {
                $character = $mapping[$character];
            } else {
                $encoded = mb_convert_encoding($character, 'Latin1');
                if ($encoded != $character) {
                    $encoded = '?';
                }

                $character = $encoded;
            }

            $result[] = $character;
        }

        return implode($result);
    }
}
