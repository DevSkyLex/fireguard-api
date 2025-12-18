<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ModuleCollection, ArchitectureLayer};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Test ClassLocationTest
 * @final
 *
 * Ensures classes with specific suffixes are located in their correct directories:
 * - *Adapter must be in Infrastructure/ (anywhere containing "Adapter" in path)
 * - *Port must be in Application/Port/
 * - *Record must be in Infrastructure/Persistence/
 * - *Repository must be in Infrastructure/Persistence/
 *
 * @category Architecture Unit Tests
 * @package App\Tests\Architecture\Unit
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClassLocationTest extends TestCase
{
  //#region Methods
  /**
   * Method testAdaptersAreOnlyInInfrastructure
   *
   * Ensures classes ending with "Adapter" 
   * are in Infrastructure/.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testAdaptersAreOnlyInInfrastructure(): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::INFRASTRUCTURE)) {
        continue;
      }

      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($module->path)
      );

      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || $file->getExtension() !== 'php') {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, 'Adapter')) {
          continue;
        }

        $pathname = $file->getPathname();
        $relativePath = str_replace(
          $module->path . DIRECTORY_SEPARATOR,
          '',
          $pathname
        );

        // Must be in Infrastructure/
        if (!str_starts_with($relativePath, 'Infrastructure')) {
          $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
          $classPath = str_replace('.php', '', $classPath);

          $violations[] = sprintf(
            '%s\\%s must be in Infrastructure/, found outside.',
            $module->namespace,
            $classPath
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Classes ending with "Adapter" must be in Infrastructure/.'
    );
  }

  /**
   * Method testPortsAreOnlyInPortDirectory
   *
   * Ensures classes ending with "Port" 
   * are only in Application/Port/.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testPortsAreOnlyInPortDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Port',
      allowedPath: 'Application' . DIRECTORY_SEPARATOR . 'Port'
    );
  }

  /**
   * Method testRecordsAreOnlyInPersistenceDirectory
   *
   * Ensures classes ending with "Record" 
   * are only in Infrastructure/Persistence/.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testRecordsAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Record',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence'
    );
  }

  /**
   * Method testRepositoriesAreOnlyInPersistenceDirectory
   *
   * Ensures classes ending with "Repository" 
   * are only in Infrastructure/Persistence/.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testRepositoriesAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Repository',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence'
    );
  }

  /**
   * Method testMappersAreOnlyInPersistenceDirectory
   *
   * Ensures classes ending with "Mapper" 
   * are only in Infrastructure/Persistence/.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testMappersAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Mapper',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence'
    );
  }

  /**
   * Method assertSuffixOnlyInDirectory
   *
   * Asserts that all classes with a given suffix 
   * are located in the allowed path.
   *
   * @access private
   *
   * @param string $suffix The class suffix to check.
   * @param string $allowedPath The path (relative to module) where classes with this suffix should be.
   * 
   * @return void No return value.
   */
  private function assertSuffixOnlyInDirectory(string $suffix, string $allowedPath): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($module->path)
      );

      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || $file->getExtension() !== 'php') {
          continue;
        }

        $shortName = $file->getBasename('.php');

        if (!str_ends_with($shortName, $suffix)) {
          continue;
        }

        $pathname = $file->getPathname();
        $relativePath = str_replace(
          $module->path . DIRECTORY_SEPARATOR,
          '',
          $pathname
        );

        if (!str_starts_with($relativePath, $allowedPath)) {
          $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
          $classPath = str_replace('.php', '', $classPath);
          $allowedPathFormatted = str_replace(DIRECTORY_SEPARATOR, '/', $allowedPath);
          $relativePathFormatted = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

          $violations[] = sprintf(
            '%s\\%s: Classes ending with "%s" must be in %s/, found in %s.',
            $module->namespace,
            $classPath,
            $suffix,
            $allowedPathFormatted,
            dirname($relativePathFormatted)
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: sprintf('Classes ending with "%s" must only be in their designated directory.', $suffix)
    );
  }
  //#endregion
}
