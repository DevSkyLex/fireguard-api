<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Psr\Log\LoggerInterface;
use Shared\Application\Log\LogLevel;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Adapter LoggerAdapter.
 *
 * @category Outbound Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoggerAdapter implements LoggerPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the PSR logger adapter.
   *
   * @since 1.0.0
   *
   * @param LoggerInterface $logger the underlying PSR logger implementation
   */
  public function __construct(
    private readonly LoggerInterface $logger,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method log
   * {@inheritDoc}
   *
   * Log a message at the specified level.
   *
   * @since 1.0.0
   *
   * @param LogLevel $level the log level
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function log(LogLevel $level, string $message, array $context = []): void
  {
    $this->logger->log(
      level: $level->value,
      message: $message,
      context: $context,
    );
  }

  /**
   * Method critical
   * {@inheritDoc}
   *
   * Log a critical message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function critical(string $message, array $context = []): void
  {
    $this->logger->critical(
      message: $message,
      context: $context,
    );
  }

  /**
   * Method error
   * {@inheritDoc}
   *
   * Log an error message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function error(string $message, array $context = []): void
  {
    $this->logger->error(
      message: $message,
      context: $context,
    );
  }

  /**
   * Method warning
   * {@inheritDoc}
   *
   * Log a warning message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function warning(string $message, array $context = []): void
  {
    $this->logger->warning(
      message: $message,
      context: $context,
    );
  }

  /**
   * Method notice
   * {@inheritDoc}
   *
   * Log a notice message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function notice(string $message, array $context = []): void
  {
    $this->logger->notice(
      message: $message,
      context: $context,
    );
  }

  /**
   * Method info
   * {@inheritDoc}
   *
   * Log an info message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function info(string $message, array $context = []): void
  {
    $this->logger->info(
      message: $message,
      context: $context,
    );
  }

  /**
   * Method debug
   * {@inheritDoc}
   *
   * Log a debug message.
   *
   * @since 1.0.0
   *
   * @param string $message the log message
   * @param array<string, mixed> $context the log context
   *
   * @return void no return value
   */
  public function debug(string $message, array $context = []): void
  {
    $this->logger->debug(
      message: $message,
      context: $context,
    );
  }
  // #endregion
}
