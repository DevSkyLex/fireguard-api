<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use ApiPlatform\Metadata\ApiResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

use function class_exists;
use function dirname;
use function str_ends_with;
use function str_replace;
use function strlen;
use function substr;

/**
 * Test ApiOperationNamesTest.
 *
 * Explicit operation names are also Symfony route names. A collision used to
 * hide an unrelated resource and now prevents API Platform from loading routes.
 *
 * @category Architecture Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApiOperationNamesTest extends TestCase
{
  // #region Methods
  /**
   * Method explicitOperationNamesAreGloballyUnique.
   *
   * @since 1.0.0
   */
  #[Test]
  public function explicitOperationNamesAreGloballyUnique(): void
  {
    $root = dirname(__DIR__, 3) . '/src/';
    $names = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Resource.php')) {
        continue;
      }

      $class = str_replace(['/', '\\'], '\\', substr($file->getPathname(), strlen($root), -4));
      if (!class_exists($class)) {
        continue;
      }

      foreach (new ReflectionClass($class)->getAttributes(ApiResource::class) as $attribute) {
        foreach ($attribute->newInstance()->getOperations() ?? [] as $operation) {
          $name = $operation->getName();
          if (null === $name || null !== $operation->getRouteName()) {
            continue;
          }

          self::assertArrayNotHasKey($name, $names, 'Operation ' . $name . ' is duplicated by ' . $class);
          $names[$name] = $class;
        }
      }
    }

    self::assertNotEmpty($names);
  }
  // #endregion
}
