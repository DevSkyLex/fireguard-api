<?php

declare(strict_types=1);

namespace Procurement\Presentation\Api\Dto\Output;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

/** Authorized procurement projection; internal costs are absent without financial permission. */
final class SupplierOutput
{
  #[ApiProperty(identifier: true)]
  #[Groups(['procurement:read'])]
  public string $id;

  #[Groups(['procurement:read'])]
  public string $organizationId;

  #[Groups(['procurement:read'])]
  public string $name;

  #[Groups(['procurement:read'])]
  public ?string $code;

  #[Groups(['procurement:read'])]
  public ?string $email;

  #[Groups(['procurement:read'])]
  public ?string $phone;

  /**
   * @var list<array<string,mixed>>|list<string>
   */
  #[Groups(['procurement:read'])]
  public array $contacts;

  #[Groups(['procurement:read'])]
  public ?string $archivedAt;

  #[Groups(['procurement:read'])]
  public string $createdAt;

  #[Groups(['procurement:read'])]
  public string $updatedAt;

  #[Groups(['procurement:read'])]
  public int $revision;

  #[Groups(['procurement:read'])]
  public bool $replayed = false;

  /**
   * @param array<string,mixed> $data
   */
  public static function fromProjection(array $data, bool $replayed = false): self
  {
    /** @var array{id:string,organizationId:string,name:string,code:string|null,email:string|null,phone:string|null,contacts:list<array<string,mixed>>|list<string>,archivedAt:string|null,createdAt:string,updatedAt:string,revision:int} $data */
    $output = new self();
    $output->id = $data['id'];
    $output->organizationId = $data['organizationId'];
    $output->name = $data['name'];
    $output->code = $data['code'];
    $output->email = $data['email'];
    $output->phone = $data['phone'];
    $output->contacts = $data['contacts'];
    $output->archivedAt = $data['archivedAt'];
    $output->createdAt = $data['createdAt'];
    $output->updatedAt = $data['updatedAt'];
    $output->revision = $data['revision'];
    $output->replayed = $replayed;

    return $output;
  }
}
