<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Context;

use function trim;

/**
 * Contract AssistantContextFragment.
 *
 * One provider's contribution to the business-context block injected into
 * the assistant's prompt — a plain DTO, deliberately never a provider
 * module's Domain object (see `AssistantContextProviderPort`'s docblock).
 * `$text` is empty when the provider has nothing to contribute for this
 * scope (no data, or the asking member lacks the underlying read
 * permission) — implementations must never throw instead.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantContextFragment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sourceKey a short, stable identifier for the contributing provider (e.g. `compliance.summary`)
   * @param string $text the rendered text block, or an empty string for no contribution
   */
  private function __construct(
    public string $sourceKey,
    public string $text,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method withText.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $sourceKey a short, stable identifier for the contributing provider
   * @param string $text the rendered text block
   *
   * @return self the fragment
   */
  public static function withText(string $sourceKey, string $text): self
  {
    return new self($sourceKey, $text);
  }

  /**
   * Method empty.
   *
   * @static
   *
   * Builds a "contributed nothing" fragment — the expected return value when
   * a provider has no data, or the asking member lacks the required
   * permission.
   *
   * @since 1.0.0
   *
   * @param string $sourceKey a short, stable identifier for the contributing provider
   *
   * @return self the empty fragment
   */
  public static function empty(string $sourceKey): self
  {
    return new self($sourceKey, '');
  }

  /**
   * Method isEmpty.
   *
   * @since 1.0.0
   *
   * @return bool true when this fragment carries no usable text
   */
  public function isEmpty(): bool
  {
    return '' === trim($this->text);
  }
  // #endregion
}
