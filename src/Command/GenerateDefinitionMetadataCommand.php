<?php
/**
 * command for our definition generator
 */

namespace Graviton\MigrationKit\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateDefinitionMetadataCommand extends BaseCommand
{

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @param Finder $finder finder
     */
    public function __construct(
        Finder $finder
    ) {
        parent::__construct();
        $this->finder = $finder;
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:definition-metadata:generate')
            ->setDescription(
                'Generates YML metadata files from service definitions'
            )
            ->setDefinition(
                new InputDefinition(
                    [
                    new InputArgument(
                        'sourceDir',
                        InputArgument::REQUIRED,
                        'Where to read definitions from.'
                    ),
                    new InputArgument(
                        'outputDir',
                        InputArgument::REQUIRED,
                        'Where to output our meta files (YAML)'
                    ),
                    new InputArgument(
                        'entityName',
                        InputArgument::OPTIONAL,
                        'Entity name to choose if more than one is exposed'
                    )
                    ]
                )
            );
    }

    /**
     * execute the command
     *
     * @param InputInterface  $input  input
     * @param OutputInterface $output output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->metadataUtils->setOutputDir($input->getArgument('outputDir'));
        $this->metadataUtils->setSourceDir($input->getArgument('sourceDir'));
        $this->metadataUtils->setExposedEntityName($input->getArgument('entityName'));
        $this->metadataUtils->setFinder($this->finder);
        $this->metadataUtils->generate();
    }
}
