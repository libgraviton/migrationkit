<?php
/**
 * generator that turns the ios definition into our definition
 */

namespace Graviton\MigrationKit\Utils;

use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFromIosSchemaUtils
{

    /**
     * @var Finder finder
     */
    private $finder;

    /**
     * path to the definition
     *
     * @var string
     */
    private $definitionFile;

    private $overridePath;

    /**
     * primitives - those will be taken 1:1 or mapped by $typeMap
     *
     * @var array
     */
    private $primitives = [
        'string',
        'datetime',
        'extref',
        'decimal',
        'number/bool',
        'number',
        'bool',
        'CLPlacemark',
        'NSData',
        'NSNull',
        'NSDateComponents',
        'EVJFinanceCockpitCompanyStatement'
    ];

    /**
     * map anything to another graviton type
     *
     * @var array
     */
    private $typeMap = [
        'number/bool' => 'int',
        'number' => 'float',
        'bool' => 'boolean',
        'CLPlacemark' => 'string',
        'NSData' => 'string',
        'NSNull' => 'string',
        'NSDateComponents' => 'string',
        // this here is unknown as it has no properties in the definition(?)
        'EVJFinanceCockpitCompanyStatement' => 'object'
    ];

    /**
     * entity ("class:") type mappings
     *
     * @var array
     */
    private $entityMap = [
        'EVJConsultationDossier' => 'FinancingConsultation', // top level object
        'localized' => 'ConsultationTranslatable'
    ];

    /**
     * @var array generated types
     */
    private $types = [];

    /**
     * @var array generated entities
     */
    private $entities = [];

    /**
     * @var array generated entities path
     */
    private $entitiesPath = [];

    /**
     * @var array generated fields, full list
     */
    private $fieldList = [];

    /**
     * @var array overrides
     */
    private $overrides = [];

    /**
     * @param Finder $finder symfony/finder instance
     */
    public function __construct(Finder $finder)
    {
        $this->finder = $finder;
    }

    /**
     * set DefinitionFile
     *
     * @param string $definitionFile definitionFile
     *
     * @return void
     */
    public function setDefinitionFile($definitionFile)
    {
        $this->definitionFile = $definitionFile;
    }

    /**
     * set OverridePath
     *
     * @param mixed $overridePath overridePath
     *
     * @return void
     */
    public function setOverridePath($overridePath)
    {
        $this->overridePath = $overridePath;
    }

    /**
     * outer function to get all generated definitions
     *
     * @return array
     */
    public function getDefinitions()
    {
        $this->readOverrides();

        $string = file_get_contents($this->definitionFile);

        if (mb_detect_encoding($string) != 'UTF-8') {
            ini_set('mbstring.substitute_character', 'none');
            $string = mb_convert_encoding($string, 'UTF-8', mb_detect_encoding($string));
        }

        $iosDefinition = json_decode($string, true);
        if (!$iosDefinition) {
            throw new \Exception(
                sprintf(
                    'Error in JSON parsing of file %s: %s',
                    $this->definitionFile,
                    json_last_error_msg()
                )
            );
        }

        $this->processHierarchy($iosDefinition);

        $definitions = [];
        $writtenEntities = [];

        foreach ($this->entities as $entity) {
            $entityName = $entity['id'];

            if (isset($writtenEntities[$entityName])) {
                // skip if already generated for some reason
                continue;
            }

            $paths = $this->entitiesPath[$entityName];

            if (count($paths) == 1) {
                $path = implode('/', $this->pathToClassNameParts($paths[0])).'/'.$entityName.'.json';
            } else {
                $path = 'CommonObjects/'.$entityName.'.json';
            }

            $writtenEntities[$entityName] = '';

            // any overrides?
            if (isset($this->overrides[$entityName])) {
                $entity = $this->applyOverride($entity, $this->overrides[$entityName]);
            }

            // check fields for missing type..
            if (is_array($entity['target']['fields'])) {
                foreach ($entity['target']['fields'] as $idx => $field) {
                    if (!isset($field['type'])) {
                        throw new \LogicException(
                            sprintf(
                                'No type on %s, file %s, field index %s!',
                                $entityName,
                                $path,
                                $idx.' field:'.json_encode($field)
                            )
                        );
                    }
                }
            }

            $definitions[$path] = $entity;
        }

        return $definitions;
    }

    /**
     * outer function to get the field list
     *
     * @return array
     */
    public function getFieldList()
    {
        return $this->fieldList;
    }

    /**
     * outer function to get the entities paths
     *
     * @return array
     */
    public function getEntitiesPath()
    {
        return $this->entitiesPath;
    }

    /**
     * recursive function to parse the ios definition into our structure
     *
     * @param array  $content the content
     * @param string $path    depth path (where we are)
     *
     * @return void
     */
    private function processHierarchy($content, $path = '')
    {
        if (isset($content['name'])) {
            $thisType = $this->mapToGravitonType($content, $path);
            if (!empty($path)) {
                $path .= '.';
            }
            $path .= $content['name'];
            $this->fieldList[$path] = $thisType;
        }

        $type = null;
        if (isset($content['type'])) {
            $type = $content['type'];
        }
        if (is_null($type) && isset($content['content']['type'])) {
            $this->types[$content['content']['type']] = '';
            $type = $content['content']['type'];
        }

        $fields = [];
        if (isset($content['properties']) && is_array($content['properties'])) {
            $fields = $content['properties'];
        }
        if (empty($fields) && isset($content['content']['properties']) && is_array($content['content']['properties'])) {
            $fields = $content['content']['properties'];
        }

        if (isset($content['polymorphic']) && $content['polymorphic'] === true && is_array($content['content'])) {
            /**
             * this is a polymorphic type. so we need to loop the 'content' array, take each
             * 'properties' there and merge them into $fields in sequence..
             */
            $polyType = $content;
            unset($polyType['properties']);

            foreach ($content['content'] as $subType) {
                foreach ($subType['properties'] as $subTypeField) {
                    $found = false;
                    foreach ($fields as $fieldIdx => $exField) {
                        if ($subTypeField['name'] == $exField['name']) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found) {
                        $fields[$fieldIdx] = $subTypeField;
                    } else {
                        $fields[] = $subTypeField;
                    }
                }
            }
        }

        if (!empty($fields)) {
            $this->createEntity($type, $fields, $path);

            foreach ($fields as $field) {
                $this->processHierarchy($field, $path);
            }
        }
    }

    /**
     * generate the definition for a single entity
     *
     * @param string $type   the type
     * @param array  $fields the fields
     * @param string $path   the path (where we are in recursion)
     *
     * @return void
     */
    private function createEntity($type, array $fields, $path)
    {
        if (in_array($type, $this->primitives)) {
            return;
        }

        $type = $this->iOsClassToBackend($type, $path);

        $definition = [
            'id' => $type
        ];

        $generatedFields = [];
        $fieldNames = [];
        $generatedRelations = [];
        $relationNames = [];
        $fieldCounter = 0;
        $relationCounter = 0;

        foreach ($fields as $field) {
            $fieldDef = [
                'name' => $field['name'],
                'title' => $this->getFieldTitle($field)
            ];

            if ($field['required'] === true) {
                $fieldDef['required'] = true;
            }

            if (isset($field['comment'])) {
                $fieldDef['description'] = $field['comment'];
            } else {
                $fieldDef['description'] = '';
            }

            $fieldDef = array_merge($fieldDef, $this->mapToGravitonType($field, $path, $field['name']));

            if (substr($fieldDef['type'], 0, 6) == 'class:') {
                // class type, create embed relation
                $generatedRelations[$relationCounter] = [
                    'localProperty' => $fieldDef['name'],
                    'type' => 'embed'
                ];
                $relationNames[$relationCounter] = $fieldDef['name'];
                $relationCounter++;
            }

            $generatedFields[$fieldCounter] = $fieldDef;
            $fieldNames[$fieldCounter] = $fieldDef['name'];
            $fieldCounter++;
        }

        // sort fields
        array_multisort($fieldNames, SORT_ASC, $generatedFields);

        // sort relations
        array_multisort($relationNames, SORT_ASC, $generatedRelations);

        $definition['target']['fields'] = $generatedFields;

        if (!empty($generatedRelations)) {
            $definition['target']['relations'] = $generatedRelations;
        }

        if (!isset($this->entities[$type])) {
            $this->entities[$type] = $definition;
        }

        if (!isset($this->entitiesPath[$type])) {
            $this->entitiesPath[$type] = [];
        }

        $this->entitiesPath[$type] = array_unique(array_merge($this->entitiesPath[$type], [$path]));
    }

    /**
     * here we change the "type" attribute in the ios definition to whatever we need in our definition.
     * what is returned here will be array_merge()d with whatever the basic entity generation sees fit.
     *
     * @param array  $def       definition
     * @param string $path      path
     * @param string $fieldName fieldname
     *
     * @return array definition elements
     */
    private function mapToGravitonType($def, $path, $fieldName = null)
    {
        $typeDef = [];

        $iOsType = $def['content']['type'];
        $isArray = false;

        if (in_array($iOsType, $this->primitives)) {
            if (isset($this->typeMap[$iOsType])) {
                $typeDef['type'] = $this->typeMap[$iOsType];
            } else {
                $typeDef['type'] = $iOsType;
            }
        } else {
            preg_match('/^dictionary\<(.*)\>/i', $iOsType, $matches);

            if (!isset($matches[1])) {
                // array?
                preg_match('/^array\<(.*)\>/i', $iOsType, $matches);

                $isArray = false;
                if (isset($matches[1])) {
                    $iOsType = $matches[1];

                    // change to 'any' if it's a dictionary type..
                    if (preg_match('/^dictionary\<(.*)\>/i', $iOsType)) {
                        $iOsType = 'any';
                    }

                    if ($iOsType != 'any' && $iOsType != 'EVJReference') {
                        if (empty($path)) {
                            $subPath = $def['name'];
                        } else {
                            $subPath = $path.'.'.$def['name'];
                        }
                        //$path .= $def['name'];
                        $this->processHierarchy($def['content']['content'], $subPath);
                    }
                    $isArray = true;
                }

                if ($iOsType == 'any') {
                    $typeDef['type'] = 'object';
                    if ($isArray === true) {
                        $typeDef['name'] = $def['name'].'.0';
                    }
                } elseif ($iOsType == 'EVJReference' && $isArray == true) {
                    $typeDef['type'] = 'extref';
                    $typeDef['name'] = $def['name'].'.0.ref';
                    $typeDef['exposeAs'] = '$ref';
                } elseif ($iOsType == 'EVJLocalizedStrings' && $isArray == true) {
                    $typeDef['type']
                        = 'class:GravitonDyn\\ConsultationTranslatableBundle\\Document\\ConsultationTranslatable[]';
                    $typeDef['name'] = $def['name'];
                } elseif (in_array($iOsType, $this->primitives) && $isArray == true) {
                    $arrayType = $iOsType;
                    if (isset($this->typeMap[$arrayType])) {
                        $arrayType = $this->typeMap[$arrayType];
                    }
                    $typeDef['type'] = $arrayType;
                    $typeDef['name'] = $def['name'].'.0';
                } else {
                    $iOsType = $this->iOsClassToBackend($iOsType, $path, $fieldName);

                    $className = sprintf(
                        'GravitonDyn\\%sBundle\\Document\\%s',
                        $iOsType,
                        $iOsType
                    );

                    $typeDef['type'] = 'class:' . $className;

                    if ($isArray === true) {
                        $typeDef['type'] .= '[]';
                    }
                }

                if ($def['required'] === true) {
                    $typeDef['constraints'][] = [
                        'name' => 'NotNull'
                    ];
                }
            } else {
                // dictionary type - cannot be defined
                $typeDef['type'] = 'object';
            }
        }

        if ($typeDef['type'] == 'extref' && !$isArray) {
            $typeDef['name'] = $def['name'].'.ref';
            $typeDef['exposeAs'] = '$ref';
            $typeDef['collection'][] = '*';
        }

        // decimals are always required as string but we add our constraint
        if ($typeDef['type'] == 'decimal') {
            $typeDef['type'] = 'string';
            $typeDef['constraints'][] = ['name' => 'Decimal'];
        }

        return $typeDef;
    }

    /**
     * simple function that changes the class name from ios to what we want to have (prefixed)
     *
     * @param string $className classname
     * @param string $path      the path
     * @param string $fieldName field name
     *
     * @return string our class name
     */
    private function iOsClassToBackend($className, $path = null, $fieldName = null)
    {
        if ($className == '<none>') {
            // no name given, must compose
            if (!is_null($fieldName)) {
                $path .= '.'.$fieldName;
            }
            $parts = $this->pathToClassNameParts($path);
            $className = implode('', $parts);
        }

        if (isset($this->entityMap[$className])) {
            return $this->entityMap[$className];
        }

        if (substr($className, 0, 3) == 'EVJ') {
            $className = substr($className, 3);
        }

        if (substr($className, 0, 2) == 'EV') {
            $className = substr($className, 2);
        }

        return 'Consultation'.$className;
    }

    /**
     * reads all available overrides and returns them as collected array
     *
     * @return array overrides
     */
    private function readOverrides()
    {
        $overrides = [];

        if (is_null($this->overridePath)) {
            return $overrides;
        }

        $finder = $this->finder->files()->in($this->overridePath)->name('*.json');

        foreach ($finder as $file) {
            $thisFile = json_decode(file_get_contents($file->getPathname()), true);
            if (isset($thisFile['id'])) {
                $overrides[$thisFile['id']] = $thisFile;
            }
        }

        return $overrides;
    }

    /**
     * merges the generated definitions with whatever the override wants to change
     *
     * @param array $entity   the definition
     * @param array $override contents of the override
     *
     * @return array new definition
     */
    private function applyOverride($entity, $override)
    {
        /**** FIELDS ****/

        // first, possible override renames..
        if (isset($override['override']['renames']) && is_array($override['override']['renames'])) {
            foreach ($entity['target']['fields'] as $exFieldIdx => $exFieldDef) {
                if (array_key_exists($exFieldDef['name'], $override['override']['renames'])) {
                    $entity['target']['fields'][$exFieldIdx] = array_merge(
                        $entity['target']['fields'][$exFieldIdx],
                        ['name' => $override['override']['renames'][$exFieldDef['name']] ]
                    );
                }
            }
        }

        if (isset($override['target']['fields'])) {
            foreach ($override['target']['fields'] as $fieldDef) {
                $found = false;
                foreach ($entity['target']['fields'] as $exFieldIdx => $exFieldDef) {
                    if ($exFieldDef['name'] == $fieldDef['name']) {
                        $entity['target']['fields'][$exFieldIdx] = array_merge(
                            $entity['target']['fields'][$exFieldIdx],
                            $fieldDef
                        );
                        $found = true;
                    }
                }

                // new field
                if (!$found) {
                    $entity['target']['fields'][] = $fieldDef;
                }
            }
        }

        // any removals?
        if (isset($override['override']['removals'])) {
            foreach ($entity['target']['fields'] as $exFieldIdx => $exFieldDef) {
                if (in_array($exFieldDef['name'], $override['override']['removals'])) {
                    unset($entity['target']['fields'][$exFieldIdx]);
                }
            }
            // reset index counters
            $entity['target']['fields'] = array_values($entity['target']['fields']);
        }

        /**** RELATIONS ****/

        if (isset($override['target']['relations'])) {
            foreach ($override['target']['relations'] as $relDef) {
                $found = false;
                foreach ($entity['target']['relations'] as $exRelIdx => $exRelDef) {
                    if ($exRelDef['localProperty'] == $relDef['localProperty']) {
                        $entity['target']['relations'][$exRelIdx] = array_merge(
                            $entity['target']['relations'][$exRelIdx],
                            $relDef
                        );
                        $found = true;
                    }
                }

                // new field
                if (!$found) {
                    $entity['target']['relations'][] = $relDef;
                }
            }
            $entity['target']['relations'] = array_values($entity['target']['relations']);
        }

        // any removals of relations?
        if (isset($override['override']['relationRemovals'])) {
            foreach ($entity['target']['relations'] as $exRelIdx => $exRelDef) {
                if (in_array($exRelDef['localProperty'], $override['override']['relationRemovals'])) {
                    unset($entity['target']['relations'][$exRelIdx]);
                }
            }
            // reset index counters
            $entity['target']['relations'] = array_values($entity['target']['relations']);
        }

        /**** OTHER STUFF - WE BLINDLY TAKE ****/

        if (isset($override['title'])) {
            $entity['title'] = $override['title'];
        }
        if (isset($override['description'])) {
            $entity['description'] = $override['description'];
        }
        if (isset($override['service'])) {
            $entity['service'] = $override['service'];
        }
        if (isset($override['target']['indexes'])) {
            $entity['target']['indexes'] = $override['target']['indexes'];
        }
        if (isset($override['target']['textIndexes'])) {
            $entity['target']['textIndexes'] = $override['target']['textIndexes'];
        }

        return $entity;
    }

    /**
     * Strips a object path (dud.dud.field) into an array of readable words, needed
     * to generate a path for a field
     *
     * @param string $path a field path
     *
     * @return array parts
     */
    private function pathToClassNameParts($path)
    {
        return array_map(
            function ($arg) {
                return ucfirst(strtolower($arg));
            },
            explode('.', $path)
        );
    }

    /**
     * a function to derive a field title from the name
     *
     * @todo be more creative here
     *
     * @param array $fieldDef field definition
     *
     * @return string the field title
     */
    private function getFieldTitle($fieldDef)
    {
        $name = $fieldDef['name'];
        $title = '';

        if (strtolower($name) == 'id') {
            $title = 'ID';
        }

        return $title;
    }
}
