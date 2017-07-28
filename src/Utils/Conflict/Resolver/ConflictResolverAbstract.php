<?php

namespace Graviton\MigrationKit\Utils\Conflict\Resolver;

use Graviton\MigrationKit\Utils\Conflict\ConflictAbstract;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ConflictResolverAbstract {

    /**
     * @var ConflictAbstract
     */
    protected $conflict;

    public function __construct(ConflictAbstract &$conflict)
    {
        $this->conflict = &$conflict;
    }

    abstract public function getConflictDescription();

    abstract public function interactiveResolve(StyleInterface $style);

    abstract public function resolve();

}
