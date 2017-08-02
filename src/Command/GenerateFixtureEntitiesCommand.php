<?php
/**
 * command that generates random test entities that can be inserted
 */

namespace Graviton\MigrationKit\Command;

use Faker\Factory;
use Graviton\MigrationKit\Utils\GenerationUtils;
use JsonPath\JsonObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFixtureEntitiesCommand extends Command
{

    /**
     * @var string
     */
    private $ymlDir;

    /**
     * @var GenerationUtils
     */
    private $generationUtils;

    /**
     * @var Filesystem
     */
    private $fs;

    /**
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * @var null|string
     */
    private $extRefMapFile;

    /**
     * @var array
     */
    private $extRefMap = [];

    /**
     * @param GenerationUtils $generationUtils utils
     * @param Filesystem      $fs              fs
     */
    public function __construct(
        GenerationUtils $generationUtils,
        Filesystem $fs
    ) {
        parent::__construct();
        $this->generationUtils = $generationUtils;
        $this->fs = $fs;
        $this->faker = Factory::create();
    }

    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('graviton:fixture-entity:generate')
            ->setDescription(
                'Generates random example JSON entities conforming to a given structure.'
            )
            ->setDefinition(
                new InputDefinition(
                    [
                    new InputArgument(
                        'metaDir',
                        InputArgument::REQUIRED,
                        'Where to read the YML metafiles from.'
                    ),
                    new InputArgument(
                        'outputDir',
                        InputArgument::REQUIRED,
                        'Where to output the files'
                    ),
                    new InputArgument(
                        'number',
                        InputArgument::OPTIONAL,
                        'Amount of entities to generate',
                        10
                    ),
                    new InputArgument(
                        'refMap',
                        InputArgument::OPTIONAL,
                        'Path to an optional refMap File, mapping extref Collections to urls'
                    )
                    ]
                )
            );
    }

    /**
     * execute the command
     *
     * @param InputInterface  $input  input
     * @param OutputInterface $output output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $number = (int) $input->getArgument('number');

        $outputDir = $input->getArgument('outputDir');
        if (substr($outputDir, -1) != '/') {
            $outputDir .= '/';
        }

        $this->ymlDir = $input->getArgument('metaDir');
        $this->extRefMapFile = $input->getArgument('refMap');

        // extrefmap file?
        if (!is_null($this->extRefMapFile)) {
            if (!file_exists($this->extRefMapFile)) {
                throw new \LogicException(sprintf('extref map file %s does not exist', $this->extRefMapFile));
            }
            $this->extRefMap = Yaml::parse(file_get_contents($this->extRefMapFile));
        }

        $this->generationUtils->setDirectory($this->ymlDir);

        for ($i = 0; $i < $number; $i++) {
            $entity = $this->generateSingleEntity();
            $filename = $outputDir.$entity['id'].'.json';
            $this->fs->dumpFile($filename, json_encode($entity['entity'], JSON_PRETTY_PRINT));
            $output->writeln('Wrote '.$filename);
        }
    }

    /**
     * generates a single entity
     *
     * @return array array of id and entity
     */
    private function generateSingleEntity()
    {
        $node = new JsonObject(null, true);

        foreach ($this->generationUtils->getFieldList() as $path => $info) {
            $value = $this->getSingleValue($path, $info);
            if (!is_null($value)) {
                $jsonPath = $this->generationUtils->translatePathToJsonPath($path);

                // if the last is an array, we don't want to set it as we set a random number of elements as array
                if (substr($jsonPath, -3) == '[*]') {
                    $jsonPath = substr($jsonPath, 0, -3);
                }

                /** FILL ARRAYS */
                $arrayParts = explode('[*]', $jsonPath);
                // take the last away
                array_pop($arrayParts);

                foreach ($arrayParts as $idx => $arrayPart) {
                    $thisArrayPart = implode('[*]', array_slice($arrayParts, 0, $idx + 1));

                    // do we have an array there?
                    if ($node->get($thisArrayPart) === false) {
                        $node->set($thisArrayPart, $this->getRandomSizedArray());
                    }
                }

                // apply exposeAs
                if (isset($info['exposeAs'])) {
                    $pathParts = explode('.', $jsonPath);
                    array_pop($pathParts);
                    $pathParts[] = $info['exposeAs'];
                    $jsonPath = implode('.', $pathParts);
                }

                $node->set($jsonPath, $value);
            }
        }


        // does have id?
        $id = $node->get('$.id');
        if ($id === false) {
            $id = uniqid();
        }

        return [
            'id' => $id,
            'entity' => $node->getValue()
        ];
    }

    /**
     * gets the value for a given field
     *
     * @param string $path        path
     * @param array  $information field information
     *
     * @return array|bool|float|null|string value
     */
    private function getSingleValue($path, $information)
    {
        $value = null;
        $type = strtolower($information['type']);
        if ($type == 'varchar') {
            $type = 'string';
        }
        if ($type == 'double') {
            $type = 'float';
        }
        $name = '';
        if (isset($information['name'])) {
            $name = $information['name'];
        }
        $constraints = '';
        if (isset($information['constraints'])) {
            $constraints = json_encode($information['constraints']);
        }
        $translatable = false;
        if (isset($information['translatable'])) {
            $translatable = $information['translatable'];
        }

        if ((preg_match('/\.(gu)?(id)$/i', $path) || $path == 'id') && $type == 'string') {
            $value = $this->faker->uuid;
        } elseif ($translatable === true) {
            // a single translatable
            $value = [
                'en' => $this->faker->words(2, true),
                'de' => $this->faker->words(2, true),
                'fr' => $this->faker->words(2, true)
            ];
        } elseif (preg_match('/\.*name*$/i', $path) && $type == 'string') {
            $value = $this->faker->name;
        } elseif ($type == 'boolean') {
            $value = $this->faker->boolean();
        } elseif ($type == 'datetime') {
            $value = $this->faker->iso8601();
        } elseif ($type == 'float') {
            $value = $this->faker->randomFloat(null, 1);
        } elseif ($type == 'int') {
            $value = $this->faker->numberBetween(1);
        } elseif ($type == 'extref') {
            $value = $this->getExtRefLink($information);
        } elseif ($type == 'string' && strpos($constraints, 'Decimal') !== false) {
            $value = strval($this->faker->randomFloat(null, 1));
        } elseif ($type == 'string') {
            $value = $this->faker->words(2, true);
        } elseif ($this->generationUtils->isClassArrayType($path)) {
            // array of object
            $value = $this->getRandomSizedArray();
        } elseif ($type == 'object') {
            // array of object
            $value = [
                $this->faker->word => $this->faker->word
            ];
        }

        if (!empty($constraints)) {
            $value = $this->getConstraintValue($value, $type, $information['constraints']);
        }

        if (strpos($name, '.0') !== false) {
            $value = [$value];
        }

        if (strtolower($name) == 'recordorigin') {
            $value = 'faker';
        }

        return $value;
    }

    /**
     * Returns a randomly sized empty array
     *
     * @return array random sized array
     */
    private function getRandomSizedArray()
    {
        return array_fill(0, $this->faker->numberBetween(1, 5), []);
    }

    /**
     * gets the value for an extref field
     *
     * @param array $information field information
     *
     * @return string value
     */
    private function getExtRefLink($information)
    {
        $url = 'http://localhost';

        if (!isset($information['collection'])
            || (isset($information['collection']) && in_array('*', $information['collection']))
        ) {
            $url .= '/file/'.$this->faker->domainWord;
            return $url;
        }

        $mappedTypes = array_keys($this->extRefMap);
        $intersect = array_intersect($information['collection'], $mappedTypes);

        if (empty($intersect)) {
            throw new \LogicException(
                sprintf(
                    'Could not map extref types %s. Please specify an '.
                    'extref map and make sure it contains those types.',
                    json_encode($information['collection'])
                )
            );
        }

        $linkEntity = array_pop($intersect);

        $url .= $this->extRefMap[$linkEntity].$this->faker->domainWord;

        return $url;
    }

    /**
     * gets the value for a constrained field
     *
     * @param mixed  $value       value
     * @param string $type        type
     * @param array  $constraints constraints
     *
     * @return float|int value
     */
    private function getConstraintValue($value, $type, $constraints)
    {
        foreach ($constraints as $constraint) {
            if ($constraint['name'] == 'Choice') {
                $choices = explode('|', $constraint['options'][0]['value']);
                $chosen = $choices[array_rand($choices)];
                if (is_int($value)) {
                    $value = intval($chosen);
                } else {
                    $value = $chosen;
                }
            }
            if ($constraint['name'] == 'Email') {
                $value = $this->faker->companyEmail;
            }
            if ($constraint['name'] == 'Range') {
                $minVal = 0;
                $maxVal = PHP_INT_MAX;

                foreach ($constraint['options'] as $option) {
                    if ($option['name'] == 'min') {
                        $minVal = floatval($option['value']);
                    }
                    if ($option['name'] == 'max') {
                        $maxVal = floatval($option['value']);
                    }
                }

                $value = $this->faker->randomFloat(null, $minVal, $maxVal);

                if ($type == 'int') {
                    $value = ceil($value);
                }
            }
        }

        return $value;
    }
}
