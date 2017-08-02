<?php
namespace Graviton\MigrationKit\Tests\Command;

use Graviton\MigrationKit\Command\GenerateDefinitionMetadataCommand;
use Graviton\MigrationKit\Utils\MetadataUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateDefinitionMetadataCommandTest extends TestCase
{

    /**
     * @var Command
     */
    var $sut;

    /**
     * @var OutputInterface
     */
    var $output;

    /**
     * @before
     */
    public function createCommand()
    {
        $this->sut = new GenerateDefinitionMetadataCommand(
            new MetadataUtils(),
            new Finder()
        );
        $this->output = $this->getMockBuilder(OutputInterface::class)
            ->getMock();
    }

    public function testGeneration()
    {
        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        $fs->mkdir($tmpDir);

        $arguments = [
            'sourceDir' => __DIR__.'/Resources/definition/',
            'outputDir' => $tmpDir,
            'entityName' => null
        ];

        $input = new ArrayInput($arguments);
        $this->sut->run($input, $this->output);

        $this->assertSame(
            Yaml::parse(file_get_contents(__DIR__.'/Resources/metadata/_entitiesPath.yml')),
            Yaml::parse(file_get_contents($tmpDir.'/_entitiesPath.yml'))
        );

        $this->assertSame(
            Yaml::parse(file_get_contents(__DIR__.'/Resources/metadata/_fieldList.yml')),
            Yaml::parse(file_get_contents($tmpDir.'/_fieldList.yml'))
        );
    }
}
