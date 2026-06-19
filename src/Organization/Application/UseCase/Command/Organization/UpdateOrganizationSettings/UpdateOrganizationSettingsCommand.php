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
  ) {
  }
  // #endregion
}
