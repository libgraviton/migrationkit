<?php
/**
 * command that copies a git clone, switches it and generates migrations off it
 */

namespace Graviton\MigrationKit\Command;

use GitElephant\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateMigrationsVersionCommand extends Command
{

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @param Filesystem $fs symfony/filesystem instance
     */
    public function __construct(
        Filesystem $fs
    ) {
        parent::__construct();
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
            ->setName('graviton:version-migrations:generate')
            ->setDescription(
                'Generates migrations from a given branch/tag'
            )
            ->setDefinition(
                new InputDefinition(
                    [
                    new InputArgument(
                        'baseDir',
                        InputArgument::REQUIRED,
                        'The path to your git clone of the project to use.'
                    ),
                    new InputArgument(
                        'branch',
                        InputArgument::REQUIRED,
                        'The branch to switch to for our diff. Use "develop" for a branch or "v1.0.0" for a tag.'
                    ),
                    new InputArgument(
                        'relativeDefinitionDir',
                        InputArgument::REQUIRED,
                        'The directory with the service definitions, relative to <baseDir>.'
                    ),
                    new InputArgument(
                        'migrationsDir',
                        InputArgument::REQUIRED,
                        'Path to put the generated migrations'
                    ),
                    new InputArgument(
                        'entityName',
                        InputArgument::OPTIONAL,
                        'If the directory contains multiple exposed entities, provide the targetted one'
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
        $baseDir = $input->getArgument('baseDir');
        $tag = $input->getArgument('branch');

        if (substr($baseDir, -1) != '/') {
            $baseDir .= '/';
        }

        if (!is_dir($baseDir.'.git')) {
            throw new \LogicException('Could not find .git directory in '.$baseDir);
        }

        $definitionDir = $input->getArgument('relativeDefinitionDir');
        $serviceDir = $baseDir.$definitionDir;
        if (!is_dir($serviceDir)) {
            throw new \LogicException('Could not find service directory in '.$serviceDir);
        }

        $tmpDir = sys_get_temp_dir().'/gr'.uniqid().'/';
        mkdir($tmpDir);

        $output->writeln('Mirroring baseDir to temporary location...');

        $this->fs->mirror($baseDir, $tmpDir);

        $repo = new Repository($tmpDir);
        $status = $repo->getStatus();

        foreach ($status->modified() as $modifiedFile) {
            $output->writeln('Reverting '.$modifiedFile->getName());
            $repo->checkout($modifiedFile->getName());
        }

        $output->writeln('Checking out '.$tag);

        try {
            $repo->checkout($tag);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException(
                sprintf(
                    'Could not checkout ref "%s". Please make sure you spelled it correctly (with the "v" prefix '.
                    'if this is a tag) and that you executed "git fetch"/"git pull" prior to executing this command '.
                    'so your clone is up to date!',
                    $tag
                )
            );
        }

        $command = $this->getApplication()->find('graviton:migrations:generate');

        $arguments = [
            'command' => 'graviton:migrations:generate',
            'oldDir' => $tmpDir.$definitionDir,
            'newDir' => $serviceDir,
            'migrationsDir' => $input->getArgument('migrationsDir'),
            'entityName' => $input->getArgument('entityName')
        ];

        $appInput = new ArrayInput($arguments);
        $command->run($appInput, $output);
    }
}
