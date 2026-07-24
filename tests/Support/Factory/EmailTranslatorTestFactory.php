<?php

declare(strict_types=1);

namespace Tests\Support\Factory;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class EmailTranslatorTestFactory.
 *
 * Factory for a real Symfony translator loaded with the project's actual
 * `emails.*.yaml` catalogs, so unit tests exercise the same translated
 * strings production emails render, instead of a hand-maintained duplicate.
 *
 * @category Test Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailTranslatorTestFactory
{
  // #region Constants
  private const array LOCALES = ['en', 'fr', 'es'];
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a translator with the `emails` domain loaded for every
   * supported locale.
   *
   * @since 1.0.0
   *
   * @return TranslatorInterface the configured translator
   */
  public static function create(): TranslatorInterface
  {
    $translator = new Translator('en');
    $translator->addLoader('yaml', new YamlFileLoader());

    foreach (self::LOCALES as $locale) {
      $translator->addResource(
        'yaml',
        __DIR__ . '/../../../translations/emails.' . $locale . '.yaml',
        $locale,
        'emails',
      );
    }

    return $translator;
  }
  // #endregion
}
