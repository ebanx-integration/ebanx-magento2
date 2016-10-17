<?php
namespace Ebanx\Standard\Controller\Payment;

class Notify extends \Magento\Framework\App\Action\Action
{
	protected $_objectManager;
	protected $_transactionFactory;
	protected $_dataHelper;
	protected $_scopeConfig;
 
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
		\Ebanx\Standard\Model\TransactionFactory $transactionFactory,
		\Ebanx\Standard\Helper\Data $dataHelper,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    )
    {
		parent::__construct($context);
		$this->_objectManager = $context->getObjectManager();
		$this->_transactionFactory = $transactionFactory;
		$this->_dataHelper = $dataHelper;
		$this->_scopeConfig = $scopeConfig;
				
    } 

    public function execute()
    {		
		$hash = $this->getRequest()->getParam('hash_codes');
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/hash.log'); 
				$logger = new \Zend\Log\Logger();
				$logger->addWriter($writer); 
				$logger->info($hash);
        if (isset($hash) && $hash != null) 
        {
			//foreach($hash as $hashs){
				$orderPayment = $this->_transactionFactory->create()->getCollection()
                                    ->addFieldToFilter('hash', $hash)
                                    ->getFirstItem();
				$response = $this->_dataHelper->doQuery($hash);
				
				//Order Status changes
				if ($orderPayment && $response->status == 'SUCCESS')
                {
				  try
                  {
					$orderPayment->setData('status', $response->payment->status);
					$orderPayment->setData('amount', $response->payment->amount_ext);
					  $orderPayment->setData('payment_method', $response->payment->payment_type_code);
					$orderPayment->save();
					
					// Get the new status from Magento
					$orderStatus = $this->_getOrderStatus($response->payment->status);
					
				    $order = $this->_objectManager->create('Magento\Sales\Model\Order')->loadByIncrementId($orderPayment['order_id']); 
					
					if (!$order->getRealOrderId())
                    {
                      throw new \Exception('Order cannot be found.');
                    }
					
					 // If payment status is CA - Canceled - AND order can be cancelled
                    if ($response->payment->status == 'CA' && $order->canCancel())
                    {
                      if (!$order->canCancel())
                      {
                        throw new \Exception('Order cannot be canceled, assuming already processed.');
                      }

                      // Cancel order
                      $order->cancel();

                      // Set orderStatus to Generic canceled status - nothing more to do
                      $orderStatus = 'canceled';

                        // Comment on order
                      $order->addStatusHistoryComment('Automatically CANCELED by EBANX Notification.', false);
                    }
					
					// If payment status is CO - Paid - AND order can be invoiced
                    if ($response->payment->status == 'CO')
                    {
					  $orderPayment->setData('confirm_date',date('Y-m-d H:i:s'));
					  $orderPayment->save();
              
					  // If can NOT Invoice or order is not new
                      if (!$order->canInvoice())
                      {
                        throw new \Exception('Order cannot be invoiced, assuming already processed.');
                      }
					  
                      // Invoice
					  $payment = $order->getPayment();
					  $skipFraudDetection = false;
					  $payment->setTransactionId($hash)
								->setParentTransactionId($hash)
								->registerCaptureNotification($response->payment->amount_ext);
					  $order->save();		
					  $invoice = $payment->getCreatedInvoice();
						// notify customer
					  if ($invoice && !$order->getEmailSent()) {
						$this->_objectManager->create('\Magento\Sales\Model\Order\Email\Sender\InvoiceSender')->send($invoice);
						$order->addStatusHistoryComment(
							__('You notified customer about invoice #%1.', $invoice->getIncrementId())
						)->setIsCustomerNotified(
							true 
						)->save();
					  }
                    }
					 
				   // Set status
                    $order->addStatusToHistory($orderStatus, 'Status changed by EBANX Notification.', false)
                          ->save();
				   echo 'OK: payment ' . $hash . ' was updated<br>';
				   
				  }catch (Exception $e)
                  {
                    echo 'NOK: payment ' . $hash . ' could not update order, Exception: '  . $e->getMessage() . '<br>';
                  }	
				}
			//}
		}
		else
        {
          echo 'NOK: empty request.';
        }
    }
	
	/**
     * Get the new order status from Magento
     * @param  string $status The EBANX order status code
     * @return string The Magento order status
     */
    protected function _getOrderStatus($status)
    {
        $orderStatus = array(
            'CO' => $this->_scopeConfig->getValue('payment/standard/paid_order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
          , 'PE' => $this->_scopeConfig->getValue('payment/standard/order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
          , 'CA' => $this->_scopeConfig->getValue('payment/standard/cancelled_order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
          , 'OP' => $this->_scopeConfig->getValue('payment/standard/order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)
        );
		
        return $orderStatus[$status];
    }
	
}