<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Input\Organization;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\OrganizationSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO UpdateOrganizationNotificationsInput.
 *
 * Partial organization notification policy payload. Every flag is optional; only
 * the provided flags are applied on top of the current settings.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateOrganizationNotificationsInput
{
  // #region Properties
  /**
   * Property emailEnabled.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether notifications are delivered by email', required: false)]
  public ?bool $emailEnabled = null;

  /**
   * Property inAppEnabled.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Whether notifications are delivered in-app', required: false)]
  public ?bool $inAppEnabled = null;

  /**
   * Property interventionPublished.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when a field intervention is published', required: false)]
  public ?bool $interventionPublished = null;

  /**
   * Property interventionAssigned.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when a field intervention is assigned', required: false)]
  public ?bool $interventionAssigned = null;

  /**
   * Property inspectionDue.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when an inspection is due', required: false)]
  public ?bool $inspectionDue = null;

  /**
   * Property nonConformityOpened.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when an equipment non-conformity is opened', required: false)]
  public ?bool $nonConformityOpened = null;

  /**
   * Property nonConformitySlaBreached.
   *
   * @since 1.1.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when a non-conformity breaches its resolution SLA', required: false)]
  public ?bool $nonConformitySlaBreached = null;

  /**
   * Property memberInvited.
   *
   * @since 1.0.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Notify when a member is invited', required: false)]
  public ?bool $memberInvited = null;

  /**
   * Property weeklyDigest.
   *
   * @since 1.2.0
   */
  #[Assert\Type('bool')]
  #[Groups([OrganizationSerializationGroup::WRITE])]
  #[ApiProperty(description: 'Send the weekly operational digest email to organization administrators', required: false)]
  public ?bool $weeklyDigest = null;
  // #endregion
}
