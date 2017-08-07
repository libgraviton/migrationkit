<?php
namespace Graviton\MigrationKit\Tests\Command;

use Graviton\MigrationKit\Command\GenerateFixtureEntitiesCommand;
use Graviton\MigrationKit\Utils\GenerationUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @author   List of contributors <https://github.com/libgraviton/migrationkit/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class GenerateFixtureEntitiesCommandTest extends TestCase
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
        $this->sut = new GenerateFixtureEntitiesCommand(
            new GenerationUtils(),
            new Filesystem()
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
            'definitionDir' => __DIR__.'/Resources/definition/',
            'outputDir' => $tmpDir,
            'number' => 3,
            '--refMap' => __DIR__.'/Resources/metadata/refMap.yml'
        ];

        $input = new ArrayInput($arguments);
        $this->sut->run($input, $this->output);

        // let's see what came up..
        $files = scandir($tmpDir);
        $this->assertSame(5, count($files)); // it's 5 as '.' and '..' are also found.. so 3+2

        // open a file
        $file = $files[2];
        $gen = json_decode(file_get_contents($tmpDir.DIRECTORY_SEPARATOR.$file));

        // assert some complicated stuff
        $this->assertNotEmpty($gen->others[0]->someRef[0]->{'$ref'});
        $this->assertNotEmpty($gen->other->someRef[0]->{'$ref'});
        $this->assertNotEmpty($gen->deepNestedProperty->thisis->one->goes->very->deep->down->thats->good);
        $this->assertNotEmpty($gen->deepNestedArray->very->deep->glorious[0]->object);
        $this->assertContains($gen->choices, ['<','>','=','>=','<=','<>']);

        // delete tmpdir
        $fs->remove($tmpDir);
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotExistingSourceDir()
    {
        $arguments = [
            'metaDir' => 'fred',
            'outputDir' => sys_get_temp_dir()
        ];

        $input = new ArrayInput($arguments);
        $this->sut->run($input, $this->output);
    }
}
