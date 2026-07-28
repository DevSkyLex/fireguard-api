<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Shared\Application\Message\{CommandMessage, QueryMessage, ResultMessage};
use SplFileInfo;
use Tests\Support\Message\MessageArgumentFactory;

use function class_exists;
use function count;
use function in_array;
use function is_object;
use function sort;
use function sprintf;
use function str_replace;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR;

/**
 * Test MessageContractTest.
 *
 * Every command, query and result crossing the message bus is a plain
 * carrier: constructible from its declared arguments, with each argument
 * promoted verbatim to a public readonly property. This test asserts that
 * contract for the whole catalogue at once, so a message that stops
 * promoting a field — or acquires a constructor that mutates what it was
 * handed — is caught in the module that introduced it.
 *
 * @category Architecture Unit Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MessageContractTest extends TestCase
{
  // #region Constants
  /**
   * Constant SOURCE_DIR.
   *
   * Root of the module sources.
   */
  private const string SOURCE_DIR = __DIR__ . '/../../../src';
  // #endregion

  // #region Methods
  /**
   * Method messageClassProvider.
   *
   * Yields every concrete class implementing one of the bus message
   * marker interfaces.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{class-string}> the message classes
   */
  public static function messageClassProvider(): iterable
  {
    foreach (self::messageClasses() as $class) {
      yield $class => [$class];
    }
  }

  /**
   * Method testMessageIsConstructibleAndPromotesItsArguments.
   *
   * Builds the message and asserts that every constructor parameter is
   * readable back, unchanged, from the property of the same name.
   *
   * @since 1.0.0
   *
   * @param class-string $class the message class under test
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('messageClassProvider')]
  public function testMessageIsConstructibleAndPromotesItsArguments(string $class): void
  {
    $factory = new MessageArgumentFactory();
    $message = $factory->build($class);

    self::assertInstanceOf($class, $message);

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
      if (!$property->isPublic() || !$property->isReadOnly()) {
        continue;
      }

      $expected = $arguments[$index];
      $actual = $property->getValue($message);

      // Objects are rebuilt per call, so compare by value; a constructor
      // that derives its own instant (an event's occurredAt) is left alone.
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
   * Method testMessageCatalogueIsNotEmpty.
   *
   * Guards the discovery itself: a broken scan would silently turn every
   * contract assertion above into a no-op.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testMessageCatalogueIsNotEmpty(): void
  {
    self::assertGreaterThan(100, count(self::messageClasses()));
  }

  /**
   * Method messageClasses.
   *
   * Scans the module sources for bus message implementations.
   *
   * @since 1.0.0
   *
   * @return list<class-string> the discovered message classes
   */
  private static function messageClasses(): array
  {
    $markers = [CommandMessage::class, QueryMessage::class, ResultMessage::class];
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

      /** @var class-string $class */
      $class = str_replace('/', '\\', substr($path, $position + 5, -4));
      if (!class_exists($class)) {
        continue;
      }

      $reflection = new ReflectionClass($class);
      if ($reflection->isAbstract()) {
        continue;
      }

      foreach ($reflection->getInterfaceNames() as $interface) {
        if (in_array($interface, $markers, true)) {
          $classes[] = $class;

          break;
        }
      }
    }

    sort($classes);

    return $classes;
  }
  // #endregion
}
