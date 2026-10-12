<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function array_diff_key;
use function array_flip;
use function array_key_exists;
use function is_array;

/**
 * ValueObject OrganizationSettings.
 *
 * Root container for an organization's structured preferences. It is persisted
 * as a single JSON blob and groups the per-concern sub-sections (notifications,
 * regional). New sections are added as further nested value objects.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationSettings
{
  // #region Constants
  /**
   * Schema version written alongside the blob. Ignored on read today; reserved
   * as the anchor for a future upcasting chain should a breaking rename ever
   * become necessary (additions never need it: `fromArray` applies defaults).
   */
  public const int SCHEMA_VERSION = 1;
  // #endregion

  // #region Properties
  /**
   * Notification policy sub-section.
   *
   * @since 1.0.0
   */
  public OrganizationNotificationSettings $notifications;

  /**
   * Regional and formatting sub-section.
   *
   * @since 1.0.0
   */
  public OrganizationRegionalSettings $regional;

  /**
   * Compliance policy sub-section (SLAs, inspection periodicities, reminders).
   *
   * @since 1.1.0
   */
  public OrganizationComplianceSettings $compliance;

  /**
   * Automation toggles sub-section.
   *
   * @since 1.1.0
   */
  public OrganizationAutomationSettings $automation;

  /**
   * Four-eyes approval policy sub-section (gated action types, minimum
   * approver role, self-approval, request TTL).
   *
   * @since 1.2.0
   */
  public OrganizationApprovalSettings $approval;

  /**
   * AI-assistant policy sub-section (availability, model, sampling, whether
   * business context is pre-injected).
   *
   * @since 1.3.0
   */
  public OrganizationAssistantSettings $assistant;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationSettings class.
   *
   * @since 1.0.0
   *
   * @param ?OrganizationNotificationSettings $notifications the notification sub-section
   * @param ?OrganizationRegionalSettings $regional the regional sub-section
   * @param ?OrganizationComplianceSettings $compliance the compliance sub-section
   * @param ?OrganizationAutomationSettings $automation the automation sub-section
   * @param ?OrganizationApprovalSettings $approval the approval sub-section
   * @param ?OrganizationAssistantSettings $assistant the assistant sub-section
   * @param array<string, mixed> $additionalSections historical and owner-registered additional policy sections
   */
  public function __construct(
    ?OrganizationNotificationSettings $notifications = null,
    ?OrganizationRegionalSettings $regional = null,
    ?OrganizationComplianceSettings $compliance = null,
    ?OrganizationAutomationSettings $automation = null,
    ?OrganizationApprovalSettings $approval = null,
    ?OrganizationAssistantSettings $assistant = null,
    public array $additionalSections = [],
  ) {
    $this->notifications = $notifications ?? new OrganizationNotificationSettings();
    $this->regional = $regional ?? new OrganizationRegionalSettings();
    $this->compliance = $compliance ?? new OrganizationComplianceSettings();
    $this->automation = $automation ?? new OrganizationAutomationSettings();
    $this->approval = $approval ?? new OrganizationApprovalSettings();
    $this->assistant = $assistant ?? new OrganizationAssistantSettings();
  }
  // #endregion

  // #region Methods
  /**
   * Method default.
   *
   * @static
   *
   * Returns an organization settings instance with all defaults applied.
   *
   * @since 1.0.0
   *
   * @return self the default settings instance
   */
  public static function default(): self
  {
    return new self();
  }

  /**
   * Method withNotifications.
   *
   * Returns a new instance with the notification sub-section replaced.
   *
   * @since 1.0.0
   *
   * @param OrganizationNotificationSettings $notifications the new notification settings
   *
   * @return self the new instance
   */
  public function withNotifications(OrganizationNotificationSettings $notifications): self
  {
    return new self(
      notifications: $notifications,
      regional: $this->regional,
      compliance: $this->compliance,
      automation: $this->automation,
      approval: $this->approval,
      assistant: $this->assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withRegional.
   *
   * Returns a new instance with the regional sub-section replaced.
   *
   * @since 1.0.0
   *
   * @param OrganizationRegionalSettings $regional the new regional settings
   *
   * @return self the new instance
   */
  public function withRegional(OrganizationRegionalSettings $regional): self
  {
    return new self(
      notifications: $this->notifications,
      regional: $regional,
      compliance: $this->compliance,
      automation: $this->automation,
      approval: $this->approval,
      assistant: $this->assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withCompliance.
   *
   * Returns a new instance with the compliance sub-section replaced.
   *
   * @since 1.1.0
   *
   * @param OrganizationComplianceSettings $compliance the new compliance settings
   *
   * @return self the new instance
   */
  public function withCompliance(OrganizationComplianceSettings $compliance): self
  {
    return new self(
      notifications: $this->notifications,
      regional: $this->regional,
      compliance: $compliance,
      automation: $this->automation,
      approval: $this->approval,
      assistant: $this->assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withAutomation.
   *
   * Returns a new instance with the automation sub-section replaced.
   *
   * @since 1.1.0
   *
   * @param OrganizationAutomationSettings $automation the new automation settings
   *
   * @return self the new instance
   */
  public function withAutomation(OrganizationAutomationSettings $automation): self
  {
    return new self(
      notifications: $this->notifications,
      regional: $this->regional,
      compliance: $this->compliance,
      automation: $automation,
      approval: $this->approval,
      assistant: $this->assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withApproval.
   *
   * Returns a new instance with the approval sub-section replaced.
   *
   * @since 1.2.0
   *
   * @param OrganizationApprovalSettings $approval the new approval settings
   *
   * @return self the new instance
   */
  public function withApproval(OrganizationApprovalSettings $approval): self
  {
    return new self(
      notifications: $this->notifications,
      regional: $this->regional,
      compliance: $this->compliance,
      automation: $this->automation,
      approval: $approval,
      assistant: $this->assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withAssistant.
   *
   * Returns a new instance with the assistant sub-section replaced.
   *
   * @since 1.3.0
   *
   * @param OrganizationAssistantSettings $assistant the new assistant settings
   *
   * @return self the new instance
   */
  public function withAssistant(OrganizationAssistantSettings $assistant): self
  {
    return new self(
      notifications: $this->notifications,
      regional: $this->regional,
      compliance: $this->compliance,
      automation: $this->automation,
      approval: $this->approval,
      assistant: $assistant,
      additionalSections: $this->additionalSections,
    );
  }

  /**
   * Method withPolicySection
   *
   * @param string $section owner-registered section name
   * @param array<string, mixed> $values validated stored section values
   *
   * @return self immutable settings with the owner policy replaced
   */
  public function withPolicySection(string $section, array $values): self
  {
    if (array_key_exists($section, $this->toArray()) && !array_key_exists($section, $this->additionalSections)) {
      throw InvalidValueException::because('Use the typed organization settings capability for this section.');
    }

    return new self(
      notifications: $this->notifications,
      regional: $this->regional,
      compliance: $this->compliance,
      automation: $this->automation,
      approval: $this->approval,
      assistant: $this->assistant,
      additionalSections: [...$this->additionalSections, $section => $values],
    );
  }

  /**
   * Method toArray.
   *
   * Returns the settings as a nested serializable array.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the settings array, including preserved owner policy sections
   */
  public function toArray(): array
  {
    return [
      ...$this->additionalSections,
      'version' => self::SCHEMA_VERSION,
      'notifications' => $this->notifications->toArray(),
      'regional' => $this->regional->toArray(),
      'compliance' => $this->compliance->toArray(),
      'automation' => $this->automation->toArray(),
      'approval' => $this->approval->toArray(),
      'assistant' => $this->assistant->toArray(),
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates settings from a (possibly empty) array, applying defaults for any
   * missing sub-section. Tolerates legacy empty `{}` rows.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $data the settings data
   * @param array<string, array<string, mixed>> $defaults registered section defaults
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data, array $defaults = []): self
  {
    foreach ($defaults as $section => $fields) {
      if ('compliance' !== $section) {
        $data[$section] = [...$fields, ...(is_array($data[$section] ?? null) ? $data[$section] : [])];
      }
    }
    $notifications = $data['notifications'] ?? [];
    $regional = $data['regional'] ?? [];
    $compliance = $data['compliance'] ?? [];
    $automation = $data['automation'] ?? [];
    $approval = $data['approval'] ?? [];
    $assistant = $data['assistant'] ?? [];

    return new self(
      notifications: OrganizationNotificationSettings::fromArray(is_array($notifications) ? $notifications : []),
      regional: OrganizationRegionalSettings::fromArray(is_array($regional) ? $regional : []),
      compliance: OrganizationComplianceSettings::fromArray(is_array($compliance) ? $compliance : [], $defaults['compliance'] ?? []),
      automation: OrganizationAutomationSettings::fromArray(is_array($automation) ? $automation : []),
      approval: OrganizationApprovalSettings::fromArray(is_array($approval) ? $approval : []),
      assistant: OrganizationAssistantSettings::fromArray(is_array($assistant) ? $assistant : []),
      additionalSections: array_diff_key($data, array_flip(['version', 'notifications', 'regional', 'compliance', 'automation', 'approval', 'assistant'])),
    );
  }
  // #endregion
}
