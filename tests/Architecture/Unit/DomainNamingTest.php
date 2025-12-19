<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function is_dir;
use function sprintf;
use function str_ends_with;

/**
 * Test DomainNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DomainNamingTest extends TestCase
{
    // #region Methods
    /**
     * Method testEventsEndWithEventSuffix.
     *
     * Ensures every class in Domain/Event/
     * ends with "Event".
     *
     * @return void no return value
     */
    #[Test]
    public function testEventsEndWithEventSuffix(): void
    {
        $this->assertDomainNaming('Event', 'Event');
    }

    /**
     * Method testExceptionsEndWithExceptionSuffix.
     *
     * Ensures every class in Domain/Exception/ ends with "Exception".
     *
     * @return void no return value
     */
    #[Test]
    public function testExceptionsEndWithExceptionSuffix(): void
    {
        $this->assertDomainNaming('Exception', 'Exception');
    }

    /**
     * Method assertDomainNaming.
     *
     * Asserts that all files in a domain subdirectory follow the naming convention.
     *
     * @param string $directory the subdirectory name under Domain/
     * @param string $suffix    the required suffix
     *
     * @return void no return value
     */
    private function assertDomainNaming(string $directory, string $suffix): void
    {
        $violations = [];

        foreach (ModuleCollection::all() as $module) {
            if (!$module->hasLayer(ArchitectureLayer::DOMAIN)) {
                continue;
            }

            $targetDir = $module->layerPath(ArchitectureLayer::DOMAIN)
              . DIRECTORY_SEPARATOR . $directory;

            if (!is_dir($targetDir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($targetDir)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo) {
                    continue;
                }
                if (!$file->isFile() || 'php' !== $file->getExtension()) {
                    continue;
                }

                $shortName = $file->getBasename('.php');

                if (!str_ends_with($shortName, $suffix)) {
                    $violations[] = sprintf(
                        '%s\\Domain\\%s\\%s must end with suffix "%s".',
                        $module->namespace,
                        $directory,
                        $shortName,
                        $suffix
                    );
                }
            }
        }

        self::assertSame(
            expected: [],
            actual: $violations,
            message: sprintf('Every class in Domain/%s/ must end with suffix "%s".', $directory, $suffix)
        );
    }
    // #endregion
}
