<?php
/**
 * conflict scanner
 */

namespace Graviton\MigrationKit\Utils\Conflict\Scanner;

use Graviton\MigrationKit\Utils\MigrationDiff;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ConflictScannerAbstract
{

    /**
     * scan for conflicts in a given diff
     *
     * @param MigrationDiff $diff diff
     *
     * @return void
     */
    abstract public function scan(MigrationDiff &$diff);
}
