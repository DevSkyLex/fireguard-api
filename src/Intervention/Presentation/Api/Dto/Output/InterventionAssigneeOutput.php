<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;

/**
 * DTO InterventionAssigneeOutput.
 *
 * Read-only identity of a work item assignee, resolved at read time from the
 * assignee member reference so clients can render the avatar and name without
 * loading the full organization member list. Mirrors the inspector identity
 * embedded on inspection outputs.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionAssigneeOutput
{
  /**
   * Property member.
   *
   * The assignee organization member IRI (echoes the work item assignee).
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $member = '';

  /**
   * Property userId.
   *
   * The underlying user identifier, when resolvable.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $userId = null;

  /**
   * Property firstName.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $firstName = null;

  /**
   * Property lastName.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $lastName = null;

  /**
   * Property displayName.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public string $displayName = '';

  /**
   * Property avatarUrl.
   *
   * @since 1.0.0
   */
  #[ApiProperty(readable: true, writable: false)]
  public ?string $avatarUrl = null;
}
