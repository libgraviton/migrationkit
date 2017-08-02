<?php
namespace Graviton\MigrationKit\Tests\Command;

use Graviton\MigrationKit\Command\GenerateFromIosSchemaCommand;
use Graviton\MigrationKit\Utils\GenerateFromIosSchemaUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFromIosSchemaCommandTest extends TestCase
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
        $this->sut = new GenerateFromIosSchemaCommand(
            new GenerateFromIosSchemaUtils(new Finder()),
            new Filesystem()
        );
        $this->output = $this->getMockBuilder(OutputInterface::class)
            ->getMock();
    }

    public function testGeneration()
    {
        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid().DIRECTORY_SEPARATOR;
        $tmpDirYaml = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid();
        $fs->mkdir($tmpDir);
        $fs->mkdir($tmpDirYaml);

        $arguments = [
            'schemaFile' => __DIR__.'/Resources/ios/schema.json',
            'outputDir' => $tmpDir,
            '--infoDir' => $tmpDirYaml,
            '--overrideDir' => __DIR__.'/Resources/ios/overrides/'
        ];

        $input = new ArrayInput($arguments);
        $this->sut->run($input, $this->output);

        $files = [
            'ConsultationTestEntity.json',
            'Persons/ConsultationPerson.json',
            'CommonObjects/ConsultationOtherEntity.json'
        ];

        foreach ($files as $file) {
            $this->assertFileExists($tmpDir.$file);
            $this->assertJsonFileEqualsJsonFile(
                __DIR__.'/Resources/ios/generated/'.$file,
                $tmpDir.$file
            );
        }

        // check overrides
        $main = json_decode(file_get_contents($tmpDir.'ConsultationTestEntity.json'));
        $this->assertSame('/test/entity/', $main->service->routerBase);
        $this->assertSame(['creationDate'], $main->target->indexes);

    }
}
