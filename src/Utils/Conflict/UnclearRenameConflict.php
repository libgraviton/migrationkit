<?php
/**
 * conflict
 */

namespace Graviton\MigrationKit\Utils\Conflict;

use Graviton\MigrationKit\Utils\Conflict\Resolver\UnclearRenameConflictResolver;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class UnclearRenameConflict extends ConflictAbstract
{

    /**
     * @var array
     */
    private $additions = [];

    /**
     * @var array
     */
    private $removals = [];

    /**
     * @var array
     */
    private $renames = [];

    /**
     * returns the resolver instance for this conflict
     *
     * @return ConflictResolverAbstract resolver
     */
    public function getResolver()
    {
        return new UnclearRenameConflictResolver($this);
    }

    /**
     * get Additions
     *
     * @return array Additions
     */
    public function getAdditions()
    {
        return $this->additions;
    }

    /**
     * set Additions
     *
     * @param array $additions additions
     *
     * @return void
     */
    public function setAdditions($additions)
    {
        $this->additions = $additions;
    }

    /**
     * get Removals
     *
     * @return array Removals
     */
    public function getRemovals()
    {
        return $this->removals;
    }

    /**
     * set Removals
     *
     * @param array $removals removals
     *
     * @return void
     */
    public function setRemovals($removals)
    {
        $this->removals = $removals;
    }

    /**
     * get Renames
     *
     * @return array Renames
     */
    public function getRenames()
    {
        return $this->renames;
    }

    /**
     * set Renames
     *
     * @param array $renames renames
     *
     * @return void
     */
    public function setRenames($renames)
    {
        $this->renames = $renames;
    }
}
