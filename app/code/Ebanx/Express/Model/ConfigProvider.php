<?php
/**
 * Copyright © 2015 Ebanx All rights reserved.
 */

namespace Ebanx\Express\Model;

use Magento\Framework\Locale\Bundle\DataBundle;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Escaper;
use Magento\Payment\Model\CcConfig;
use Magento\Framework\View\Asset\Source;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\Session;



class ConfigProvider implements ConfigProviderInterface
{
	/**
     * Years range
     */
    const YEARS_RANGE = 20;
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'express'
    ];

	protected $_ccoptions = array(
		   'mastercard' => 'Mastercard'
		  , 'visa' => 'Visa'
		  , 'aura' => 'Aura'
		  , 'amex' => 'American Express'
		  , 'diners' => 'Diners'
		  , 'discover' => 'Discover'
		  , 'elo' => 'Elo'
		  , 'hipercard' => 'Hipercard'
		);
    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @param PaymentHelper $paymentHelper
     * @param Escaper $escaper
     */
	 /**
     * @var array
     */
    private $icons = [];

    /**
     * @var CcConfig
     */
    protected $ccConfig;
	protected $localeResolver;
	protected $_date;
	protected $_helper;
	protected $_priceCurrency;
	protected $_checkoutSession;
	protected $scopeConfig;
    /**
     * @var \Magento\Framework\View\Asset\Source
     */
    protected $assetSource;

    public function __construct(
        PaymentHelper $paymentHelper,
        Escaper $escaper,
		CcConfig $ccConfig,
        Source $assetSource,
		\Magento\Framework\Locale\ResolverInterface $localeResolver,
		\Magento\Framework\Stdlib\DateTime\DateTime $date,
		\Ebanx\Express\Helper\Data $helper,
		\Magento\Framework\Pricing\PriceCurrencyInterface $priceCurrency,
		Session $customerSession,
		\Magento\Checkout\Model\Session $_checkoutSession,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
		
		
    ) {
		$this->ccConfig = $ccConfig;
        $this->assetSource = $assetSource;
        $this->escaper = $escaper;
		$this->localeResolver = $localeResolver;
		$this->_date = $date;
		$this->_helper = $helper;
		$this->_priceCurrency = $priceCurrency;
		$this->_customerSession = $customerSession;
		$this->_checkoutSession = $_checkoutSession;
		$this->scopeConfig = $scopeConfig;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('\Ebanx\Express\Model\Config\Source\Installment');

        $config = [];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment'][$code]['instructions'] = $this->getInstructions($code);
				$config['payment'][$code]['ccavailabletypes'] = $this->getCcAvailableTypes();
				$config['payment'][$code]['years'] = $this->getYears();
				$config['payment'][$code]['months'] = $this->getMonths();
				$config['payment'][$code]['icons'] = $this->getIcons($code);
                $config['payment'][$code]['installments'] = $this->getInstallments($code);
				$config['payment'][$code]['installments_fees'] = $helper->instalmentWithRate();
				$config['payment'][$code]['installments_active'] = $this->getInstallmentsActive();
				$config['payment'][$code]['cpf'] = $this->getEbanxCpf($code);
				$config['payment'][$code]['birth_date'] = $this->getBirthDate($code);
				$config['payment'][$code]['currency'] = $this->getCurrencyData($code);
			}
        }
		
        return $config;
    }

    protected function getInstructions($code)
    {
        return nl2br($this->escaper->escapeHtml('Payment methods for Brazil, Mexico and Peru'));
    }
	
	protected function getCcAvailableTypes()
    {
        return $this->_ccoptions;
    }
	public function getIcons($code)
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }

        $types = $this->_ccoptions;
        foreach (array_keys($types) as $code) {
			
            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->ccConfig->createAsset('Ebanx_Express::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->assetSource->findSource($asset);
                if ($placeholder) {
                    list($width, $height) = getimagesize($asset->getSourceFile());
                    $this->icons[$code] = [
                        'url' => $asset->getUrl(),
                        'width' => $width,
                        'height' => $height
                    ];
                }
				
            }
        }
        return $this->icons;
    }
	
	public function getMonths()
    {
        $data = [];
        $months = (new DataBundle())->get(
            $this->localeResolver->getLocale()
        )['calendar']['gregorian']['monthNames']['format']['wide'];
        foreach ($months as $key => $value) {
            $monthNum = ++$key < 10 ? '0' . $key : $key;
            $data[$key] = $monthNum . ' - ' . $value;
        }
        return $data;
    }
	
	public function getYears()
    {
        $years = [];
        $first = (int)$this->_date->date('Y');
        for ($index = 0; $index <= self::YEARS_RANGE; $index++) {
            $year = $first + $index;
            $years[$year] = $year;
        }
        return $years;
    }
	public function getFinalValue()
    {
		$cartData = $this->_checkoutSession->getQuote()->collectTotals()
                ->getGrandTotal();
				return $cartData;
	}
	
	public function getInstallments($code)
    {
	    $installments = $this->scopeConfig->getValue('payment/express/installments');
		$maxInstallments  = explode(',',$installments);
		return $maxInstallments;
	}
	public function getInstallmentsActive()
    {
	    $installmentActive= $this->scopeConfig->getValue('payment/express/installment_setup');
		return $installmentActive;
	}
	public function getCurrencyData($code)
    {
	    $currencySymbol = $this->_priceCurrency->getCurrency()->getCurrencySymbol();
		return $currencySymbol;
	}

	public function getEbanxCpf($code){
		
		$customer = $this->_customerSession->getCustomer();
		$cpf = $customer->getEbanxCpf();
		return $cpf;
	}
	public function getBirthDate($code){
		
		$customer = $this->_customerSession->getCustomer();
		$birthDate = $customer->getDob();
		if($birthDate){ 
		$ebanxBdy = explode('-',$birthDate);
		$birthDate = str_pad($ebanxBdy[2],   2, '0', STR_PAD_LEFT) . '/'
                     . str_pad($ebanxBdy[1], 2, '0', STR_PAD_LEFT) . '/'
                     . $ebanxBdy[0];
		}
		return $birthDate;
	}
}
