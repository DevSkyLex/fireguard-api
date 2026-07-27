<?php

declare(strict_types=1);

namespace App\Tests\Architecture\Unit;

use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Tests\Support\Message\MessageArgumentFactory;
use Throwable;

use function class_exists;
use function count;
use function is_a;
use function sort;
use function sprintf;
use function str_contains;
use function str_replace;
use function strpos;
use function substr;
use function trim;

use const DIRECTORY_SEPARATOR;

/**
 * Test DomainExceptionFactoryTest.
 *
 * Domain and application exceptions are raised through named static
 * factories whose message is what reaches the API error payload and the
 * audit ledger. A factory that returns the wrong type, or builds a blank
 * message, degrades every error surface at once — and is easy to miss
 * because the failure path is rarely exercised. This walks every factory
 * in every module.
 *
 * @category Architecture Unit Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DomainExceptionFactoryTest extends TestCase
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
   * Method exceptionFactoryProvider.
   *
   * Yields every public static factory declared on a domain exception.
   *
   * @since 1.0.0
   *
   * @return iterable<string, array{class-string, string}> class/method pairs
   */
  public static function exceptionFactoryProvider(): iterable
  {
    foreach (self::exceptionFactories() as [$class, $method]) {
      yield $class . '::' . $method => [$class, $method];
    }
  }

  /**
   * Method testFactoryBuildsTheExceptionWithANonEmptyMessage.
   *
   * Calls the factory with synthesized arguments and asserts it returns
   * its own exception type carrying a usable message.
   *
   * @since 1.0.0
   *
   * @param class-string $class the exception class
   * @param string $method the factory method name
   *
   * @return void no return value
   */
  #[Test]
  #[DataProvider('exceptionFactoryProvider')]
  public function testFactoryBuildsTheExceptionWithANonEmptyMessage(string $class, string $method): void
  {
    $factory = new MessageArgumentFactory();
    $reflection = new ReflectionMethod($class, $method);

    $arguments = [];
    foreach ($reflection->getParameters() as $parameter) {
      $arguments[] = $parameter->isDefaultValueAvailable()
        ? $parameter->getDefaultValue()
        : $factory->argumentValueFor($parameter);
    }

    $exception = $reflection->invokeArgs(null, $arguments);

    self::assertInstanceOf(Throwable::class, $exception);
    self::assertInstanceOf($class, $exception);
    self::assertNotSame(
      '',
      trim($exception->getMessage()),
      sprintf('%s::%s() produced a blank message.', $class, $method),
    );
  }

  /**
   * Method testFactoryCatalogueIsNotEmpty.
   *
   * Guards the discovery so a broken scan cannot quietly pass.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFactoryCatalogueIsNotEmpty(): void
  {
    self::assertGreaterThan(50, count(self::exceptionFactories()));
  }

  /**
   * Method exceptionFactories.
   *
   * Scans Domain/Exception and Application/Exception directories for
   * public static factories that return the exception itself.
   *
   * @since 1.0.0
   *
   * @return list<array{class-string, string}> the discovered factories
   */
  private static function exceptionFactories(): array
  {
    $factories = [];

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
      if (!str_contains($relative, '/Domain/Exception/') && !str_contains($relative, '/Application/Exception/')) {
        continue;
      }

      /** @var class-string $class */
      $class = str_replace('/', '\\', substr($path, $position + 5, -4));
      if (!class_exists($class) || !is_a($class, Throwable::class, true)) {
        continue;
      }

      $reflection = new ReflectionClass($class);
      if ($reflection->isAbstract()) {
        continue;
      }

      foreach ($reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() !== $class) {
          continue;
        }

        $returnType = $method->getReturnType();
        if (null === $returnType || 'self' !== (string) $returnType && 'static' !== (string) $returnType && $class !== (string) $returnType) {
          continue;
        }

        $factories[] = [$class, $method->getName()];
      }
    }

    sort($factories);

    return $factories;
  }
  // #endregion
}
