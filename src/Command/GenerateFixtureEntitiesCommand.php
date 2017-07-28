<?php
/**
 * command for our definition generator
 */

namespace Graviton\MigrationKit\Command;

use Faker\Factory;
use Graviton\MigrationKit\Utils\GenerateFromIosSchemaUtils;
use Graviton\MigrationKit\Utils\GenerationUtils;
use JsonPath\JsonObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFixtureEntitiesCommand extends Command
{

    private $ymlDir;

    private $generationUtils;

    /**
     * @var \Faker\Generator
     */
    private $faker;

    private $extRefMapFile;

    private $extRefMap;

    /**
     * @param string        $destDir destination dir
     * @param GenerateFromIosSchemaUtils $utils   utils
     * @param Filesystem    $fs      symfony/filesystem instance
     *
     */
    public function __construct(
        $ymlDir,
        GenerationUtils $generationUtils,
        $extRefMapFile = null
    ) {
        parent::__construct();
        $this->ymlDir = $ymlDir;
        $this->generationUtils = $generationUtils;
        $this->faker = Factory::create();
        $this->extRefMapFile = $extRefMapFile;
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
                new InputDefinition([
                    new InputOption('outputDir', 'o', InputOption::VALUE_REQUIRED, 'Where to output our generated files'),
                    new InputOption('infoDir', 'oi', InputOption::VALUE_REQUIRED, 'Where to output our meta files (YAML)')
                ])
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
        // override here

        // extrefmap file?
        if (!is_null($this->extRefMapFile)) {
            if (!file_exists($this->extRefMapFile)) {
                throw new \LogicException(sprintf('extref map file %s does not exist', $this->extRefMapFile));
            }
            $this->extRefMap = Yaml::parse(file_get_contents($this->extRefMapFile));
        }

        $this->generationUtils->setDirectory($this->ymlDir);

        $this->generateSingleEntity();

    }

    private function generateSingleEntity()
    {
        $node = new JsonObject();

        foreach ($this->generationUtils->getFieldList() as $path => $info) {
            $value = $this->getSingleValue($path, $info);
            if (!is_null($value)) {
                $jsonPath = $this->generationUtils->translatePathToJsonPath($path);
                // if the last is an array, we don't want to set it as we set a random number of elements as array
                if (substr($jsonPath, -3) == '[*]') {
                    $jsonPath = substr($jsonPath, 0, -3);
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

        echo $node->getJson();
    }

    private function getSingleValue($path, $information) {
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
            $value = array_fill(0, $this->faker->numberBetween(1, 5), []);
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

    private function getExtRefLink($information)
    {
        $url = 'http://localhost';

        if (
            !isset($information['collection']) ||
            (isset($information['collection']) && in_array('*', $information['collection']))
        ) {
            $url .= '/file/'.$this->faker->domainWord;
            return $url;
        }

        $mappedTypes = array_keys($this->extRefMap);
        $intersect = array_intersect($information['collection'], $mappedTypes);

        if (empty($intersect)) {
            throw new \LogicException(sprintf('Could not map extref types %s. Please specify an '.
            'extref map and make sure it contains those types.', json_encode($information['collection'])));
        }

        $linkEntity = array_pop($intersect);

        $url .= $this->extRefMap[$linkEntity].$this->faker->domainWord;

        return $url;
    }

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
