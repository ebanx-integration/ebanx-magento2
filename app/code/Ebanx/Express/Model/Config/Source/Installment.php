<?php
namespace Ebanx\Express\Model\Config\Source;

class Installment 
{
	protected $_options = array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

    public function toOptionArray()
    {
        $arr = array(
            1 => array(
                'interest_rate' => 1
            ),
            2 => array(
                'interest_rate' => 2.30
            ),
            3 => array(
                'interest_rate' => 3.40
            ),
            4 => array(
                'interest_rate' => 4.50
            ),
            5 => array(
                'interest_rate' => 5.60
            ),
            6 => array(
                'interest_rate' => 6.70
            ),
            7 => array(
                'interest_rate' => 7.80
            ),
            8 => array(
                'interest_rate' => 8.90
            ),
            9 => array(
                'interest_rate' => 9.10
            ),
            10 => array(
                'interest_rate' => 10.11
            ),
            11 => array(
                'interest_rate' => 11.22
            ),
            12 => array(
                'interest_rate' => 12.33
            )
        );

        return $arr;
    }
}