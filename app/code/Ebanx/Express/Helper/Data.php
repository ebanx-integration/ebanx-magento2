<?php
namespace Ebanx\Express\Helper;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Backend\App\Action;
class Data extends \Magento\Framework\App\Helper\AbstractHelper {
	
	protected $_scopeConfig;
	protected $testMode;
	protected $secretKey;
	protected $_objectManager;
	public function __construct(
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Framework\ObjectManagerInterface $objectManager,
		\Ebanx\Ebanx $ebanx
        
   ) {
	   $this->_scopeConfig = $scopeConfig;
	   $this->_objectManager = $objectManager;
	   $this->_ebanxConfig();
	}
	
	public function testMode(){
		$this->testMode = $this->_scopeConfig->getValue('payment/express/test_mode', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		return (intval($this->testMode) == 1);
	}
	
	public function secretKey(){
		$sKey = $this->_scopeConfig->getValue('payment/express/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
		$this->secretKey = $this->_objectManager->get('\Magento\Framework\Encryption\EncryptorInterface')->decrypt($sKey);
		return $this->secretKey;
	}

	private function _ebanxConfig(){
		\Ebanx\Config::set([
				'integrationKey' =>$this->secretKey()
			  , 'testMode' => $this->testMode()
			  , 'directMode' => true	  
			]);
		\Ebanx\Config::setDirectMode(true);
	}
	
	public function request($params)
    {
		if($params){
			$request = \Ebanx\Ebanx::doRequest($params);
			return $request;
		}
    }
	
	public function doQuery($hash)
    {
		$request = \Ebanx\Ebanx::doQuery(array('hash' => $hash));
		return $request;
    }
	
	public function capture($request,$key)
    { 
		$response = \Ebanx\Ebanx::doCapture(array(
			'hash' => $request->payment->hash
		));

		return $response;
    }
	public function refund($hash,$amount)
    {
		$response = \Ebanx\Ebanx::doRefund(array(
			  'hash'      => $hash
			, 'operation' => 'request'
			, 'amount'    => $amount
			, 'description' => 'Order refunded'
		));
	  return $response;
	}
	 
	public function cancel($hash)
    {
		$response = \Ebanx\Ebanx::doRefundOrCancel(array(
          'hash' => $hash
        , 'description' => 'The order has been cancelled.'
      ));
	  return $response;
	}
	
	public function calculateTotalWithInterest($orderTotal, $installment)
    {
        $helper = $this->_objectManager->create('\Ebanx\Express\Model\Config\Source\Installment');
        $total = (floatval($helper->toOptionArray()[$installment]['interest_rate'] / 100) * floatval($orderTotal) + floatval($orderTotal));

        return $total;
     } 
}