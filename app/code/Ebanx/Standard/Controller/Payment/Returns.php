<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Ebanx\Standard\Controller\Payment;

class Returns extends \Magento\Framework\App\Action\Action
{
    public function execute()
    {
		$hash = $this->getRequest()->getParam('hash');
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$orderPayment = $objectManager->create('Ebanx\Standard\Model\Transaction')
							->getCollection()
							->addFieldToFilter('hash', $hash)
							->getFirstItem();

		if ($orderPayment && $orderPayment->getStatus() == "CA")
        {			
			$this->_redirect('checkout/onepage/failure');
		}else{
			$order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderPayment['order_id']);
			$storeCode = ($order->getStore()->getCode()) ?: 'default';
			$this->_redirect('checkout/onepage/success',array('_store' => $storeCode));
		}
		
    }
}
