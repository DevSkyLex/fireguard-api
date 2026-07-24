<?php

declare(strict_types=1);

namespace Billing\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Billing\Presentation\Api\Serialization\BillingSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO PortalSessionOutput.
 *
 * Hosted Billing Portal URL the client must redirect the user to.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PortalSessionOutput
{
  // #region Properties
  /**
   * Property organizationId.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false, identifier: true)]
  public string $organizationId = '';

  /**
   * Property url.
   *
   * @since 1.0.0
   */
  #[Groups([BillingSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $url = '';
  // #endregion
}
