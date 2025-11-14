<?php

namespace Shared\Application\Log;

/**
 * Enum LogLevel
 *
 * Enum used to define the
 * log levels.
 *
 * @category Log
 * @package Shared\Application\Log
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum LogLevel: string
{
  //#region Cases
  /**
   * Case ALERT
   *
   * Alert level.
   *
   * @since 1.0.0
   *
   * @var string The alert level.
   */
  case ALERT = 'alert';

  /**
   * Case CRITICAL
   *
   * Critical level.
   *
   * @since 1.0.0
   *
   * @var string The critical level.
   */
  case CRITICAL = 'critical';

  /**
   * Case ERROR
   *
   * Error level.
   *
   * @since 1.0.0
   *
   * @var string The error level.
   */
  case ERROR = 'error';

  /**
   * Case WARNING
   *
   * Warning level.
   *
   * @since 1.0.0
   *
   * @var string The warning level.
   */
  case WARNING = 'warning';

  /**
   * Case NOTICE
   *
   * Notice level.
   *
   * @since 1.0.0
   *
   * @var string The notice level.
   */
  case NOTICE = 'notice';

  /**
   * Case INFO
   *
   * Info level.
   *
   * @since 1.0.0
   *
   * @var string The info level.
   */
  case INFO = 'info';

  /**
   * Case DEBUG
   *
   * Debug level.
   *
   * @since 1.0.0
   *
   * @var string The debug level.
   */
  case DEBUG = 'debug';
  //#endregion
}
