<?php

declare(strict_types=1);

namespace Assistant\Domain\Service;

use Assistant\Domain\Exception\AssistantValidationException;

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function trim;

/**
 * Service AssistantModelPolicy.
 *
 * Enforces the operator/tenant trust boundary around model selection.
 * `OLLAMA_BASE_URL` (the Ollama server address) is OPERATOR deployment
 * configuration, the same trust class as `MAILER_DSN` — no organization
 * setting or request input may ever influence it. `model` is the only
 * tenant-controlled input in this boundary (supplied once, at
 * `StartAssistantThreadHandler` time — see
 * {@see \Assistant\Domain\Model\Thread\AssistantThread::start()}), and it
 * MUST be constrained to this operator-curated allowlist
 * (`OLLAMA_ALLOWED_MODELS`). An unconfigured (empty) allowlist DENIES every
 * tenant-supplied model rather than permitting any: the operator must
 * explicitly opt in before tenants may override the default model.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AssistantModelPolicy
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<string> $allowedModels the operator-curated allowed model identifiers
   */
  public function __construct(
    private array $allowedModels,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method isAllowed.
   *
   * @since 1.0.0
   *
   * @param string $model the candidate model identifier
   *
   * @return bool true when the model is in the configured allowlist
   */
  public function isAllowed(string $model): bool
  {
    return in_array($model, $this->normalizedAllowedModels(), true);
  }

  /**
   * Method assertAllowed.
   *
   * @since 1.0.0
   *
   * @param string $model the candidate model identifier
   *
   * @throws AssistantValidationException when the model is not allowed
   */
  public function assertAllowed(string $model): void
  {
    if (!$this->isAllowed($model)) {
      throw AssistantValidationException::modelNotAllowed($model);
    }
  }

  /**
   * Method normalizedAllowedModels.
   *
   * @since 1.0.0
   *
   * @return list<string> the trimmed, non-blank allowed model identifiers
   */
  private function normalizedAllowedModels(): array
  {
    return array_values(array_filter(
      array_map(static fn (string $model): string => trim($model), $this->allowedModels),
      static fn (string $model): bool => '' !== $model,
    ));
  }
  // #endregion
}
