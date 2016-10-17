<?php

namespace Ebanx\Standard\Model\ResourceModel;
 
class Transaction extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('ebanx_payment', 'id');
    }
}