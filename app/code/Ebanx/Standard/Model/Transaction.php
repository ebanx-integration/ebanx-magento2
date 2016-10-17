<?php 
namespace Ebanx\Standard\Model;
use Magento\Framework\Model\AbstractModel;
class Transaction extends AbstractModel
{
    protected function _construct(){
        $this->_init('Ebanx\Standard\Model\ResourceModel\Transaction');
    }	
}
