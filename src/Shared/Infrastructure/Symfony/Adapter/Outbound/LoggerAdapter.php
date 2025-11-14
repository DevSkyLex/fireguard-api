<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Symfony\Adapter\Outbound;

use Psr\Log\LoggerInterface;
use Shared\Application\Log\LogLevel;
use Shared\Application\Port\Outbound\LoggerPort;

/**
 * Adapter LoggerAdapter
 * @implements LoggerPort
 * @final
 *
 * Adapter bridging the outbound logger port with
 * a PSR-3 compatible logger.
 *
 * @category Outbound Adapter
 * @package Shared\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoggerAdapter implements LoggerPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the PSR logger adapter.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LoggerInterface $logger The underlying PSR logger implementation.
   */
  public function __construct(
    private readonly LoggerInterface $logger
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method log
   * @method log(
   *  LogLevel $level,
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log a message at the specified level.
   *
   * @access public
   * @since 1.0.0
   *
   * @param LogLevel $level The log level.
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function log(LogLevel $level, string $message, array $context = []): void
  {
    $this->logger->log(
      level: $level->value,
      message: $message,
      context: $context
    );
  }

  /**
   * Method critical
   * @method critical(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log a critical message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function critical(string $message, array $context = []): void
  {
    $this->logger->critical(
      message: $message,
      context: $context
    );
  }

  /**
   * Method error
   * @method error(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log an error message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function error(string $message, array $context = []): void
  {
    $this->logger->error(
      message: $message,
      context: $context
    );
  }

  /**
   * Method warning
   * @method warning(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log a warning message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function warning(string $message, array $context = []): void
  {
    $this->logger->warning(
      message: $message,
      context: $context
    );
  }

  /**
   * Method notice
   * @method notice(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log a notice message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function notice(string $message, array $context = []): void
  {
    $this->logger->notice(
      message: $message,
      context: $context
    );
  }

  /**
   * Method info
   * @method info(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log an info message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function info(string $message, array $context = []): void
  {
    $this->logger->info(
      message: $message,
      context: $context
    );
  }

  /**
   * Method debug
   * @method debug(
   *  string $message,
   *  array $context = []
   * ): void
   * {@inheritDoc}
   *
   * Log a debug message.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $message The log message.
   * @param array $context The log context.
   *
   * @return void No return value.
   */
  public function debug(string $message, array $context = []): void
  {
    $this->logger->debug(
      message: $message,
      context: $context
    );
  }
  //#endregion
}
