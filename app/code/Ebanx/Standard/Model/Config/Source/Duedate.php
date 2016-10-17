<?php
namespace Ebanx\Standard\Model\Config\Source;

class Duedate 
{
	protected $_options = array(
			'1' => '1 day'
		  , '2' => '2 days'
		  , '3' => '3 days'
		  , '4' => '4 days'
		  , '5' => '5 days'
		  , '6' => '6 days'
		  , '7' => '7 days'
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