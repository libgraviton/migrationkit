<?php

namespace Graviton\MigrationKit\Utils;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MetadataUtils {

    const FILENAME_ENTITIES_PATH = '_entitiesPath.yml';
    const FILENAME_FIELD_LIST = '_fieldList.yml';

    /**
     * @var Finder
     */
    private $finder;

    /**
     * @var string
     */
    private $sourceDir;

    /**
     * @var string
     */
    private $outputDir;

    /**
     * @var string
     */
    private $exposedEntityName;

    private $entities = [];

    private $exposedEntities = [];

    private $resultFieldList = [];

    private $resultEntitiesPath = [];

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
     * set SourceDir
     *
     * @param string $sourceDir sourceDir
     *
     * @return void
     */
    public function setSourceDir($sourceDir)
    {
        $this->sourceDir = $sourceDir;
        $this->entities = [];
        $this->exposedEntities = [];
        $this->resultFieldList = [];
        $this->resultEntitiesPath = [];
    }

    /**
     * set OutputDir
     *
     * @param string $outputDir outputDir
     *
     * @return void
     */
    public function setOutputDir($outputDir)
    {
        if (!is_dir($outputDir)) {
            throw new \LogicException(sprintf("Directory %s does not exist!", $outputDir));
        }

        if (substr($outputDir, -1) !== '/') $outputDir .= '/';
        $this->outputDir = $outputDir;
    }

    /**
     * set ExposedEntityName
     *
     * @param string $exposedEntityName exposedEntityName
     *
     * @return void
     */
    public function setExposedEntityName($exposedEntityName)
    {
        $this->exposedEntityName = $exposedEntityName;
    }

    /**
     * execute the command
     *
     * @param InputInterface  $input  input
     * @param OutputInterface $output output
     *
     * @return void
     */
    public function generate()
    {
        // override here
        $this->loadEntities();

        if (is_null($this->exposedEntityName)) {
            if (empty($this->exposedEntities)) {
                throw new \LogicException("Could find no beginning (exposed) entity in the specified directory!");
            }

            if (count($this->exposedEntities) !== 1) {
                throw new \LogicException("Found more than one exposed entities in the specified directory!");
            }

            $this->exposedEntityName = $this->exposedEntities[0];
        } else {
            if (!isset($this->entities[$this->exposedEntityName])) {
                throw new \LogicException("Could not find entity ".$this->exposedEntityName);
            }
        }

        $this->generateMetadata($this->exposedEntityName);

        file_put_contents($this->outputDir.self::FILENAME_ENTITIES_PATH, Yaml::dump($this->resultEntitiesPath));
        file_put_contents($this->outputDir.self::FILENAME_FIELD_LIST, Yaml::dump($this->resultFieldList));
    }

    private function loadEntities()
    {
        $finder = $this->finder;

        $files = $finder::create()
            ->files()
            ->name('*.json')
            ->in($this->sourceDir);

        foreach ($files as $file) {
            $structure = json_decode(file_get_contents($file->getPathname()), true);

            if (!isset($structure['id'])) {
                echo "ignored file ".$file->getPathname().PHP_EOL;
                continue;
            }

            if (isset($structure['service']['routerBase']) && !empty($structure['service']['routerBase'])) {
                $this->exposedEntities[] = $structure['id'];
            }

            $this->entities[$structure['id']] = $structure['target']['fields'];
        }
    }

    private function generateMetadata($entity, $path = [])
    {
        $this->resultEntitiesPath[$entity][] = implode('.', $path);
        $fields = $this->entities[$entity];

        foreach ($fields as $field) {
            $thisPath = $path;
            $thisPath[] = $field['name'];

            $this->resultFieldList[implode('.', $thisPath)] = $field;

            $class = $this->getFieldEntityName($field);

            if (!is_null($class)) {
                if (strpos($class, '[]') !== false) {
                    $class = str_replace('[]', '', $class);
                    $lastPath = array_pop($thisPath);
                    $lastPath .= '.0';
                    $thisPath[] = $lastPath;
                }

                $this->generateMetadata($class, $thisPath);
            }
        }
    }

    private function getFieldEntityName($field)
    {
        $class = null;
        if (isset($field['type'])) {
            $type = $field['type'];
            if (substr($type, 0, 6) == 'class:') {
                $classParts = explode('\\', $type);
                $class = array_pop($classParts);
            }
        }
        return $class;
    }
}
