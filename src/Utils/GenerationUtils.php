<?php
/**
 * utils needed for generation
 */

namespace Graviton\MigrationKit\Utils;

use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerationUtils
{

    /**
     * @var string
     */
    private $directory;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * set Directory
     *
     * @param mixed $directory directory
     *
     * @return void
     */
    public function setDirectory($directory)
    {
        if (substr($directory, -1) != '/') {
            $directory .= '/';
        }

        $this->directory = $directory;
        $this->cache = [];
    }

    /**
     * get entities
     *
     * @return array entities
     */
    public function getEntities()
    {
        return $this->getCacheFile(MetadataUtils::FILENAME_ENTITIES_PATH);
    }

    /**
     * get the field list
     *
     * @return array field list
     */
    public function getFieldList()
    {
        return $this->getCacheFile(MetadataUtils::FILENAME_FIELD_LIST);
    }

    /**
     * gets a file from the cache, prepare cache if necessary
     *
     * @param string $fileName filename
     *
     * @return array file content
     */
    private function getCacheFile($fileName)
    {
        $file = $this->findFile($fileName);

        if (!isset($this->cache[$file])) {
            $content = Yaml::parse(file_get_contents($file));

            // sort by key length
            uksort(
                $content,
                function ($a, $b) {
                    return strlen($a)-strlen($b);
                }
            );

            $this->cache[$file] = $content;
        }

        return $this->cache[$file];
    }

    /**
     * tells if a given path is a class type
     *
     * @param string $path path
     *
     * @return bool true fi yes, false otherwise
     */
    public function isClassType($path)
    {
        $info = $this->getPathInformation($path);
        return (isset($info['type']) && substr($info['type'], 0, 6) == 'class:');
    }

    /**
     * tells if a given path is a class array type
     *
     * @param string $path path
     *
     * @return bool true fi yes, false otherwise
     */
    public function isClassArrayType($path)
    {
        $info = $this->getPathInformation($path);
        return ($this->isClassType($path) && substr($info['type'], -2) == '[]');
    }

    /**
     * tells if a given path is an array type
     *
     * @param string $path path
     *
     * @return bool true fi yes, false otherwise
     */
    public function isArrayType($path)
    {
        $info = $this->getPathInformation($path);
        return (isset($info['name']) && substr($info['name'], -2) == '.0');
    }

    /**
     * gets the root entity name, the exposed entity
     *
     * @return null|string root entity name
     */
    public function getRootEntity()
    {
        $ret = null;
        foreach ($this->getEntities() as $entity => $path) {
            if (count($path) == 1 && empty($path[0])) {
                $ret = $entity;
                break;
            }
        }
        return $ret;
    }

    /**
     * gets all paths for a given entity
     *
     * @param string $entityName entity name
     *
     * @return array paths
     */
    public function getEntityPaths($entityName)
    {
        $paths = [];
        $entities = $this->getEntities();
        if (isset($entities[$entityName])) {
            $paths = $entities[$entityName];
        }
        return $paths;
    }

    /**
     * get information about a path
     *
     * @param string $path path
     *
     * @return array|null information
     */
    public function getPathInformation($path)
    {
        $information = null;
        $fieldList = $this->getFieldList();
        if (isset($fieldList[$path])) {
            $information = $fieldList[$path];
        }
        return $information;
    }

    /**
     * translate our path to a jsonpath expression
     *
     * @param string $path path
     *
     * @return string jsonpath expression
     */
    public function translatePathToJsonPath($path)
    {
        return '$.'.str_replace('.0', '[*]', $path);
    }

    /**
     * locates a file in the local directory
     *
     * @param string $filename filename
     *
     * @return string filepath
     */
    private function findFile($filename)
    {
        $filepath = $this->directory.$filename;

        if (!file_exists($filepath)) {
            throw new \LogicException(
                sprintf(
                    'Could not find file %s',
                    $filepath
                )
            );
        }

        return $filepath;
    }
}
