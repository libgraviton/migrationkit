<?php
/**
 * conflict resolver
 */

namespace Graviton\MigrationKit\Utils\Conflict\Resolver;

use Diff\DiffOp\Diff\Diff;
use Diff\DiffOp\DiffOpChange;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class UnclearRenameConflictResolver extends ConflictResolverAbstract
{

    /**
     * Return a description for this conflict
     *
     * @return string
     */
    public function getConflictDescription()
    {
        return 'Possible rename';
    }

    /**
     * Handle the resolving of this conflict with the user
     *
     * @param StyleInterface $style style
     *
     * @return void
     */
    public function interactiveResolve(StyleInterface $style)
    {
        $style->text(
            [
            sprintf(
                '<question>In the entity "%s" we have added and removed fields.</question>',
                $this->conflict->getClassName()
            ),
            'You need to tell us if any of those changes are <error>renames.</error>',
            'Here are the added and removed fields:'
            ]
        );

        $additions = $this->conflict->getAdditions();
        $removals = $this->conflict->getRemovals();
        $renames = [];

        $userInput = 'y';

        while ($userInput != 'n') {
            if (!empty($renames)) {
                $style->writeln(str_repeat('-', 60));

                $renameList = [];
                $renameCounter = 0;
                foreach ($renames as $fieldFrom => $fieldTo) {
                    $renameList[] = $renameCounter.': '.$fieldFrom.' => '.$fieldTo;
                    $renameCounter++;
                }

                $style->table(['Renames'], [[implode(PHP_EOL, $renameList)]]);
            }

            $style->table(
                ['Removals', 'Additions'],
                [[$this->getFieldList($removals), $this->getFieldList($additions)]]
            );

            $style->text(
                [
                '<info>Are there any renames in this change?</info>',
                'Please enter one of the following to specify what to do:'
                ]
            );

            $style->listing(
                [
                '<question>r[NUMBER]:[NUMBER]</question> = to specify a rename according to the table above. '.
                'Each side (removal/addition) has a number preceding the field. So specify a rename from deleted '.
                'field 0 to added field 2, you would enter "r0:2".',
                '<question>d[NUMBER]</question> = to delete a previously specified rename in case of an error.'.
                ' The number refers to the number in front of the rename in the rename table. So to delete '.
                'rename 1, you would enter "d1".',
                '<question>n</question> = no renames that need specifying'
                ]
            );

            $userInput = $style->ask('Enter a command');

            if ($userInput != 'n') {
                $this->applyUserInput($userInput, $style, $additions, $removals, $renames);
            }
        }

        $this->conflict->setRenames($renames);
        $this->conflict->setIsResolved(true);
    }

    /**
     * apply a single user input to our dialog
     *
     * @param string         $userInput input
     * @param StyleInterface $style     style
     * @param array          $additions additions
     * @param array          $removals  removals
     * @param array          $renames   renames
     *
     * @return void
     */
    private function applyUserInput($userInput, $style, &$additions, &$removals, &$renames)
    {
        $matches = [];
        if (preg_match('/^r([0-9]+):([0-9]+)$/i', $userInput, $matches) === 1) {
            $fromField = (int) $matches[1];
            $toField = (int) $matches[2];

            if (!isset($removals[$fromField])) {
                $this->error($style, 'Could not find deleted field number '.$fromField);
                return;
            }
            if (!isset($additions[$toField])) {
                $this->error($style, 'Could not find added field number '.$toField);
                return;
            }

            $renames[$removals[$fromField]] = $additions[$toField];
            unset($removals[$fromField]);
            unset($additions[$toField]);
        } elseif (preg_match('/^d([0-9]+)$/i', $userInput, $matches) === 1) {
            $keys = array_keys($renames);
            $renameIndex = (int) $matches[1];

            if (!isset($keys[$renameIndex])) {
                $this->error($style, 'Could not find rename number '.$renameIndex);
                return;
            }

            $keyName = $keys[$renameIndex];
            $removals[] = $keyName;
            $additions[] = $renames[$keyName];
            unset($renames[$keyName]);
        } else {
            $this->error($style, 'Could not interpret your input, try again...');
        }
    }

    /**
     * renders an error
     *
     * @param OutputInterface $output  output
     * @param string          $message message
     *
     * @return void
     */
    private function error($output, $message)
    {
        $output->writeln('<error>'.PHP_EOL.$message.PHP_EOL.'</error>'.PHP_EOL);
    }

    /**
     * gets a list of fields to print out
     *
     * @param array $fields fields
     *
     * @return string field list
     */
    private function getFieldList($fields)
    {
        $fieldList = [];
        foreach ($fields as $index => $fieldName) {
            $fieldList[] = $index.': '.$fieldName;
        }
        return implode(PHP_EOL, $fieldList);
    }

    /**
     * After having the user input, resolve the conflict in the diffs
     *
     * @return void
     */
    public function resolve()
    {
        // let's apply our renames..
        $renames = $this->conflict->getRenames();
        if (empty($renames)) {
            return;
        }

        $ops = $this->conflict->getFieldOps();
        foreach ($renames as $oldName => $newName) {
            // fields
            $ops['fields'][$oldName] = new DiffOpChange($oldName, $newName);
            unset($ops['fields'][$newName]);

            // props
            $oldField = $ops['props'][$oldName]->getRemovals();
            $newField = $ops['props'][$newName]->getAdditions();

            $ops['props'][$oldName] = new Diff(
                [
                'name' => new DiffOpChange($oldName, $newName),
                'required' => new DiffOpChange(
                    $oldField['required']->getOldValue(),
                    $newField['required']->getNewValue()
                ),
                'type' => new DiffOpChange($oldField['type']->getOldValue(), $newField['type']->getNewValue())
                ]
            );

            // unset the new field
            unset($ops['props'][$newName]);
        }

        $this->conflict->setFieldOps($ops);
    }
}
