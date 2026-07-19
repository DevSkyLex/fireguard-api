<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * ValueObject OrganizationAutomationSettings.
 *
 * Organization-wide automation toggles. Each rule the platform can run on the
 * organization's behalf is an explicit, individually documented boolean that
 * defaults to "off": automations must always be opted into.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationAutomationSettings
{
  // #region Properties
  /**
   * Whether recording a critical non-conformity automatically creates a draft
   * corrective intervention pre-filled with the affected resource.
   *
   * @since 1.0.0
   */
  public bool $autoCreateInterventionOnCriticalNc;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationAutomationSettings class.
   *
   * @since 1.0.0
   *
   * @param bool $autoCreateInterventionOnCriticalNc whether critical non-conformities auto-create a corrective intervention
   */
  public function __construct(
    bool $autoCreateInterventionOnCriticalNc = false,
  ) {
    $this->autoCreateInterventionOnCriticalNc = $autoCreateInterventionOnCriticalNc;
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Returns the automation settings as a serializable array.
   *
   * @since 1.0.0
   *
   * @return array<string, bool> the settings array
   */
  public function toArray(): array
  {
    return [
      'auto_create_intervention_on_critical_nc' => $this->autoCreateInterventionOnCriticalNc,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates automation settings from a (possibly partial) array, falling back
   * to the "off" defaults for any missing flag.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    return new self(
      autoCreateInterventionOnCriticalNc: (bool) ($data['auto_create_intervention_on_critical_nc'] ?? false),
    );
  }
  // #endregion
}
