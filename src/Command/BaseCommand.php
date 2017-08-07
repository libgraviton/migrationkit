<?php
/**
 * base command for commands
 */

namespace Graviton\MigrationKit\Command;

use Graviton\MigrationKit\Utils\MetadataUtils;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
abstract class BaseCommand extends Command
{

    /**
     * @var MetadataUtils
     */
    protected $metadataUtils;

    /**
     * @var array
     */
    private $tmpMetadataDirs = [];

    /**
     * @var Finder
     */
    private $finder;

    /**
     */
    public function __construct()
    {
        parent::__construct();
        $this->metadataUtils = new MetadataUtils();
        $this->finder = new Finder();
    }

    /**
     * set MetadataUtils
     *
     * @param MetadataUtils $metadataUtils metadataUtils
     *
     * @return void
     */
    public function setMetadataUtils($metadataUtils)
    {
        $this->metadataUtils = $metadataUtils;
    }

    /**
     * set Finder
     *
     * @param Finder $finder finder
     *
     * @return void
     */
    public function setFinder($finder)
    {
        $this->finder = $finder;
    }

    /**
     * Generates metadata files in a tmp directory from a service definition
     *
     * @param string $definitionDir definition dir
     * @param string $exposedEntity exposed entity if multiple
     *
     * @return string tmp directory location
     */
    protected function generateMetadataFromDefinitionDir($definitionDir, $exposedEntity = null)
    {
        if (!is_dir($definitionDir)) {
            throw new \LogicException(sprintf('Directory "%s" does not exist!', $definitionDir));
        }

        $metadataDir = sys_get_temp_dir().'/gr'.uniqid();
        mkdir($metadataDir);

        $this->metadataUtils->setSourceDir($definitionDir);
        $this->metadataUtils->setOutputDir($metadataDir);
        $this->metadataUtils->setExposedEntityName($exposedEntity);
        $this->metadataUtils->setFinder($this->finder);
        $this->metadataUtils->generate();

        $this->tmpMetadataDirs[] = $metadataDir;

        return $metadataDir;
    }
}
