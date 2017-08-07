<?php
/**
 * conflict scanner for fields that are required and have no default value
 */

namespace Graviton\MigrationKit\Utils\Conflict\Scanner;

use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;
use Graviton\MigrationKit\Utils\Conflict\UnclearRenameConflict;
use Graviton\MigrationKit\Utils\MigrationDiff;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MissingRequiredDefaultConflictScanner extends ConflictScannerAbstract
{

    /**
     * scan for conflicts in a given diff
     *
     * @param MigrationDiff $diff diff
     *
     * @return void
     */
    public function scan(MigrationDiff &$diff)
    {
        foreach ($diff->getDiffs() as $entityName => $changes) {

            var_dump($diff->getDiffs()); die;
            $adds = [];
            $removals = [];
            foreach ($changes['fields'] as $fieldName => $fieldChange) {
                if ($fieldChange instanceof DiffOpAdd) {
                    $adds[] = $fieldName;
                }
                if ($fieldChange instanceof DiffOpRemove) {
                    $removals[] = $fieldName;
                }
            }

            // so do we have additions and removals here?
            if (!empty($adds) && !empty($removals)) {
                $conflict = new UnclearRenameConflict();
                $conflict->setClassName($entityName);
                $conflict->setFieldOps($changes);
                $conflict->setAdditions($adds);
                $conflict->setRemovals($removals);

                $diff->addConflict($conflict);
            }
        }
    }
}
