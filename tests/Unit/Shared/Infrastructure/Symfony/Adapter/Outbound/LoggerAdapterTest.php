<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shared\Application\Log\LogLevel;
use Shared\Infrastructure\Symfony\Adapter\Outbound\LoggerAdapter;

/**
 * Test LoggerAdapter
 * @final
 *
 * Test the LoggerAdapter class.
 *
 * @category Infrastructure Test
 * @package Tests\Shared\Infrastructure\Symfony\Adapter\Outbound
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class LoggerAdapterTest extends TestCase
{
  //#region Methods
  /**
   * Method testLogDelegatesToPsrLogger
   *
   * Ensure that the log method delegates to
   * the underlying PSR logger with the
   * expected parameters.
   *
   * @access public
   *
   * @return void No return value
   */
  public function testLogDelegatesToPsrLogger(): void
  {
    // Arrange
    $psrLogger = $this->createMock(type: LoggerInterface::class);
    $psrLogger->expects(self::once())
      ->method(constraint: 'log')
      ->with(
        arguments: self::callback(
          fn(string $level) => $level === LogLevel::INFO->value
        ),
        second: 'message',
        third: ['context' => 'value']
      );

    $adapter = new LoggerAdapter(logger: $psrLogger);

    // Act
    $adapter->log(
      level: LogLevel::INFO,
      message: 'message',
      context: ['context' => 'value']
    );
  }

  /**
   * Method providePsrShortcuts
   *
   * Provide PSR shortcut methods for testing.
   *
   * @access public
   *
   * @return iterable<string, array{callable, non-empty-string}>
   */
  public static function providePsrShortcuts(): iterable
  {
    yield 'critical' => [static fn(LoggerAdapter $adapter) => $adapter->critical('message'), 'critical'];
    yield 'error'    => [static fn(LoggerAdapter $adapter) => $adapter->error('message'), 'error'];
    yield 'warning'  => [static fn(LoggerAdapter $adapter) => $adapter->warning('message'), 'warning'];
    yield 'notice'   => [static fn(LoggerAdapter $adapter) => $adapter->notice('message'), 'notice'];
    yield 'info'     => [static fn(LoggerAdapter $adapter) => $adapter->info('message'), 'info'];
    yield 'debug'    => [static fn(LoggerAdapter $adapter) => $adapter->debug('message'), 'debug'];
  }

  /**
   * Method testPsrShortcutsDelegateToUnderlyingLogger
   *
   * Ensure that the PSR shortcut methods delegate to
   * the underlying logger with the appropriate method name.
   *
   * @access public
   *
   * @param callable $callable The callable invoking the adapter method.
   * @param non-empty-string $method The expected method name on the PSR logger.
   *
   * @return void No return value
   */
  #[DataProvider('providePsrShortcuts')]
  public function testPsrShortcutsDelegateToUnderlyingLogger(callable $callable, string $method): void
  {
    // Arrange
    $psrLogger = $this->createMock(type: LoggerInterface::class);
    $psrLogger->expects(self::once())
      ->method(constraint: $method)
      ->with('message', []);

    $adapter = new LoggerAdapter(logger: $psrLogger);

    // Act & Assert
    $callable($adapter);
  }
  //#endregion
}
