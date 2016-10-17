<?php
namespace Ebanx\Standard\Controller\Checkout;

use Magento\Payment\Helper\Data as PaymentHelper;
/**
 * Description of Redirect
 */
class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Ebanx\Payment\Model\Config
     */
    //protected $_config;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var PaymentHelper
     */
    protected $_paymentHelper;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Puntopagos\Payment\Model\Config $config
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        PaymentHelper $paymentHelper
    )
    {
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
        $this->_paymentHelper = $paymentHelper;

        parent::__construct($context);
    }

    public function execute()
    {
        try {
			$order = $this->_getCheckoutSession()->getLastRealOrder();
			if($order)
			{
				$method = $order->getPayment()->getMethod();
				$methodInstance = $this->_paymentHelper->getMethodInstance($method);
			}
			
            if ($methodInstance instanceof \Ebanx\Standard\Model\Paymentmethod\Paymentmethod) {
				
                $redirectUrl = $methodInstance->startTransaction($order, $this->_url);
                $this->_redirect($redirectUrl);
            } else {
             
				 throw new \Magento\Framework\Validator\Exception(__('Method is not a Ebanx payment method'));
            }

        } catch (\Exception $e) {
            $this->messageManager->addException($e, __('Something went wrong, please try again later'));
            $this->_logger->critical($e);
            $this->_getCheckoutSession()->restoreQuote();
            $this->_redirect('checkout/cart');
        }
    }

    /**
     * Return checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
}