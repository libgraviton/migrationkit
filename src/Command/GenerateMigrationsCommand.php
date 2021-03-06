<?php
/**
 * command that generates migrations from two directories
 */

namespace Graviton\MigrationKit\Command;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;
use Graviton\MigrationKit\Utils\GenerateFromIosSchemaUtils;
use Graviton\MigrationKit\Utils\GenerationUtils;
use Graviton\MigrationKit\Utils\MigrationGenerateUtils;
use Graviton\MigrationKit\Utils\MigrationUtils;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateMigrationsCommand extends BaseCommand
{

    const OP_CHANGE = 'M';
    const OP_ADD = '+';
    const OP_DEL = '-';

    /**
     * @var array
     */
    private $infoMap = [
        self::OP_ADD => 'info',
        self::OP_CHANGE => 'comment',
        self::OP_DEL => 'error'
    ];

    /**
     * @var string destination dir
     */
    private $oldDir;

    /**
     * @var string destination dir
     */
    private $oldYmlDir;

    /**
     * @var string info dir
     */
    private $newDir;

    /**
     * @var string info dir
     */
    private $newYmlDir;

    /**
     * @var string
     */
    private $migrationsDir;

    /**
     * @var GenerateFromIosSchemaUtils utils
     */
    private $utils;

    /**
     * @var MigrationGenerateUtils
     */
    private $migrationGenerateUtils;

    /**
     * @var GenerationUtils
     */
    private $generationUtils;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var SymfonyStyle
     */
    private $style;

    /**
     * @param MigrationUtils         $utils                  MigrationUtils
     * @param MigrationGenerateUtils $migrationGenerateUtils MigrationGenerateUtils
     * @param GenerationUtils        $generationUtils        GenerationUtils
     * @param Finder                 $finder                 Finder
     */
    public function __construct(
        MigrationUtils $utils,
        MigrationGenerateUtils $migrationGenerateUtils,
        GenerationUtils $generationUtils,
        Finder $finder
    ) {
        parent::__construct();
        $this->utils = $utils;
        $this->migrationGenerateUtils = $migrationGenerateUtils;
        $this->generationUtils = $generationUtils;
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
            ->setName('graviton:migrations:generate')
            ->setDescription(
                'Tries to generate migrations'
            )
            ->setDefinition(
                new InputDefinition(
                    [
                    new InputArgument(
                        'oldDir',
                        InputArgument::REQUIRED,
                        'The directory of the *old* structure we want to migrate from'
                    ),
                    new InputArgument(
                        'newDir',
                        InputArgument::REQUIRED,
                        'The directory of the *new* structure we want to migrate to'
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
                    ),
                    new InputArgument(
                        'namespace',
                        InputArgument::OPTIONAL,
                        'Class namespace for the generated migration'
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
        $this->style = new SymfonyStyle($input, $output);

        $this->welcomeScreen();

        $this->prepareExecute($input);

        // prepare the GenerationUtils for our MigrationGenerationUtils (old & new)
        $generationUtilsOld = clone $this->generationUtils;
        $generationUtilsOld->setDirectory($this->oldYmlDir);
        $generationUtilsNew = clone $this->generationUtils;
        $generationUtilsNew->setDirectory($this->newYmlDir);

        $this->migrationGenerateUtils->setGenerationUtilsOld($generationUtilsOld);
        $this->migrationGenerateUtils->setGenerationUtilsNew($generationUtilsNew);

        $diff = $this->utils->compute($this->oldDir, $this->newDir);

        if (empty($diff->getDiffs())) {
            $this->style->section('No differences found, exiting...');
            exit(0);
        }

        $this->style->title('Showing differences');

        $rows = [];
        foreach ($diff->getDiffs() as $entity => $changes) {
            $rows[] = array_merge($this->renderSingleChangeLine($entity, $changes));
            $rows[] = new TableSeparator();
        }

        array_pop($rows);

        $this->style->table(
            ['Entity', 'Fields'],
            $rows
        );

        $this->style->text(
            [
            'In the table above you see the differences we could detect.',
            'Please verify if those look correct. If they seem wrong, please check the result ',
            'of the definition generation.'
            ]
        );

        $isCorrect = $this->style->confirm('Do the differences look correct?', true);
        if (!$isCorrect) {
            exit(0);
            die;
        }

        if (!$diff->hasConflicts()) {
            $this->style->section('No conflicts, all fine...');
        } else {
            $this->style->text('We have conflict(s) that you need to give input to in order to resolve them.');
            sleep(2);
            $this->style->title('Conflict resolution');
        }

        $conflictCounter = count($diff->getConflicts());
        $currentConflict = 1;
        foreach ($diff->getConflicts() as $conflict) {
            $resolver = $conflict->getResolver();

            $this->style->section(
                sprintf(
                    'Conflict resolution (%d/%d): %s',
                    $currentConflict,
                    $conflictCounter,
                    $resolver->getConflictDescription()
                )
            );

            $resolver->interactiveResolve($this->style, $input, $output, $this->getHelper('question'));
            $resolver->resolve();

            $diff->setDiffForEntity($conflict->getClassName(), $conflict->getFieldOps());

            $currentConflict++;
        }

        // generate migration here
        $this->migrationGenerateUtils->setClassNamespace($input->getArgument('namespace'));
        $this->migrationGenerateUtils->setOutputDirectory($this->migrationsDir);
        $result = $this->migrationGenerateUtils->generate($diff);

        if (is_null($result)) {
            $this->style->section(
                'No migration generated, no relevant changes were done (as this tool could detect)...'
            );
        } else {
            $this->style->section('Generated migration '.$result);
        }
    }

    /**
     * prints the welcome screen
     *
     * @return void
     */
    private function welcomeScreen()
    {
        $this->style->title('Welcome to migrationkit!');

        $this->style->text(
            [
            'This tool tries to automatically generate migrations of service definitions.',
            'It is not a replacement for the human brain and it cannot generate everything.',
            '',
            'The ultimate goal of this tool is to generate the most common and doable cases.',
            'For the remaining cases, the tool at least should see what problems there are and tell the user.'
            ]
        );

        $confirm = $this->style->confirm(
            'Can you confirm that you understand that this tool does not replace your brain?',
            false
        );

        if (!$confirm) {
            exit(0);
        }
    }

    /**
     * prepares the execution of the migration generation
     *
     * @param InputInterface $input input
     *
     * @return void
     */
    private function prepareExecute($input)
    {
        $this->style->note('OK.. nice.. preparing diff metadata..');
        $this->style->progressStart(100);
        $this->style->progressAdvance(10);

        $this->migrationsDir = $input->getArgument('migrationsDir');
        $this->oldDir = $input->getArgument('oldDir');
        $this->newDir = $input->getArgument('newDir');
        $exposedEntity = $input->getArgument('entityName');

        if (!is_dir($this->migrationsDir) || !is_dir($this->oldDir) || !is_dir($this->newDir)) {
            throw new \LogicException('Either one of <oldDir>, <migrationsDir> or <newDir> do not exist!');
        }

        // generate metadata
        $this->oldYmlDir = $this->generateMetadataFromDefinitionDir($this->oldDir, $exposedEntity);
        $this->style->progressAdvance(50);

        $this->newYmlDir = $this->generateMetadataFromDefinitionDir($this->newDir, $exposedEntity);
        $this->style->progressAdvance(40);
        $this->style->progressFinish();
    }

    /**
     * renders a single line to the user
     *
     * @param string $entityName   entity name
     * @param array  $entityChange change
     *
     * @return array
     */
    private function renderSingleChangeLine($entityName, $entityChange)
    {
        $fieldChanges = [];

        foreach ($entityChange['props'] as $fieldName => $diff) {
            $typeOfChange = self::OP_CHANGE;

            if (isset($entityChange['fields'][$fieldName])) {
                if ($entityChange['fields'][$fieldName] instanceof DiffOpAdd) {
                    $typeOfChange = self::OP_ADD;
                }
                if ($entityChange['fields'][$fieldName] instanceof DiffOpRemove) {
                    $typeOfChange = self::OP_DEL;
                }
            }

            $outFormat = $this->infoMap[$typeOfChange];

            $fieldChange = sprintf(
                implode(
                    PHP_EOL,
                    [
                        '<%1$s>[%2$s] %3$s</%1$s>',
                        '%4$s'
                    ]
                ),
                $outFormat,
                $typeOfChange,
                $fieldName,
                implode(PHP_EOL, $this->getPropertyChanges($diff))
            );

            $fieldChanges[] = $fieldChange;
        }

        return [$entityName, implode(PHP_EOL, $fieldChanges)];
    }

    /**
     * render an actual change
     *
     * @param Diff $diff diff
     *
     * @return array
     */
    private function getPropertyChanges(Diff $diff)
    {
        $changes = [];

        foreach ($diff->getOperations() as $fieldName => $op) {
            $oldValue = '[none]';
            $newValue = '[none]';

            if (is_callable([$op, 'getOldValue'])) {
                $oldValue = $this->changeClassname($op->getOldValue());
            }
            if (is_callable([$op, 'getNewValue'])) {
                $newValue = $this->changeClassname($op->getNewValue());
            }

            $changes[] = '    '.$fieldName.': '.var_export($oldValue, true).
                ' => '.var_export($newValue, true);
        }

        return $changes;
    }

    /**
     * gets the class name of a given type for output
     *
     * @param string $type type
     *
     * @return string what to display
     */
    private function changeClassname($type)
    {
        if (substr($type, 0, 6) == 'class:') {
            $parts = explode('\\', $type);
            if (count($parts) > 0) {
                $type = array_slice($parts, -1)[0];
            }
        }
        return $type;
    }
}
