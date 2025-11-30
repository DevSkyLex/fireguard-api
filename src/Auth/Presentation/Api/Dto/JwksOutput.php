<?php

declare(strict_types=1);

namespace Auth\Presentation\Api\Dto;

use Auth\Presentation\Api\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO JwksOutput
 * @final
 *
 * DTO for JSON Web Key Set (JWKS) endpoint response.
 * Contains the public keys used to verify JWT signatures.
 *
 * @category DTO
 * @package Auth\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @see https://datatracker.ietf.org/doc/html/rfc7517
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwksOutput
{
  //#region Properties
  /**
   * Property keys
   *
   * Array of JSON Web Keys.
   *
   * @access public
   * @since 1.0.0
   *
   * @var list<array<string, string>>
   */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $keys = [];
  //#endregion
}
