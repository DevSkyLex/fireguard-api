<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Dto;

use Shared\Domain\ValueObject\GrantType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO ClientInput
 * @final
 *
 * DTO for Client Input (Create/Update).
 *
 * @category DTO
 * @package Client\Presentation\Api\Dto
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ClientInput
{
  //#region Properties
  /**
   * Property name
   *
   * The client name.
   *
   * @var string|null
   */
  #[Assert\NotBlank]
  #[Assert\Length(min: 3, max: 100)]
  public ?string $name = null;

  /**
   * Property redirectUris
   *
   * The allowed redirect URIs.
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Url(protocols: ['http', 'https'])
  ])]
  public array $redirectUris = [];

  /**
   * Property grantTypes
   *
   * The allowed grant types.
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Choice(choices: GrantType::VALUES)
  ])]
  public array $grantTypes = [];

  /**
   * Property scopes
   *
   * The allowed scopes.
   *
   * @var list<string>
   */
  #[Assert\NotBlank]
  #[Assert\All([
    new Assert\NotBlank,
    new Assert\Type('string')
  ])]
  public array $scopes = [];
  //#endregion
}
