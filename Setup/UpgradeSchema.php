<?php

namespace Cadence\DeadlockRetry\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\ModuleContextInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    /**
     * Upgrades DB schema for a module.
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        $quoteAddressTable = 'quote_address';
        $quoteTable = 'quote';
        $orderTable = 'sales_order';
        $invoiceTable = 'sales_invoice';
        $creditmemoTable = 'sales_creditmemo';

        /**
         * @see https://www.xaprb.com/blog/2006/08/08/how-to-deliberately-cause-a-deadlock-in-mysql/
         * This is a quick version of that article's suggested implementation
         */
        $setup->getConnection()->query(
            "create table innodb_deadlock_maker(a int primary key) engine=innodb;"
        );

        $setup->getConnection()->query(
            "insert into innodb_deadlock_maker(a) values(0), (1);"
        );

        $setup->endSetup();
    }
}
