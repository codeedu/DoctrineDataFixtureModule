<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace DoctrineDataFixtureModule\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\DBAL\Migrations\Configuration\Configuration;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use DoctrineDataFixtureModule\Loader\ServiceLocatorAwareLoader;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Command for generate migration classes by comparing your current database schema
 * to your mapping information.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @author  Jonathan Wage <jonwage@gmail.com>
 */
class ImportCommand extends Command
{
    protected $paths;

    protected $em;
    
    /**
     * Service Locator instance
     * @var Zend\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceLocator;
    /**
     * ServiceLocatorAwareLoader
     * @var DoctrineDataFixtureModule\Loader\ServiceLocatorAwareLoader
     */
    protected $loader;

    protected $purger;

    const PURGE_MODE_TRUNCATE = 2;
    
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
        $this->loader = new ServiceLocatorAwareLoader($this->serviceLocator);

        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this->setName('data-fixture:import')
            ->setDescription('Import Data Fixtures')
            ->setHelp(
<<<EOT
The import command Imports data-fixtures
EOT
            )
            ->addOption('append', null, InputOption::VALUE_NONE, 'Append data to existing data.')
            ->addOption('purge-with-truncate', null, InputOption::VALUE_NONE, 'Truncate tables before inserting data')
            ->addOption(
                'fixtures',
                null,
                InputOption::VALUE_REQUIRED,
                'Set path to Fixture Class or Directory to be added'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('purge-with-truncate')) {
            $this->purger->setPurgeMode(self::PURGE_MODE_TRUNCATE);
        }

        if ($input->getOption('fixtures') != null) {
            $fixtures = $input->getOption('fixtures');
            if (is_dir($fixtures)) {
                $this->loader->loadFromDirectory($fixtures);
            } elseif (file_exists($fixtures)) {
                $classes = get_declared_classes();
                include($fixtures);
                $newClasses = get_declared_classes();

                $diff = array_diff($newClasses, $classes);
                $class = array_pop($diff);
                $this->loader->addFixture(new $class);
            } else {
                throw new \RuntimeException('Cannot find File or Directory.');
            }
        } else {
            foreach ($this->paths as $key => $value) {
                $this->loader->loadFromDirectory($value);
            }
        }
        
        $executor = new ORMExecutor($this->em, $this->purger);
        $executor->execute($this->loader->getFixtures(), $input->getOption('append'));
    }

    public function setPath($paths)
    {
        $this->paths=$paths;
    }

    public function setEntityManager($em)
    {
        $this->em = $em;
    }

    public function getLoader()
    {
        return $this->loader;
    }

    public function setPurger(ORMPurger $purger)
    {
        $this->purger = $purger;
    }
}
