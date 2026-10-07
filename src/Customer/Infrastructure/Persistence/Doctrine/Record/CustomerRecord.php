<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Persistence\Doctrine\Record;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Class CustomerRecord. Main-database persistence of organization-owned customers. @category Record */
#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(name: 'idx_customer_organization', columns: ['organization_id'])]
#[ORM\UniqueConstraint(name: 'uniq_customer_organization_code', columns: ['organization_id', 'code'])]
class CustomerRecord
{
  /**
   * Property id.
   */
  #[ORM\Id]
  #[ORM\Column(type: 'string', length: 36)]
  public string $id;

  /**
   * Property organizationId.
   */
  #[ORM\Column(name: 'organization_id', type: 'string', length: 36)]
  public string $organizationId;

  /**
   * Property name.
   */
  #[ORM\Column(type: 'string', length: 160)]
  public string $name;

  /**
   * Property code.
   */
  #[ORM\Column(type: 'string', length: 80, nullable: true)]
  public ?string $code = null;

  /**
   * Property email.
   */
  #[ORM\Column(type: 'string', length: 254, nullable: true)]
  public ?string $email = null;

  /**
   * Property phone.
   */
  #[ORM\Column(type: 'string', length: 40, nullable: true)]
  public ?string $phone = null;

  /**
   * @var list<array{name:string,email:?string,phone:?string,role:?string}>
   */
  #[ORM\Column(type: 'json')]
  public array $contacts = [];

  /**
   * Property archivedAt.
   */
  #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
  public ?DateTimeImmutable $archivedAt = null;

  /**
   * Property createdAt.
   */
  #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
  public DateTimeImmutable $createdAt;

  /**
   * Property updatedAt.
   */
  #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
  public DateTimeImmutable $updatedAt;

  /**
   * Property revision.
   */
  #[ORM\Column(type: 'integer', options: ['default' => 1])]
  public int $revision = 1;
}
