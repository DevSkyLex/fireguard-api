<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Dto\Input;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/** Class CreateCustomerInput. Public customer creation fields. @category Input */
final class CreateCustomerInput
{
  #[Groups(['customer:write'])]
  #[Assert\NotBlank]
  #[Assert\Length(max: 160)]
  public string $name = '';

  #[Groups(['customer:write'])]
  #[Assert\Length(max: 80)]
  public ?string $code = null;

  #[Groups(['customer:write'])]
  #[Assert\Email]
  #[Assert\Length(max: 254)]
  public ?string $email = null;

  #[Groups(['customer:write'])]
  #[Assert\Length(max: 40)]
  public ?string $phone = null;

  /**
   * @var list<array{name:string,email?:?string,phone?:?string,role?:?string}>
   */
  #[Groups(['customer:write'])]
  #[Assert\Count(max: 50)]
  public array $contacts = [];
}
