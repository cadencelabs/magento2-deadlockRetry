<?php
/**
 * @author Alan Barber
 */

namespace Cadence\DeadlockRetry\Console\Command;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Config\ConfigOptionsListConstants;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Simulate extends Command
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Framework\App\State $appState
     */
    protected $_appState;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $_resource;

    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    protected $_dir;

    /**
     * @var DeploymentConfig
     */
    protected $_deploymentConfig;

    /**
     * Constructor of RegenerateUrlRewrites
     *
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Framework\Filesystem\DirectoryList $dir
     * @param \BoostMyShop\AdvancedStock\Model\Warehouse\Item\ReservationFixer $reservationFixer
     * @param \BoostMyShop\AdvancedStock\Model\Router $router
     * @param DeploymentConfig $deploymentConfig
     */
    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\State $appState,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        DeploymentConfig $deploymentConfig
    ) {
        $this->_resource = $resource;
        $this->_appState = $appState;
        $this->_dir = $dir;
        $this->_deploymentConfig = $deploymentConfig;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('cadence:deadlockRetry:simulate')
            ->setDescription('Simulate a deadlock')
            ->setDefinition([]);
    }

    /**
     * Regenerate Url Rewrites
     * @param  InputInterface  $input
     * @param  OutputInterface $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        set_time_limit(0);
        $this->_appState->setAreaCode('adminhtml');

        $output->writeln("Starting a transaction to force a deadlock...");

        try {
            $this->_resource->getConnection()->beginTransaction();

            $this->_resource->getConnection()
                ->fetchAll("select * from innodb_deadlock_maker where a = 1 FOR UPDATE;");

            $cnxDetails = $this->_deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_DB_CONNECTIONS . '/default');
            $dbh = new PDO('mysql:host=' . $cnxDetails['host'] . ';dbname=' . $cnxDetails['dbname'], $cnxDetails['username'], $cnxDetails['password']);

            $dbh->query("start transaction");
            $dbh->query("SELECT * FROM innodb_deadlock_maker where a = 0 FOR UPDATE");

            $this->_resource->getConnection()
                ->query("update innodb_deadlock_maker set a = 2 where a <> 1");
            $dbh->query("UPDATE innodb_deadlock_maker set a = 3 where a <> 0");

            $output->writeln("We're done! We intentionally don't commit this transaction.");
        } catch (\Throwable $e) {
            $output->writeln("Encountered exception:");
            $output->writeln($e->getMessage());
        }

        $output->writeln('Finished');
    }
}
