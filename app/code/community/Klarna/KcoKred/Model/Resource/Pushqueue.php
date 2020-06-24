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
 * Push queue resource
 */
class Klarna_KcoKred_Model_Resource_Pushqueue extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Init
     */
    public function _construct()
    {
        $this->_init('klarna_kcokred/push_queue', 'push_queue_id');
    }

    /**
     * Clean logs
     *
     * @param Mage_Log_Model_Log $object
     *
     * @return $this
     */
    public function clean(Mage_Log_Model_Log $object)
    {
        Mage::dispatchEvent(
            'kco_log_clean_before', array(
            'log' => $object
            )
        );

        $cleanTime = $object->getLogCleanTime();

        $this->_cleanExpiredLogs($cleanTime);
        $this->_cleanClaimedPushes();

        Mage::dispatchEvent(
            'kco_log_clean_after', array(
            'log' => $object
            )
        );

        return $this;
    }

    /**
     * Clean expired logs
     *
     * @param int $cleanTime
     *
     * @return $this
     */
    protected function _cleanExpiredLogs($cleanTime)
    {
        $readAdapter  = $this->_getReadAdapter();
        $writeAdapter = $this->_getWriteAdapter();

        $timeLimit = $this->formatDate(Mage::getModel('core/date')->gmtTimestamp() - $cleanTime);

        while (true) {
            $select = $readAdapter->select()
                ->from(array('push_queue' => $this->getTable('klarna_kcokred/push_queue')))
                ->where('push_queue.update_time < ?', $timeLimit)
                ->limit(100);

            $pushQueueIds = $readAdapter->fetchCol($select);

            if (!$pushQueueIds) {
                break;
            }

            $condition = array('push_queue_id IN (?)' => $pushQueueIds);

            $writeAdapter->delete($this->getTable('klarna_kcokred/push_queue'), $condition);
        }

        return $this;
    }

    /**
     * Clean all push logs that have been acknowledged
     *
     * @return $this
     */
    protected function _cleanClaimedPushes()
    {
        $readAdapter  = $this->_getReadAdapter();
        $writeAdapter = $this->_getWriteAdapter();

        while (true) {
            $select = $readAdapter->select()
                ->from(
                    array('push_queue' => $this->getTable('klarna_kcokred/push_queue'))
                )
                ->joinLeft(
                    array('kco_order' => $this->getTable('klarna_kco/order')),
                    'push_queue.klarna_checkout_id = kco_order.klarna_checkout_id'
                )
                ->where('kco_order.is_acknowledged = ?', 1)
                ->limit(100);

            $pushQueueIds = $readAdapter->fetchCol($select);

            if (!$pushQueueIds) {
                break;
            }

            $condition = array('push_queue_id IN (?)' => $pushQueueIds);

            $writeAdapter->delete($this->getTable('klarna_kcokred/push_queue'), $condition);
        }

        return $this;
    }

    /**
     * Process page data before saving
     *
     * @param Mage_Core_Model_Abstract $object
     *
     * @return $this
     */
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        // modify create / update dates
        if ($object->isObjectNew() && !$object->hasCreationTime()) {
            $object->setCreationTime(Mage::getSingleton('core/date')->gmtDate());
        }

        $object->setUpdateTime(Mage::getSingleton('core/date')->gmtDate());

        return parent::_beforeSave($object);
    }
}
