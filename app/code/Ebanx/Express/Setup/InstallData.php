<?php
namespace Ebanx\Express\Setup;

use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Config;


class InstallData implements InstallDataInterface
{
	/**
     * @var CustomerSetupFactory
     */
    protected $_customerSetupFactory;
    /**
     * @var AttributeSetFactory
     */
    protected $_attributeSetFactory;
    
    /**
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory
    ) {
        $this->_customerSetupFactory = $customerSetupFactory;
        $this->_attributeSetFactory = $attributeSetFactory;
    }

	public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
	{
		$customerSetup = $this->_customerSetupFactory
            ->create(['setup' => $setup]);

        $customerEntity = $customerSetup->getEavConfig()
            ->getEntityType('customer');
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();

        /*@var $attributeSet AttributeSet */
        $attributeSet = $this->_attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

        $customerSetup->addAttribute(Customer::ENTITY, 'ebanx_cpf', [
            'type' => 'text',
            'label' => 'CPF',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => false,
            'sort_order' => 100,
            'position' => 100,
            'system' => 0,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true,
        ]);

        $attribute = $customerSetup->getEavConfig()
            ->getAttribute(Customer::ENTITY, 'ebanx_cpf')
            ->addData([
                'attribute_set_id' => $attributeSetId,
                'attribute_group_id' => $attributeGroupId,
                'used_in_forms' => [
                    'adminhtml_customer'
                ],
            ])->save();
	}
	
	
}