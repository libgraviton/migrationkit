<?php

namespace Graviton\MigrationKit\Utils\Conflict\Scanner;

use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;
use Graviton\MigrationKit\Utils\Conflict\UnclearRenameConflict;
use Graviton\MigrationKit\Utils\MigrationDiff;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class UnclearRenameConflictScanner extends ConflictScannerAbstract {

    public function scan(MigrationDiff &$diff)
    {
        $conflicts = [];
        foreach ($diff->getDiffs() as $entityName => $changes) {
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
        return $conflicts;
    }
}
