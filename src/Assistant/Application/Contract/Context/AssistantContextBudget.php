<?php

declare(strict_types=1);

namespace Assistant\Application\Contract\Context;

/**
 * Contract AssistantContextBudget.
 *
 * The character budget still available when a provider is asked to
 * contribute, decreasing as `AssistantContextAssembler` walks earlier
 * (higher-priority) providers. A SOFT hint only: providers are encouraged to
 * keep their own output within `$remainingCharacters`, but the assembler
 * NEVER trusts a provider's self-reported length — the returned text is
 * always hard-truncated to what is actually left (see
 * `AssistantContextAssembler::assemble()`).
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantContextBudget
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $remainingCharacters the character budget still available
   */
  public function __construct(
    public int $remainingCharacters,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isExhausted.
   *
   * @since 1.0.0
   *
   * @return bool true when no budget remains
   */
  public function isExhausted(): bool
  {
    return $this->remainingCharacters <= 0;
  }
  // #endregion
}
