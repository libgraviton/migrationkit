<?php
/**
 * utils needed diffing needed for migration generation
 */

namespace Graviton\MigrationKit\Utils;

use Diff\DiffOp\DiffOp;
use Graviton\MigrationKit\Utils\Conflict\ConflictAbstract;
use Graviton\MigrationKit\Utils\Conflict\Scanner\ConflictScannerAbstract;
use Graviton\MigrationKit\Utils\Conflict\Scanner\UnclearRenameConflictScanner;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MigrationDiff
{

    /**
     * @var DiffOp[]
     */
    private $diffs;

    /**
     * @var ConflictScannerAbstract[]
     */
    private $conflictScanners;

    /**
     * @var ConflictAbstract[]
     */
    private $currentConflicts = [];

    /**
     * @var string
     */
    private $oldDirHash;

    /**
     * @var string
     */
    private $newDirHash;

    /**
     * @param array $diffs diffs
     */
    public function __construct(array $diffs)
    {
        $this->conflictScanners = [
            new UnclearRenameConflictScanner()
        ];
        $this->diffs = $diffs;
        $this->computeConflicts();
    }

    /**
     * get Diffs
     *
     * @return DiffOp[] Diffs
     */
    public function getDiffs()
    {
        return $this->diffs;
    }

    /**
     * get the diffs relevant for migrations
     *
     * @return array diffs
     */
    public function getMigrationRelevantDiffs()
    {
        $diffs = [];
        foreach ($this->diffs as $entityName => $ops) {
            foreach ($ops['props'] as $fieldName => $diffOp) {
                $isRelevant = false;
                $changes = $diffOp->getChanges();

                // is required now true?
                if (isset($changes['required']) && $changes['required']->getNewValue() === true) {
                    $isRelevant = true;
                }

                // did the field name change?

                if (isset($changes['name'])) {
                    $isRelevant = true;
                }

                // did the type change?
                if (isset($changes['type'])) {
                    $isRelevant = true;
                }

                if ($isRelevant) {
                    $diffs[$entityName][$fieldName] = $diffOp;
                }
            }
        }

        return $diffs;
    }

    /**
     * set Diffs
     *
     * @param DiffOp[] $diffs diffs
     *
     * @return void
     */
    public function setDiffs($diffs)
    {
        $this->diffs = $diffs;
    }

    /**
     * sets the diffs for a given entity
     *
     * @param string $entityName name
     * @param array  $diffOps    diffs
     *
     * @return void
     */
    public function setDiffForEntity($entityName, $diffOps)
    {
        $this->diffs[$entityName] = $diffOps;
    }

    /**
     * if we currently have conflicts that need resolving
     *
     * @return bool yes or no
     */
    public function hasConflicts()
    {
        return !empty($this->currentConflicts);
    }

    /**
     * returns the current conflicts
     *
     * @return ConflictAbstract[] conflicts
     */
    public function getConflicts()
    {
        return $this->currentConflicts;
    }

    /**
     * get OldDirHash
     *
     * @return string OldDirHash
     */
    public function getOldDirHash()
    {
        return $this->oldDirHash;
    }

    /**
     * set OldDirHash
     *
     * @param string $oldDirHash oldDirHash
     *
     * @return void
     */
    public function setOldDirHash($oldDirHash)
    {
        $this->oldDirHash = $oldDirHash;
    }

    /**
     * get NewDirHash
     *
     * @return string NewDirHash
     */
    public function getNewDirHash()
    {
        return $this->newDirHash;
    }

    /**
     * set NewDirHash
     *
     * @param string $newDirHash newDirHash
     *
     * @return void
     */
    public function setNewDirHash($newDirHash)
    {
        $this->newDirHash = $newDirHash;
    }

    /**
     * adds a conflict
     *
     * @param ConflictAbstract $conflict conflict
     *
     * @return void
     */
    public function addConflict(ConflictAbstract $conflict)
    {
        $this->currentConflicts[get_class($conflict).'-'.$conflict->getClassName().'-'.$conflict->getFieldName()]
            = $conflict;
    }

    /**
     * Let the scanners run and see if we have conflicts
     *
     * @return void
     */
    private function computeConflicts()
    {
        foreach ($this->conflictScanners as $scanner) {
            $scanner->scan($this);
        }
    }
}
