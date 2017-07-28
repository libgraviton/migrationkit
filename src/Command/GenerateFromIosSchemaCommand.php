<?php
/**
 * command for our definition generator
 */

namespace Graviton\MigrationKit\Command;

use Graviton\MigrationKit\Utils\GenerateFromIosSchemaUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFromIosSchemaCommand extends Command
{

    /**
     * @var GenerateFromIosSchemaUtils utils
     */
    private $utils;

    /**
     * @var Filesystem filesystem
     */
    private $fs;

    /**
     * @param string        $destDir destination dir
     * @param GenerateFromIosSchemaUtils $utils   utils
     * @param Filesystem    $fs      symfony/filesystem instance
     *
     */
    public function __construct(
        GenerateFromIosSchemaUtils $utils,
        Filesystem $fs
    ) {
        parent::__construct();
        $this->utils = $utils;
        $this->fs = $fs;
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:from-ios-schema:generate')
            ->setDescription(
                'Generates consultation definition from iOS files'
            )
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('schemaFile', InputArgument::REQUIRED, 'Filepath where the iOS schema is located'),
                    new InputArgument('outputDir', InputArgument::REQUIRED, 'Where to write the service definitions.'),
                    new InputOption('infoDir', 'i', InputOption::VALUE_REQUIRED, 'Where to generate optional metadata files.'),
                    new InputOption('overrideDir', 'o', InputOption::VALUE_REQUIRED, 'Directory path to optional overrides.')
                ])
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
        $schemaFile = $input->getArgument('schemaFile');
        if (!is_file($schemaFile)) {
            throw new \LogicException(sprintf('Schema file %s does not exist', $schemaFile));
        }

        // output dir set?
        $destDir = $input->getArgument('outputDir');
        if (!is_null($destDir)) {
            if (substr($destDir, -1) != '/') {
                $destDir .= '/';
            }
        }

        // info dir set?
        $infoDir = $input->getOption('infoDir');
        if (!is_null($infoDir)) {
            if (substr($infoDir, -1) != '/') {
                $infoDir .= '/';
            }
        }

        // override dir?
        $overrideDir = $input->getOption('overrideDir');
        if (!is_null($overrideDir)) {
            if (substr($overrideDir, -1) != '/') {
                $overrideDir .= '/';
            }
            if (!is_dir($overrideDir)) {
                throw new \LogicException(sprintf('Override dir %s does not exist', $overrideDir));
            }
        }

        $output->writeln('<info>Reading definitions...</info>');
        $output->writeln('<info>Writing to '.$destDir.'...</info>');

        $this->utils->setDefinitionFile($schemaFile);
        if (!is_null($overrideDir)) {
            $this->utils->setOverridePath($overrideDir);
        }

        $definitions = $this->utils->getDefinitions();

        // first, delete old json files..
        $this->fs->remove($destDir);

        $output->writeln('<info>Cleared old files...</info>');

        foreach ($definitions as $destFile => $definition) {
            $destFile = $destDir.$destFile;
            $this->fs->dumpFile($destFile, json_encode($definition, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES));
        }

        $output->writeln('<info>Wrote new definitions...</info>');

        $dummyFile = $destDir.'_DO_NOT_CHANGE_ANYTHING_HERE_MANUALLY/.gitkeep';
        $this->fs->dumpFile($dummyFile, '');

        // meta files
        if (!is_null($infoDir)) {
            $this->fs->dumpFile($infoDir . '_fieldList.yml', Yaml::dump($this->utils->getFieldList()));
            $this->fs->dumpFile($infoDir . '_entitiesPath.yml', Yaml::dump($this->utils->getEntitiesPath()));
            $output->writeln('<info>Wrote YML meta files...</info>');
        }
    }
}
