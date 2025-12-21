<?php

namespace Shared\Application\Log;

/**
 * Enum LogLevel.
 *
 * Enum used to define the
 * log levels.
 *
 * @category Log
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum LogLevel: string
{
  // #region Cases
  /**
   * Case ALERT.
   *
   * Alert level.
   *
   * @since 1.0.0
   */
  case ALERT = 'alert';

  /**
   * Case CRITICAL.
   *
   * Critical level.
   *
   * @since 1.0.0
   */
  case CRITICAL = 'critical';

  /**
   * Case ERROR.
   *
   * Error level.
   *
   * @since 1.0.0
   */
  case ERROR = 'error';

  /**
   * Case WARNING.
   *
   * Warning level.
   *
   * @since 1.0.0
   */
  case WARNING = 'warning';

  /**
   * Case NOTICE.
   *
   * Notice level.
   *
   * @since 1.0.0
   */
  case NOTICE = 'notice';

  /**
   * Case INFO.
   *
   * Info level.
   *
   * @since 1.0.0
   */
  case INFO = 'info';

  /**
   * Case DEBUG.
   *
   * Debug level.
   *
   * @since 1.0.0
   */
  case DEBUG = 'debug';
  // #endregion
}
