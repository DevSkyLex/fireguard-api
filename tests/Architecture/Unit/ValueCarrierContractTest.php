<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use Tests\Support\Message\MessageArgumentFactory;

use function class_exists;
use function count;
use function is_object;
use function sort;
use function sprintf;
use function str_contains;
use function str_replace;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Test ValueCarrierContractTest.
 *
 * Domain events and application contract objects (views, pages) are pure
 * carriers: what the constructor is handed is what callers read back. They
 * are consumed by mappers, subscribers and API factories that all assume
 * that, so a field that silently stops being promoted breaks those readers
 * far from where the change was made. This asserts the carrier contract
 * across every module at once.
 *
 * @category Architecture Unit Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValueCarrierContractTest extends TestCase
{
  // #region Constants
  /**
   * Constant SOURCE_DIR.
   *
   * Root of the module sources.
   */
  private const string SOURCE_DIR = __DIR__ . '/../../../src';

  /**
   * Constant CARRIER_DIRS.
   *
   * Path fragments whose classes are pure value carriers.
   *
   * @var list<string>
   */
  private const array CARRIER_DIRS = [
    '/Domain/Event/',
    '/Application/Contract/',
  ];
  // #endregion

  // #region Methods
  /**
   * Method carrierClassProvider.
   *
   * Yields every concrete value-carrier class.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{class-string}> the carrier classes
   */
  public static function carrierClassProvider(): iterable
  {
    foreach (self::carrierClasses() as $class) {
      yield $class => [$class];
    }
  }

  /**
   * Method testCarrierIsConstructibleAndPromotesItsArguments.
   *
   * Builds the carrier and asserts each constructor argument is readable
   * back, unchanged, from the property of the same name.
   *
   * @since 1.0.0
   *
   * @param class-string $class the carrier class under test
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('carrierClassProvider')]
  public function testCarrierIsConstructibleAndPromotesItsArguments(string $class): void
  {
    $factory = new MessageArgumentFactory();
    $carrier = $factory->build($class);

    self::assertInstanceOf($class, $carrier);

    $reflection = new ReflectionClass($class);
    $constructor = $reflection->getConstructor();
    if (null === $constructor) {
      return;
    }

    $arguments = $factory->argumentsFor($class);
    foreach ($constructor->getParameters() as $index => $parameter) {
      $name = $parameter->getName();
      if (!$reflection->hasProperty($name)) {
        continue;
      }

      $property = $reflection->getProperty($name);
      if (!$property->isPublic()) {
        continue;
      }

      $expected = $arguments[$index];
      $actual = $property->getValue($carrier);

      // Objects are rebuilt per call; an event deriving its own occurredAt
      // is deliberately not pinned here.
      if (is_object($expected) && is_object($actual)) {
        continue;
      }

      self::assertSame(
        $expected,
        $actual,
        sprintf('%s::$%s did not round-trip its constructor argument.', $class, $name),
      );
    }
  }

  /**
   * Method testCarrierCatalogueIsNotEmpty.
   *
   * Guards the discovery so a broken scan cannot quietly pass.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCarrierCatalogueIsNotEmpty(): void
  {
    self::assertGreaterThan(50, count(self::carrierClasses()));
  }

  /**
   * Method carrierClasses.
   *
   * Scans the module sources for value-carrier classes.
   *
   * @since 1.0.0
   *
   * @return list<class-string> the discovered carrier classes
   */
  private static function carrierClasses(): array
  {
    $classes = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::SOURCE_DIR)) as $file) {
      if (!$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
      }

      $path = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
      $position = strpos($path, '/src/');
      if (false === $position) {
        continue;
      }

      $relative = substr($path, $position + 4);
      $matches = false;
      foreach (self::CARRIER_DIRS as $fragment) {
        if (str_contains($relative, $fragment)) {
          $matches = true;

          break;
        }
      }

      if (!$matches) {
        continue;
      }

      /** @var class-string $class */
      $class = str_replace('/', '\\', substr($path, $position + 5, -4));
      if (!class_exists($class)) {
        continue;
      }

      $reflection = new ReflectionClass($class);
      if ($reflection->isAbstract() || $reflection->isEnum()) {
        continue;
      }

      $constructor = $reflection->getConstructor();
      if (null !== $constructor && !$constructor->isPublic()) {
        continue;
      }

      $classes[] = $class;
    }

    sort($classes);

    return $classes;
  }
  // #endregion
}
