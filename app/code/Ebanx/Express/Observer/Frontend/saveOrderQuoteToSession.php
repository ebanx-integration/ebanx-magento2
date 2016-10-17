<?php
namespace Ebanx\Express\Observer\Frontend;
use Magento\Customer\Model\Session;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
class saveOrderQuoteToSession implements ObserverInterface
{
	protected $_customerSession;
	
    public function __construct(
	Session $customerSession
	)
    {
		$this->_customerSession = $customerSession;
    }

    public function execute(EventObserver $observer)
    {
		$order = $observer->getEvent()->getOrder();
		$payment = $order->getPayment();
		$cpf = $payment->getAdditionalInformation('cpf');
		$bdy = $payment->getAdditionalInformation('birth_date');
		
		if(isset($cpf)){
			$customer = $this->_customerSession->getCustomer();
			if($customer->getEmail())
			{
				$customer->setEbanxCpf($cpf);
				$customer->setDob(implode('-', array_reverse(explode('/', $bdy))));
				$customer->save();
			}
		}		
		return $this;
    }
}
