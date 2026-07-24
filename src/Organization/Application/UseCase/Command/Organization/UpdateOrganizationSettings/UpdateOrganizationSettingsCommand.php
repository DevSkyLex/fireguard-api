<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase UpdateOrganizationSettingsCommand.
 *
 * Carries the settings to apply to an organization. Every mutable field is
 * nullable: a `null` value means "leave unchanged". Passing an empty description
 * string clears the description. The `notifications` and `regional` arrays carry
 * a partial section payload (snake_case keys); only their non-null entries are
 * applied on top of the current section.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateOrganizationSettingsCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateOrganizationSettingsCommand class.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $name the new organization name, or null to leave unchanged
   * @param ?string $slug the new organization slug, or null to leave unchanged
   * @param ?string $description the new description (empty clears it), or null to leave unchanged
   * @param ?bool $isActive the new active flag, or null to leave unchanged
   * @param ?string $logoUrl the new logo URL set server-side after an upload, or null to leave unchanged
   * @param ?array<string, mixed> $notifications the partial notification settings, or null to leave unchanged
   * @param ?array<string, mixed> $regional the partial regional settings, or null to leave unchanged
   * @param ?array<string, mixed> $compliance the partial compliance settings (map entries set to null revert to the catalog default), or null to leave unchanged
   * @param ?array<string, mixed> $automation the partial automation toggles, or null to leave unchanged
   * @param ?array<string, mixed> $approval the partial approval policy (action-rule entries set to null revert to "disabled"), or null to leave unchanged
   * @param ?array<string, mixed> $assistant the partial AI-assistant policy (a null `model` reverts to the operator default), or null to leave unchanged
   * @param ?string $country the new legal country in ISO 3166-1 alpha-2 (empty clears it), or null to leave unchanged
   * @param ?string $legalType the new legal entity type (empty clears it), or null to leave unchanged
   * @param ?string $legalName the new registered legal name (empty clears it), or null to leave unchanged
   * @param ?string $registrationNumber the new company/registration number (empty clears it), or null to leave unchanged
   * @param ?string $vatNumber the new VAT number (empty clears it), or null to leave unchanged
   */
  public function __construct(
    public string $organizationId,
    public ?string $name = null,
    public ?string $slug = null,
    public ?string $description = null,
    public ?bool $isActive = null,
    public ?string $logoUrl = null,
    public ?array $notifications = null,
    public ?array $regional = null,
    public ?array $compliance = null,
    public ?array $automation = null,
    public ?array $approval = null,
    public ?array $assistant = null,
    public ?string $country = null,
    public ?string $legalType = null,
    public ?string $legalName = null,
    public ?string $registrationNumber = null,
    public ?string $vatNumber = null,
  ) {
  }
  // #endregion
}
