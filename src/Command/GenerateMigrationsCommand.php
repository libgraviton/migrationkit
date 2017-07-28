<?php
/**
 * command for our definition generator
 */

namespace Graviton\MigrationKit\Command;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;
use Graviton\MigrationKit\Utils\GenerateFromIosSchemaUtils;
use Graviton\MigrationKit\Utils\GenerationUtils;
use Graviton\MigrationKit\Utils\MetadataUtils;
use Graviton\MigrationKit\Utils\MigrationGenerateUtils;
use Graviton\MigrationKit\Utils\MigrationUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateMigrationsCommand extends Command
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
     * @var MetadataUtils $metadataUtils
     */
    private $metadataUtils;

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var SymfonyStyle
     */
    private $style;

    /**
     * @param string        $destDir destination dir
     * @param GenerateFromIosSchemaUtils $utils   utils
     * @param Filesystem    $fs      symfony/filesystem instance
     *
     */
    public function __construct(
        MigrationUtils $utils,
        MigrationGenerateUtils $migrationGenerateUtils,
        GenerationUtils $generationUtils,
        MetadataUtils $metadataUtils,
        Finder $finder
    ) {
        parent::__construct();
        $this->utils = $utils;
        $this->migrationGenerateUtils = $migrationGenerateUtils;
        $this->generationUtils = $generationUtils;
        $this->metadataUtils = $metadataUtils;
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
                new InputDefinition([
                    new InputArgument('oldDir', InputArgument::REQUIRED, 'The directory of the *old* structure we want to migrate from'),
                    new InputArgument('newDir', InputArgument::REQUIRED, 'The directory of the *new* structure we want to migrate to'),
                    new InputArgument('migrationsDir', InputArgument::REQUIRED, 'Path to put the generated migrations'),
                    new InputArgument('entityName', InputArgument::OPTIONAL, 'If the directory contains multiple exposed entities, provide the targetted one')
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
        $this->style = new SymfonyStyle($input, $output);

        $this->welcomeScreen($input, $output);

        $this->prepareExecute($input);

        // prepare the GenerationUtils for our MigrationGenerationUtils (old & new)
        $generationUtilsOld = clone $this->generationUtils;
        $generationUtilsOld->setDirectory($this->oldYmlDir);
        $generationUtilsNew = clone $this->generationUtils;
        $generationUtilsNew->setDirectory($this->newYmlDir);

        $this->migrationGenerateUtils->setGenerationUtilsOld($generationUtilsOld);
        $this->migrationGenerateUtils->setGenerationUtilsNew($generationUtilsNew);

        $diff = $this->utils->compute($this->oldDir, $this->newDir);

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

        $this->style->text([
            'In the table above you see the differences we could detect.',
            'Please verify if those look correct. If they seem wrong, please check the result ',
            'of the definition generation.'
        ]);

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
        $this->migrationGenerateUtils->setOutputDirectory($this->migrationsDir);
        $this->migrationGenerateUtils->generate($diff);
    }

    private function welcomeScreen($input, OutputInterface $output)
    {
        $this->style->title('Welcome to migration-tools');

        $this->style->text([
            'This tool tries to automatically generate migrations of service definitions.',
            'It is not a replacement for the human brain and it cannot generate everything.',
            '',
            'The ultimate goal of this tool is to generate the most common and doable cases.',
            'For the remaining cases, the tool at least should see what problems there are and tell the user.'
        ]);

        $confirm = $this->style->confirm(
            'Can you confirm that you understand that this tool does not replace your brain?',
            false
        );

        if (!$confirm) {
            exit(0);
        }
    }

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
        $this->metadataUtils->setFinder($this->finder);
        $this->metadataUtils->setExposedEntityName($exposedEntity);

        $this->oldYmlDir = sys_get_temp_dir().'/gr'.uniqid();
        mkdir($this->oldYmlDir);
        $this->newYmlDir = sys_get_temp_dir().'/gr'.uniqid();
        mkdir($this->newYmlDir);

        $this->metadataUtils->setSourceDir($this->oldDir);
        $this->metadataUtils->setOutputDir($this->oldYmlDir);
        $this->metadataUtils->generate();

        $this->style->progressAdvance(50);

        $this->metadataUtils->setSourceDir($this->newDir);
        $this->metadataUtils->setOutputDir($this->newYmlDir);
        $this->metadataUtils->generate();

        $this->style->progressAdvance(40);
        $this->style->progressFinish();
    }

    private function renderSingleChangeLine($entityName, $entityChange) {
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

    private function getPropertyChanges(Diff $diff) {
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

            $changes[] = '    '.$fieldName.': '.var_export($oldValue, true).' => '.var_export($newValue, true);
        }

        return $changes;
    }

    private function changeClassname($type) {
        if (substr($type, 0, 6) == 'class:') {
            $parts = explode('\\', $type);
            if (count($parts) > 0) {
                $type = array_slice($parts, -1)[0];
            }
        }
        return $type;
    }
}
