<?php

declare(strict_types=1);

namespace User\Domain\ValueObject;

/**
 * Enum Locale.
 *
 * Display language preferred by a user for the application interface. The
 * concrete language cases mirror the locales shipped by the frontend build-time
 * i18n pipeline. The {@see self::SYSTEM} case is not a real language: it is the
 * explicit "follow the browser/Accept-Language" preference, modelled as a value
 * (rather than null) so it survives a JSON Merge Patch on the profile without an
 * absent-versus-null ambiguity.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum Locale: string
{
  // #region Cases
  /**
   * Case SYSTEM.
   *
   * Follow the browser language (no fixed display language). This is the
   * default for every user until they explicitly pick one.
   *
   * @since 1.0.0
   */
  case SYSTEM = 'system';

  /**
   * Case EN.
   *
   * English (source locale).
   *
   * @since 1.0.0
   */
  case EN = 'en';

  /**
   * Case FR.
   *
   * French.
   *
   * @since 1.0.0
   */
  case FR = 'fr';

  /**
   * Case ES.
   *
   * Spanish.
   *
   * @since 1.0.0
   */
  case ES = 'es';
  // #endregion

  // #region Constants
  /**
   * Constant VALUES.
   *
   * Array of all supported locale values.
   *
   * @since 1.0.0
   *
   * @var list<string>
   */
  public const array VALUES = [
    'system',
    'en',
    'fr',
    'es',
  ];
  // #endregion

  // #region Methods
  /**
   * Method label.
   *
   * Returns the language endonym (its own name), or a generic label for the
   * {@see self::SYSTEM} preference.
   *
   * @since 1.0.0
   *
   * @return string the human-readable label
   */
  public function label(): string
  {
    return match ($this) {
      self::SYSTEM => 'System',
      self::EN => 'English',
      self::FR => 'Français',
      self::ES => 'Español',
    };
  }
  // #endregion
}
