<?php
namespace Ebanx\Express\Model\Config\Source;

class PaymentCurrency 
{
	protected $_options = array(
        'base'  => 'Base currency'
      , 'front' => 'Frontend currency'
    );

    public function toOptionArray()
    {
        $arr = array();

        foreach ($this->_options as $value => $label)
        {
            $arr[] = array(
                'value' => $value
              , 'label' => $label
            );
        }

        return $arr;
    }
}