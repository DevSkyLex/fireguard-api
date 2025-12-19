<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Log\LogLevel;

/**
 * Port LoggerPort.
 *
 * Port used to log data
 * in the application.
 *
 * @category Outbound Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface LoggerPort
{
    // #region Methods
    /**
     * Method log.
     *
     * Log data in the application.
     *
     * @since 1.0.0
     *
     * @param LogLevel             $level   the level of the log
     * @param string               $message the message of the log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function log(LogLevel $level, string $message, array $context = []): void;

    /**
     * Method critical.
     *
     * Log a critical message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Method error.
     *
     * Log an error message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function error(string $message, array $context = []): void;

    /**
     * Method warning.
     *
     * Log a warning message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Method notice.
     *
     * Log a notice message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Method info.
     *
     * Log an info message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function info(string $message, array $context = []): void;

    /**
     * Method debug.
     *
     * Log a debug message.
     *
     * @since 1.0.0
     *
     * @param string               $message the message to log
     * @param array<string, mixed> $context the context of the log
     *
     * @return void no return value
     */
    public function debug(string $message, array $context = []): void;
    // #endregion
}
