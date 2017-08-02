<?php
/**
 * prepares a structure we can use to easily detect changes
 */

namespace Graviton\MigrationKit\Utils;

use Diff\Differ\ListDiffer;
use Diff\Differ\MapDiffer;
use Diff\DiffOp\DiffOpAdd;
use Diff\DiffOp\DiffOpRemove;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MigrationUtils
{

    /**
     * TODO rewrite to use the metadata files also!
     */

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var string
     */
    private $lastScanHash;

    /**
     * @param Finder $finder finder
     */
    public function __construct(Finder $finder)
    {
        $this->finder = $finder;
        $this->differ = new MapDiffer(true);
        $this->differList = new ListDiffer();
    }

    /**
     * compute the differences between two dirs with service definitions
     *
     * @param string $oldDir old dir
     * @param string $newDir new dir
     *
     * @return MigrationDiff the diff
     */
    public function compute($oldDir, $newDir)
    {
        $changes = [];
        $oldData = $this->collectAttributes($oldDir);
        $oldHash = $this->lastScanHash;
        $newData = $this->collectAttributes($newDir);
        $newHash = $this->lastScanHash;

        foreach ($newData as $objectName => $elements) {
            if (isset($oldData[$objectName])) {
                if (!isset($changes[$objectName])) {
                    $changes[$objectName] = [];
                }

                $changes[$objectName]['fields'] = $this->prepareFieldDiffKeys(
                    $this->differList->doDiff(array_keys($oldData[$objectName]), array_keys($elements))
                );
                $changes[$objectName]['props'] = $this->differ->doDiff($oldData[$objectName], $elements);

                if (empty($changes[$objectName]['fields']) && empty($changes[$objectName]['props'])) {
                    unset($changes[$objectName]);
                }
            } else {
                echo "new object".$objectName.PHP_EOL;
            }
        }

        $diff = new MigrationDiff($changes);
        $diff->setOldDirHash($oldHash);
        $diff->setNewDirHash($newHash);

        return $diff;
    }

    /**
     * create diffs for fields
     *
     * @param array $diffList diff list
     * @return array
     */
    private function prepareFieldDiffKeys(array $diffList)
    {
        $fieldList = [];
        foreach ($diffList as $op) {
            if ($op instanceof DiffOpAdd) {
                $fieldList[$op->getNewValue()] = $op;
            } elseif ($op instanceof DiffOpRemove) {
                $fieldList[$op->getOldValue()] = $op;
            } else {
                $fieldList[] = $op;
            }
        }
        return $fieldList;
    }

    /**
     * collect the attributes
     *
     * @param string $dir directory
     *
     * @return array attributes
     */
    private function collectAttributes($dir)
    {
        $data = [];
        $hashes = [];
        $finder = $this->finder;

        $files = $finder::create()
            ->in($dir)
            ->name('*.json')
            ->sortByName();

        foreach ($files as $file) {
            $structure = json_decode(file_get_contents($file->getPathname()), true);
            $hashes[] = sha1_file($file->getPathname());
            $objectId = $structure['id'];
            foreach ($structure['target']['fields'] as $field) {
                $fieldName = $field['name'];
                $data[$objectId][$fieldName]['type'] = $field['type'];
                $data[$objectId][$fieldName]['required'] = (isset($field['required']) && $field['required'] === true);
            }
        }

        $this->lastScanHash = hash('sha256', implode(',', $hashes));

        return $data;
    }
}
