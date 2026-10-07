<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Dto\Output;

use Customer\Application\Contract\CustomerView;
use Symfony\Component\Serializer\Attribute\Groups;

use const DATE_ATOM;

/** Class CustomerOutput. Scalar customer read contract. @category Output */
final class CustomerOutput
{
  #[Groups(['customer:read'])]
  public string $id = '';

  #[Groups(['customer:read'])]
  public string $organizationId = '';

  #[Groups(['customer:read'])]
  public string $name = '';

  #[Groups(['customer:read'])]
  public ?string $code = null;

  #[Groups(['customer:read'])]
  public ?string $email = null;

  #[Groups(['customer:read'])]
  public ?string $phone = null;

  /**
   * @var list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  #[Groups(['customer:read'])]
  public array $contacts = [];

  #[Groups(['customer:read'])]
  public ?string $archivedAt = null;

  #[Groups(['customer:read'])]
  public string $createdAt = '';

  #[Groups(['customer:read'])]
  public string $updatedAt = '';

  #[Groups(['customer:read'])]
  public int $revision = 1;

  public static function fromView(CustomerView $view): self
  {
    $output = new self();
    $output->id = $view->id;
    $output->organizationId = $view->organizationId;
    $output->name = $view->name;
    $output->code = $view->code;
    $output->email = $view->email;
    $output->phone = $view->phone;
    $output->contacts = $view->contacts;
    $output->archivedAt = $view->archivedAt?->format(DATE_ATOM);
    $output->createdAt = $view->createdAt->format(DATE_ATOM);
    $output->updatedAt = $view->updatedAt->format(DATE_ATOM);
    $output->revision = $view->revision;

    return $output;
  }
}
