<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_filter;
use function is_dir;
use function scandir;
use function sprintf;
use function str_ends_with;

/**
 * Test PersistenceNamingTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PersistenceNamingTest extends TestCase
{
  // #region Constants
  /**
   * Constant PERSISTENCE_DIR.
   *
   * Directory name for persistence classes.
   *
   * @var string
   */
  private const string PERSISTENCE_DIR = 'Persistence';
  // #endregion

  // #region Methods
  /**
   * Method testRecordsEndWithRecordSuffix.
   *
   * Ensures every class in Persistence/*\/Record/
   * ends with "Record".
   *
   * @return void no return value
   */
  #[Test]
  public function testRecordsEndWithRecordSuffix(): void
  {
    $this->assertNamingConvention('Record', 'Record');
  }

  /**
   * Method testMappersEndWithMapperSuffix.
   *
   * Ensures every class in Persistence/*\/Mapper/
   * ends with "Mapper".
   *
   * @return void no return value
   */
  #[Test]
  public function testMappersEndWithMapperSuffix(): void
  {
    $this->assertNamingConvention('Mapper', 'Mapper');
  }

  /**
   * Method testRepositoriesEndWithRepositorySuffix.
   *
   * Ensures every class in Persistence/*\/Repository/
   * ends with "Repository".
   *
   * @return void no return value
   */
  #[Test]
  public function testRepositoriesEndWithRepositorySuffix(): void
  {
    $this->assertNamingConvention('Repository', 'Repository');
  }

  /**
   * Method assertNamingConvention.
   *
   * Asserts that all files in any persistence subdirectory follow the naming convention.
   * Scans Persistence/{any}/$directory/ (e.g., Persistence/Doctrine/Record/).
   *
   * @param string $directory the subdirectory name (Record, Mapper, Repository)
   * @param string $suffix the required suffix
   */
  private function assertNamingConvention(string $directory, string $suffix): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      if (!$module->hasLayer(ArchitectureLayer::INFRASTRUCTURE)) {
        continue;
      }

      $persistenceDir = $module->layerPath(ArchitectureLayer::INFRASTRUCTURE)
        . DIRECTORY_SEPARATOR . self::PERSISTENCE_DIR;

      if (!is_dir($persistenceDir)) {
        continue;
      }

      // Scan all subdirectories under Persistence/ (Doctrine, Redis, etc.)
      $persistenceSubdirs = array_filter(
        scandir($persistenceDir) ?: [],
        fn ($entry) => '.' !== $entry && '..' !== $entry
        && is_dir($persistenceDir . DIRECTORY_SEPARATOR . $entry),
      );

      foreach ($persistenceSubdirs as $provider) {
        $targetDir = $persistenceDir
          . DIRECTORY_SEPARATOR . $provider
          . DIRECTORY_SEPARATOR . $directory;

        if (!is_dir($targetDir)) {
          continue;
        }

        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($targetDir),
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
              '%s\\Infrastructure\\Persistence\\%s\\%s\\%s must end with suffix "%s".',
              $module->namespace,
              $provider,
              $directory,
              $shortName,
              $suffix,
            );
          }
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: sprintf('Every class in Persistence/*/%s/ must end with suffix "%s".', $directory, $suffix),
    );
  }
  // #endregion
}
