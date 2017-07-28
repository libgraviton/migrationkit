<?php

namespace Graviton\MigrationKit\Utils\Conflict\Scanner;

use Graviton\MigrationKit\Utils\MigrationDiff;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ConflictScannerAbstract {

    abstract public function scan(MigrationDiff &$diff);

}
