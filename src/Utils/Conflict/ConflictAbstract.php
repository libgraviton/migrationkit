<?php
/**
 * conflict
 */

namespace Graviton\MigrationKit\Utils\Conflict;

use Graviton\MigrationKit\Utils\Conflict\Resolver\ConflictResolverAbstract;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class ConflictAbstract
{

    /**
     * @var bool
     */
    private $isResolved = false;

    /**
     * @var DiffOp[]
     */
    private $fieldOps;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $fieldName;

    /**
     * returns the resolver instance for this conflict
     *
     * @return ConflictResolverAbstract resolver
     */
    abstract public function getResolver();

    /**
     * get FieldOps
     *
     * @return DiffOp[] FieldOps
     */
    public function getFieldOps()
    {
        return $this->fieldOps;
    }

    /**
     * set FieldOps
     *
     * @param DiffOp[] $fieldOps fieldOps
     *
     * @return void
     */
    public function setFieldOps($fieldOps)
    {
        $this->fieldOps = $fieldOps;
    }

    /**
     * get isResolved
     *
     * @return bool isResolved
     */
    public function isResolved()
    {
        return $this->isResolved;
    }

    /**
     * set IsResolved
     *
     * @param bool $isResolved isResolved
     *
     * @return void
     */
    public function setIsResolved($isResolved)
    {
        $this->isResolved = $isResolved;
    }

    /**
     * get ClassName
     *
     * @return string ClassName
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * set ClassName
     *
     * @param string $className className
     *
     * @return void
     */
    public function setClassName($className)
    {
        $this->className = $className;
    }

    /**
     * get FieldName
     *
     * @return string FieldName
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * set FieldName
     *
     * @param string $fieldName fieldName
     *
     * @return void
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
    }
}
