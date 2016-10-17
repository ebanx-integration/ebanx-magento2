<?php 
namespace Ebanx\Standard\Model\ResourceModel\Transaction;
class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
{
    protected function _construct()
    {
        $this->_init('Ebanx\Standard\Model\Transaction', 'Ebanx\Standard\Model\ResourceModel\Transaction');
    }
 
    
}