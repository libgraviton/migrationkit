<?php

namespace Graviton\MigrationKit\Utils;

use Symfony\Component\Finder\Finder;

/**
 * @author   List of contributors <https://github.com/libgraviton/graviton/graphs/contributors>
 * @license  http://opensource.org/licenses/gpl-license.php GNU Public License
 * @link     http://swisscom.ch
 */
class MigrationGenerateUtils {

    private $finder;

    /**
     * @var \Twig_Environment
     */
    private $twig;

    /**
     * @var GenerationUtils
     */
    private $generationUtilsOld;

    /**
     * @var GenerationUtils
     */
    private $generationUtilsNew;

    /**
     * @var string
     */
    private $outputDirectory;

    public function __construct(Finder $finder, \Twig_Environment $twig)
    {
        $this->finder = $finder;
        $this->twig = $twig;
    }

    /**
     * set GenerationUtilsOld
     *
     * @param GenerationUtils $generationUtilsOld generationUtilsOld
     *
     * @return void
     */
    public function setGenerationUtilsOld($generationUtilsOld)
    {
        $this->generationUtilsOld = $generationUtilsOld;
    }

    /**
     * set GenerationUtilsNew
     *
     * @param GenerationUtils $generationUtilsNew generationUtilsNew
     *
     * @return void
     */
    public function setGenerationUtilsNew($generationUtilsNew)
    {
        $this->generationUtilsNew = $generationUtilsNew;
    }

    /**
     * set OutputDirectory
     *
     * @param string $outputDirectory outputDirectory
     *
     * @return void
     */
    public function setOutputDirectory($outputDirectory)
    {
        $this->outputDirectory = $outputDirectory;
    }

    public function generate(MigrationDiff $diff)
    {
        if (is_null($this->outputDirectory)) {
            throw new \LogicException('Output directory for migrations not specified!');
        }

        $template = $this->twig->load('class.twig');
        $templatePartial = $this->twig->load('partial_adiscriminator.twig');
        $migrationId = $this->getMigrationId();

        $baseParams = [
            'migrationId' => $migrationId,
            'className' => 'Version'.$migrationId,
            'exposedEntity' => $this->generationUtilsOld->getRootEntity(),
            'migData' => base64_encode(serialize($diff))
        ];

        $partials = [];
        foreach ($diff->getMigrationRelevantDiffs() as $entityName => $changes) {
            $entityPaths = array_map(
                [$this->generationUtilsOld, 'translatePathToJsonPath'],
                $this->generationUtilsOld->getEntityPaths($entityName)
            );

            foreach ($changes as $fieldName => $diffOp) {
                $addedFields = [
                    'entityPaths' => implode('\','.PHP_EOL.'\'', $entityPaths),
                    'entityName' => $entityName,
                    'fieldName' => $fieldName,
                    'diff' => $diffOp
                ];

                $partials[] = $templatePartial->render(array_merge($baseParams, $addedFields));
            }

        }

        file_put_contents(
            $this->outputDirectory.'/Version'.$migrationId.'.php',
            $template->render(
                array_merge(
                    $baseParams,
                    ['partials' => implode(PHP_EOL, $partials)]
                )
            )
        );
    }

    private function getMigrationId()
    {
        return date('YmdHms');
    }
}
