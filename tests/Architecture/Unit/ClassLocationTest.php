<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use App\Tests\Architecture\Support\{ArchitectureLayer, ModuleCollection};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function sprintf;
use function str_ends_with;
use function str_replace;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;

/**
 * Test ClassLocationTest.
 *
 * @category Architecture Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClassLocationTest extends TestCase
{
  // #region Methods
  /**
   * Method testAdaptersAreOnlyInInfrastructure.
   *
   * Ensures classes ending with "Adapter"
   * are in Infrastructure/.
   *
   * @return void no return value
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
        new RecursiveDirectoryIterator($module->path),
      );

      foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo) {
          continue;
        }
        if (!$file->isFile() || 'php' !== $file->getExtension()) {
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
          $pathname,
        );

        // Must be in Infrastructure/
        if (!str_starts_with($relativePath, 'Infrastructure')) {
          $classPath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
          $classPath = str_replace('.php', '', $classPath);

          $violations[] = sprintf(
            '%s\\%s must be in Infrastructure/, found outside.',
            $module->namespace,
            $classPath,
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: 'Classes ending with "Adapter" must be in Infrastructure/.',
    );
  }

  /**
   * Method testPortsAreOnlyInPortDirectory.
   *
   * Ensures classes ending with "Port"
   * are only in Application/Port/.
   *
   * @return void no return value
   */
  #[Test]
  public function testPortsAreOnlyInPortDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Port',
      allowedPath: 'Application' . DIRECTORY_SEPARATOR . 'Port',
    );
  }

  /**
   * Method testRecordsAreOnlyInPersistenceDirectory.
   *
   * Ensures classes ending with "Record"
   * are only in Infrastructure/Persistence/.
   *
   * @return void no return value
   */
  #[Test]
  public function testRecordsAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Record',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence',
    );
  }

  /**
   * Method testRepositoriesAreOnlyInPersistenceDirectory.
   *
   * Ensures classes ending with "Repository"
   * are only in Infrastructure/Persistence/.
   *
   * @return void no return value
   */
  #[Test]
  public function testRepositoriesAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Repository',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence',
    );
  }

  /**
   * Method testMappersAreOnlyInPersistenceDirectory.
   *
   * Ensures classes ending with "Mapper"
   * are only in Infrastructure/Persistence/.
   *
   * @return void no return value
   */
  #[Test]
  public function testMappersAreOnlyInPersistenceDirectory(): void
  {
    $this->assertSuffixOnlyInDirectory(
      suffix: 'Mapper',
      allowedPath: 'Infrastructure' . DIRECTORY_SEPARATOR . 'Persistence',
    );
  }

  /**
   * The suffix and layer role must agree; a generic persistence mapper cannot move to Application.
   */
  #[Test]
  #[DataProvider('mapperLocationProvider')]
  public function testMapperPlacementFollowsItsRole(string $name, string $path, bool $allowed): void
  {
    self::assertSame($allowed, self::isAllowedMapperLocation($name, $path));
  }

  /**
   * @return array<string, array{string, string, bool}>
   */
  public static function mapperLocationProvider(): array
  {
    return [
      'persistence' => ['EquipmentMapper', 'Infrastructure/Persistence/Doctrine/Mapper/EquipmentMapper.php', true],
      'private aggregate to public view' => ['EquipmentViewMapper', 'Application/Service/EquipmentViewMapper.php', true],
      'HTTP exception translation' => ['EquipmentExceptionMapper', 'Presentation/Api/Service/EquipmentExceptionMapper.php', true],
      'persistence outside infrastructure' => ['EquipmentMapper', 'Application/Service/EquipmentMapper.php', false],
      'view in domain' => ['EquipmentViewMapper', 'Domain/Service/EquipmentViewMapper.php', false],
      'persistence disguised as presentation helper' => ['EquipmentMapper', 'Presentation/Api/Service/EquipmentMapper.php', false],
      'exception business orchestration' => ['EquipmentExceptionMapper', 'Application/Service/EquipmentExceptionMapper.php', false],
      'view adapter outside persistence' => ['EquipmentViewMapper', 'Infrastructure/Adapter/EquipmentViewMapper.php', false],
    ];
  }

  /**
   * Allowed service roles remain subject to the layer and exact module dependency gates.
   */
  private static function isAllowedMapperLocation(string $name, string $relativePath): bool
  {
    $path = str_replace('\\', '/', $relativePath);

    return str_starts_with($path, 'Infrastructure/Persistence/')
      || (str_ends_with($name, 'ViewMapper') && str_starts_with($path, 'Application/Service/'))
      || (str_ends_with($name, 'ExceptionMapper') && str_starts_with($path, 'Presentation/Api/Service/'));
  }

  /**
   * Method assertSuffixOnlyInDirectory.
   *
   * Asserts that all classes with a given suffix
   * are located in the allowed path.
   *
   * @param string $suffix the class suffix to check
   * @param string $allowedPath the path (relative to module) where classes with this suffix should be
   *
   * @return void no return value
   */
  private function assertSuffixOnlyInDirectory(string $suffix, string $allowedPath): void
  {
    $violations = [];

    foreach (ModuleCollection::all() as $module) {
      $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($module->path),
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
          continue;
        }

        $pathname = $file->getPathname();
        $relativePath = str_replace(
          $module->path . DIRECTORY_SEPARATOR,
          '',
          $pathname,
        );

        if (!str_starts_with($relativePath, $allowedPath . DIRECTORY_SEPARATOR)
          && !('Mapper' === $suffix && self::isAllowedMapperLocation($shortName, $relativePath))) {
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
            dirname($relativePathFormatted),
          );
        }
      }
    }

    self::assertSame(
      expected: [],
      actual: $violations,
      message: sprintf('Classes ending with "%s" must only be in their designated directory.', $suffix),
    );
  }
  // #endregion
}
