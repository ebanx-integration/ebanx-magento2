<?php
namespace Ebanx\Standard\Model\Paymentmethod;

use Magento\Framework\UrlInterface;
use \Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

class PaymentMethod extends AbstractMethod
{
	protected $_isGateway          = true;
    protected $_isInitializeNeeded = true;
	protected $_canRefund          = true;
	protected $_canVoid            = true;
	protected $_canCancel          = true;
	protected $secretKey = null;

    public function initialize($paymentAction, $stateObject)
    {
		$payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        $order->setCanSendNewEmailFlag(false);
    }

    public function startTransaction(Order $order)
	{
		$payment = $order->getPayment();
		$method = $order->getPayment()->getMethod();
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();

		$methodSeceKey = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/'.$method.'/secret_key');
		$this->secretKey = $objectManager->get('\Magento\Framework\Encryption\EncryptorInterface')->decrypt($methodSeceKey);
		$amount = $order->getGrandTotal();

		if (empty($this->secretKey)) {
           throw new \Magento\Framework\Validator\Exception(__('Invalid Key for authorization.'));
        }
		if ($amount <= 0) {
           throw new \Magento\Framework\Validator\Exception(__('Invalid amount for authorization.'));
        }

		$amount = number_format(round($amount), 2, '.', '');
		$quoteId = $order->getQuoteId();
		$quote = $objectManager->get('Magento\Quote\Model\Quote');
		$quote->load($quoteId, 'entity_id');
        $quote->reserveOrderId()->save();
		$helper = $objectManager->create('\Ebanx\Standard\Helper\Data');
		$testMode = $helper->testMode();
		$orderId  = $order->getIncrementId() . ($testMode ? time() : '');
		  // Cut order ID in test mode
		  if (strlen($orderId) > 20 && $testMode)
		  {
			$orderId = substr($orderId, 0, 20);
		  }
		if ($objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/'.$method.'/payment_currency') == 'base')
		{
		  $amountTotal  = $order->getBaseGrandTotal();
		  $currencyCode = $order->getBaseCurrencyCode();
		}
		else
		// Frontend currency
		{
		  $amountTotal  = $order->getGrandTotal();
		  $currencyCode = $order->getOrderCurrency()->getCurrencyCode();
		}
		$email = $order->getCustomerEmail() ?: $order->getBillingAddress()->getEmail();
		$streetNumber = preg_replace('/[\D]/', '', $order->getBillingAddress()->getData('street'));
		$streetNumber = ($streetNumber > 0) ? $streetNumber : '1';

		//Due Date use for Print of order
		$dueDays = $objectManager->get('Magento\Framework\App\Config\ScopeConfigInterface')->getValue('payment/'.$method.'/due_date');
		$dueDate = date('d/m/Y', strtotime('+' . intval($dueDays).'days'));

		if (!$order->getCustomerFirstname()) {
				$customerName = $order->getBillingAddress()->getName();
		} else {
				$customerName = $order->getCustomerFirstname() . ' ' . $order->getCustomerLastname();
		}
		$params = [];
		$params = array(
			  'name'              => $customerName
			, 'email'             => $email
			, 'phone_number'      => $order->getBillingAddress()->getTelephone()
			, 'currency_code'     => $currencyCode
			, 'amount'            => $amountTotal
			, 'payment_type_code' => '_all'
			, 'merchant_payment_code' => $orderId
			, 'order_number'      => $order->getIncrementId()
			, 'due_date'          => $dueDate
			, 'zipcode'           => $order->getBillingAddress()->getData('postcode')
			, 'address'           => $order->getBillingAddress()->getData('street')
			, 'street_number'     => $streetNumber
			, 'city'              => $order->getBillingAddress()->getData('city')
			, 'state'             => $order->getBillingAddress()->getRegionCode()
			, 'country'           => $order->getBillingAddress()->getCountryId()
			, 'instalments'       => '1-12'
		);
		try
		{
			$helper = $objectManager->create('\Ebanx\Standard\Helper\Data');
			$response = $helper->request($params);

			if (!empty($response) && $response->status == 'SUCCESS')
			{

				$hash = $response->payment->hash;
				$payment->setTransactionId($hash)
						->setIsTransactionClosed(0);
				$transaction = $objectManager->create('Ebanx\Standard\Model\Transaction');
                $transaction->setData('hash', $hash);
				$transaction->setData('order_id', $response->payment->order_number);
				$transaction->setData('status', $response->payment->status);
				$transaction->setData('open_date',$response->payment->open_date);
				$transaction->setData('due_date',$response->payment->due_date);
				$transaction->setData('instalments',$response->payment->instalments);
				$transaction->setData('payment_method', $response->payment->payment_type_code);
				$transaction->setData('merchant_payment_code', $response->payment->merchant_payment_code);
				$transaction->setData('amount', $response->payment->amount_ext);
				$transaction->save();

				// Redirect to bank page if the client chose TEF
				if (isset($response->redirect_url))
				{
				  $redirectUrl = $response->redirect_url;
				  return $redirectUrl;
				}
				// Redirect to EBANX success page on client store
				else
				{
				 $redirectUrl2 = $this->getUrl('checkout/onepage/success') . '?hash=' . $hash;
				  return $redirectUrl2;
				}

			} else
			{
				$paym = 'Authorizing order [' . $order->getIncrementId() . '] - error: ' . $response->status_message;
				$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/payment.log');
				$logger = new \Zend\Log\Logger();
				$logger->addWriter($writer);
				$logger->info($paym);
			 	throw new \Magento\Framework\Validator\Exception(__('Payment error.' .$this->getEbanxErrorMessage($response->status_code)));
			}
		}catch (Exception $e)
		{
			$this->debugData(['exception' => $e->getMessage()]);
		}
    }

	public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
	{
	  $hash = $payment->getParentTransactionId();
	  $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	  $helper = $objectManager->create('\Ebanx\Standard\Helper\Data');
	  $response = $helper->refund($hash,$amount);
	  $payment->setTransactionId($hash . '-' . \Magento\Sales\Model\Order\Payment\Transaction::TYPE_REFUND)
		->setParentTransactionId($hash)
		->setIsTransactionClosed(1)
		->setShouldCloseParentTransaction(1);
		return $this;
	}

	public function void(\Magento\Payment\Model\InfoInterface $payment)
	{
      parent::void($payment);
	   $hash = $payment->getParentTransactionId();
	   $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	   $helper = $objectManager->create('\Ebanx\Standard\Helper\Data');
	   $response = $helper->cancel($hash);
	   return $this;
	}

	public function cancel(\Magento\Payment\Model\InfoInterface $payment)
	{
      return $this->void($payment);
	}
 	protected function getEbanxErrorMessage($errorCode)
	{
      $errors = array(
          "BP-DR-1"  => "O modo deve ser full ou iframe"
        , "BP-DR-2"  => "É necessário selecionar um método de pagamento"
        , "BP-DR-3"  => "É necessário selecionar uma moeda"
        , "BP-DR-4"  => "A moeda não é suportada pelo EBANX"
        , "BP-DR-5"  => "É necessário informar o total do pagamento"
        , "BP-DR-6"  => "O valor do pagamento deve ser maior do que X"
        , "BP-DR-7"  => "O valor do pagamento deve ser menor do que"
        , "BP-DR-8"  => "O valor total somado ao valor de envio deve ser igual ao valor total"
        , "BP-DR-13" => "É necessário informar um nome"
        , "BP-DR-14" => "O nome não pode conter mais de 100 caracteres"
        , "BP-DR-15" => "É necessário informar um email"
        , "BP-DR-16" => "O email não pode conter mais de 100 caracteres"
        , "BP-DR-17" => "O email informado é inválido"
        , "BP-DR-18" => "O cliente está suspenso no EBANX"
        , "BP-DR-19" => "É necessário informar a data de nascimento"
        , "BP-DR-20" => "A data de nascimento deve estar no formato dd/mm/aaaa"
        , "BP-DR-21" => "É preciso ser maior de 16 anos"
        , "BP-DR-22" => "É necessário informar um CPF ou CNPJ"
        , "BP-DR-23" => "O CPF informado não é válido"
        , "BP-DR-24" => "É necessário informar um CEP"
        , "BP-DR-25" => "É necessário informar o endereço"
        , "BP-DR-26" => "É necessário informar o número do endereço"
        , "BP-DR-27" => "É necessário informar a cidade"
        , "BP-DR-28" => "É necessário informar o estado"
        , "BP-DR-29" => "O estado informado é inválido. Deve se informar a sigla do estado (ex.: SP)"
        , "BP-DR-30" => "O código do país deve ser 'br'"
        , "BP-DR-31" => "É necessário informar um telefone"
        , "BP-DR-32" => "O telefone informado é inválido"
        , "BP-DR-33" => "Número de parcelas inválido"
        , "BP-DR-34" => "Número de parcelas inválido"
        , "BP-DR-35" => "Método de pagamento inválido: X"
        , "BP-DR-36" => "O método de pagamento não está ativo"
        , "BP-DR-39" => "CPF, nome e data de nascimento não combinam"
        , "BP-DR-40" => "Cliente atingiu o limite de pagamentos para o período"
        , "BP-DR-41" => "Deve-se escolher um tipo de pessoa - física ou jurídica."
        , "BP-DR-42" => "É necessário informar os dados do responsável pelo pagamento"
        , "BP-DR-43" => "É necessário informar o nome do responsável pelo pagamento"
        , "BP-DR-44" => "É necessário informar o CPF do responsável pelo pagamento"
        , "BP-DR-45" => "É necessário informar a data de bascunebti do responsável pelo pagamento"
        , "BP-DR-46" => "CPF, nome e data de nascimento do responsável não combinam"
        , "BP-DR-47" => "A conta bancário deve conter no máximo 10 caracteres"
        , "BP-DR-48" => "É necessário informar os dados do cartão de crédito"
        , "BP-DR-49" => "É necessário informar o número do cartão de crédito"
        , "BP-DR-51" => "É necessário informar o nome do titular do cartão de crédito"
        , "BP-DR-52" => "O nome do titular do cartão deve conter no máximo 50 caracteres"
        , "BP-DR-54" => "É necessário informar o CVV do cartão de crédito"
        , "BP-DR-55" => "O CVV deve conter no máximo 4 caracteres"
        , "BP-DR-56" => "É necessário informar a data de venciomento do cartão de crédito"
        , "BP-DR-57" => "A data de vencimento do cartão de crédito deve estar no formato dd/mm/aaaa"
        , "BP-DR-58" => "A data de vencimento do boleto é inválida"
        , "BP-DR-59" => "A data de vencimento do boleto é menor do que o permitido"
        , "BP-DR-61" => "Não foi possível criar um token para este cartão de crédito"
        , "BP-DR-62" => "Pagamentos recorrentes não estão habilitados para este merchant"
        , "BP-DR-63" => "Token não encontrado para este adquirente"
        , "BP-DR-64" => "Token não encontrado"
        , "BP-DR-65" => "O token informado já está sendo utilizado"
        , "BP-DR-66" => "Token inválido. O token deve ter entre 32 e 128 caracteres"
        , "BP-DR-67" => "A data de venciomento do cartão de crédito é inválida"
        , "BP-DR-68" => "É necessário informar o número da conta bancária"
        , "BP-DR-69" => "A conta bancária não pode conter mais de 10 caracteres"
        , "BP-DR-70" => "É necessário informar a agência bancária"
        , "BP-DR-71" => "O código do banco não pode ter mais de 5 caracteres"
        , "BP-DR-72" => "É necessário informar o código do banco"
        , "BP-DR-73" => "É necessário informar os dados da conta para débito em conta"
        , "BP-R-1" => "É necessário informar a moeda"
        , "BP-R-2" => "É necessário informar o valor do pagamento"
        , "BP-R-3" => "É necessário informar o código do pedido"
        , "BP-R-4" => "É necessário informar o nome"
        , "BP-R-5" => "É necessário informar o email"
        , "BP-R-6" => "É necessário selecionar o método de pagamento"
        , "BP-R-7" => "O método de pagamento não está ativo"
        , "BP-R-8" => "O método de pagamento é inválido"
        , "BP-R-9" => "O valor do pagamento deve ser positivo: X"
        , "BP-R-10" => "O valor do pagamento deve ser maior do que X"
        , "BP-R-11" => "O método de pagamento não suporta parcelamento"
        , "BP-R-12" => "O número máximo de parcelas é X. O valor informado foi de X parcelas."
        , "BP-R-13" => "O valor mínimo das parcelas é de R$ X."
        , "BP-R-17" => "O pagamento não está aberto"
        , "BP-R-18" => "O típo de pessoa é inválido"
        , "BP-R-19" => "O checkout com CNPJ não está habilitado"
        , "BP-R-20" => "A data de vencimento deve estar no formato dd/mm/aaaa"
        , "BP-R-21" => "A data de vencimento é inválida"
        , "BP-R-22" => "A data de vencimento é inválida"
        , "BP-R-23" => "A moeda não está ativa no sistema"
        , "BP-ZIP-1" => "O CEP não foi informado"
        , "BP-ZIP-2" => "O CEP não é válido"
        , "BP-ZIP-3" => "O endereço não pode ser encontrado"
      );

      if (array_key_exists($errorCode, $errors))
      {
          return $errors[$errorCode];
      }

      return 'Ocorreu um erro desconhecido. Por favor contacte o administrador.';
  }
}
