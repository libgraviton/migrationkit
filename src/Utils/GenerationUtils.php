<?php

namespace Graviton\MigrationKit\Utils;

use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerationUtils {

    private $directory;

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

    public function getEntities()
    {
        return $this->getCacheFile(MetadataUtils::FILENAME_ENTITIES_PATH);
    }

    public function getFieldList()
    {
        return $this->getCacheFile(MetadataUtils::FILENAME_FIELD_LIST);
    }

    private function getCacheFile($fileName)
    {
        $file = $this->findFile($fileName);

        if (!isset($this->cache[$file])) {
            $content = Yaml::parse(file_get_contents($file));

            // sort by key length
            uksort($content, function($a, $b) {
                return strlen($a)-strlen($b);
            });

            $this->cache[$file] = $content;
        }

        return $this->cache[$file];
    }

    public function isClassType($path)
    {
        $info = $this->getPathInformation($path);
        return (isset($info['type']) && substr($info['type'], 0, 6) == 'class:');
    }

    public function isClassArrayType($path)
    {
        $info = $this->getPathInformation($path);
        return ($this->isClassType($path) && substr($info['type'], -2) == '[]');
    }

    public function isArrayType($path)
    {
        $info = $this->getPathInformation($path);
        return (isset($info['name']) && substr($info['name'], -2) == '.0');
    }

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

    public function getEntityPaths($entityName)
    {
        $paths = [];
        $entities = $this->getEntities();
        if (isset($entities[$entityName])) {
            $paths = $entities[$entityName];
        }
        return $paths;
    }

    public function getPathInformation($path)
    {
        $information = null;
        $fieldList = $this->getFieldList();
        if (isset($fieldList[$path])) {
            $information = $fieldList[$path];
        }
        return $information;
    }

    public function translatePathToJsonPath($path)
    {
        return '$.'.str_replace('.0', '[*]', $path);

    }

    private function findFile($filename)
    {
        $filepath = $this->directory.$filename;

        if (!file_exists($filepath)) {
            throw new \LogicException(sprintf(
                'Could not find file %s',
                $filepath
            ));
        }

        return $filepath;
    }



}
