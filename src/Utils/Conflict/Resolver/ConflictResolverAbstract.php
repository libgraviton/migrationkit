<?php
/**
 * conflict resolver
 */

namespace Graviton\MigrationKit\Utils\Conflict\Resolver;

use Graviton\MigrationKit\Utils\Conflict\ConflictAbstract;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ConflictResolverAbstract
{

    /**
     * @var ConflictAbstract
     */
    protected $conflict;

    /**
     * @param ConflictAbstract $conflict conflict
     */
    public function __construct(ConflictAbstract &$conflict)
    {
        $this->conflict = &$conflict;
    }

    /**
     * Return a description for this conflict
     *
     * @return string
     */
    abstract public function getConflictDescription();

    /**
     * Handle the resolving of this conflict with the user
     *
     * @param StyleInterface $style style
     *
     * @return void
     */
    abstract public function interactiveResolve(StyleInterface $style);

    /**
     * After having the user input, resolve the conflict in the diffs
     *
     * @return void
     */
    abstract public function resolve();
}
