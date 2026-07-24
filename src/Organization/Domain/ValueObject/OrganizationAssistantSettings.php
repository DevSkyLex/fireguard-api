<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Organization\Domain\Catalog\OrganizationAssistantDefaults;
use Shared\Domain\Exception\InvalidValueException;

use function array_key_exists;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function trim;

/**
 * ValueObject OrganizationAssistantSettings.
 *
 * Organization-wide AI-assistant policy: whether the assistant is available at
 * all, which model answers, how deterministic its sampling is, and whether the
 * server may pre-inject a business-context block alongside the thread
 * transcript. The assistant is DISABLED by default (opt-in) because enabling it
 * sends conversation content to the inference backend — mirrors
 * {@see OrganizationApprovalSettings}.
 *
 * The model name is stored as a plain string and is NOT validated here (the
 * Organization domain never depends on the Assistant module nor on deployment
 * configuration); the API layer validates it against the operator's
 * `OLLAMA_ALLOWED_MODELS` allowlist. Crucially, no field here ever influences
 * the backend URL: that is operator configuration, so a tenant setting can
 * never redirect inference traffic.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAssistantSettings
{
  // #region Properties
  /**
   * Whether the assistant is available to this organization.
   *
   * @since 1.0.0
   */
  public bool $enabled;

  /**
   * Per-organization model override, or `null` to use the operator default.
   *
   * @since 1.0.0
   */
  public ?string $model;

  /**
   * Sampling temperature (validated against the catalog bounds).
   *
   * @since 1.0.0
   */
  public float $temperature;

  /**
   * Whether the server pre-injects a bounded business-context block.
   *
   * @since 1.0.0
   */
  public bool $includeBusinessContext;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param bool $enabled whether the assistant is available
   * @param ?string $model the model override, or null for the operator default
   * @param float $temperature the sampling temperature (validated)
   * @param bool $includeBusinessContext whether business context is pre-injected
   */
  public function __construct(
    bool $enabled = OrganizationAssistantDefaults::ENABLED,
    ?string $model = OrganizationAssistantDefaults::MODEL,
    float $temperature = OrganizationAssistantDefaults::TEMPERATURE,
    bool $includeBusinessContext = OrganizationAssistantDefaults::INCLUDE_BUSINESS_CONTEXT,
  ) {
    $this->enabled = $enabled;
    $this->model = self::normalizeModel($model);
    $this->temperature = self::validateTemperature($temperature);
    $this->includeBusinessContext = $includeBusinessContext;
  }
  // #endregion

  // #region Methods
  /**
   * Method mergedWith.
   *
   * Applies a partial section payload (snake_case keys) on top of the current
   * settings and returns the resulting instance. An explicit `null` `model`
   * reverts to the operator default.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $partial the partial section payload
   *
   * @return self the merged instance
   */
  public function mergedWith(array $partial): self
  {
    $enabled = $this->enabled;
    if (isset($partial['enabled']) && is_bool($partial['enabled'])) {
      $enabled = $partial['enabled'];
    }

    $model = $this->model;
    if (array_key_exists('model', $partial)) {
      $model = is_string($partial['model']) ? $partial['model'] : null;
    }

    $temperature = $this->temperature;
    if (isset($partial['temperature']) && (is_float($partial['temperature']) || is_int($partial['temperature']))) {
      $temperature = (float) $partial['temperature'];
    }

    $includeBusinessContext = $this->includeBusinessContext;
    if (isset($partial['include_business_context']) && is_bool($partial['include_business_context'])) {
      $includeBusinessContext = $partial['include_business_context'];
    }

    return new self(
      enabled: $enabled,
      model: $model,
      temperature: $temperature,
      includeBusinessContext: $includeBusinessContext,
    );
  }

  /**
   * Method toArray.
   *
   * Returns the assistant settings as a serializable array.
   *
   * @since 1.0.0
   *
   * @return array{enabled: bool, model: ?string, temperature: float, include_business_context: bool} the settings array
   */
  public function toArray(): array
  {
    return [
      'enabled' => $this->enabled,
      'model' => $this->model,
      'temperature' => $this->temperature,
      'include_business_context' => $this->includeBusinessContext,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates assistant settings from a (possibly partial) array, falling back to
   * the catalog defaults for any missing or malformed key.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    $model = $data['model'] ?? OrganizationAssistantDefaults::MODEL;
    $temperature = $data['temperature'] ?? OrganizationAssistantDefaults::TEMPERATURE;

    return new self(
      enabled: (bool) ($data['enabled'] ?? OrganizationAssistantDefaults::ENABLED),
      model: is_string($model) ? $model : OrganizationAssistantDefaults::MODEL,
      temperature: is_float($temperature) || is_int($temperature) ? (float) $temperature : OrganizationAssistantDefaults::TEMPERATURE,
      includeBusinessContext: (bool) ($data['include_business_context'] ?? OrganizationAssistantDefaults::INCLUDE_BUSINESS_CONTEXT),
    );
  }

  /**
   * Method normalizeModel.
   *
   * @static
   *
   * Trims the model name and collapses a blank override to `null` so that an
   * empty string can never be sent to the inference backend as a model id.
   *
   * @since 1.0.0
   *
   * @param ?string $model the submitted model override
   *
   * @return ?string the normalized model override
   */
  private static function normalizeModel(?string $model): ?string
  {
    if (null === $model) {
      return null;
    }

    $trimmed = trim($model);

    return '' === $trimmed ? null : $trimmed;
  }

  /**
   * Method validateTemperature.
   *
   * @static
   *
   * Validates the sampling temperature bounds.
   *
   * @since 1.0.0
   *
   * @param float $temperature the sampling temperature
   *
   * @return float the validated sampling temperature
   */
  private static function validateTemperature(float $temperature): float
  {
    if ($temperature < OrganizationAssistantDefaults::MIN_TEMPERATURE || $temperature > OrganizationAssistantDefaults::MAX_TEMPERATURE) {
      throw InvalidValueException::because(sprintf(
        'Assistant temperature must be between %.1f and %.1f.',
        OrganizationAssistantDefaults::MIN_TEMPERATURE,
        OrganizationAssistantDefaults::MAX_TEMPERATURE,
      ));
    }

    return $temperature;
  }
  // #endregion
}
