<?php

declare(strict_types=1);

namespace Customer\Infrastructure\Persistence\Doctrine\Repository;

use Customer\Application\Port\Outbound\CustomerRepositoryPort;
use Customer\Domain\Exception\CustomerException;
use Customer\Domain\Model\Customer\Customer;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Class CustomerRepository. Scoped PostgreSQL persistence and concurrency on main. @category Repository */
final readonly class CustomerRepository implements CustomerRepositoryPort
{
  public function __construct(private EntityManagerInterface $entityManager)
  {
  }

  public function find(string $id, string $organizationId, bool $lock = false): ?Customer
  {
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM customers WHERE id = :id AND organization_id = :organization' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id, 'organization' => $organizationId]);

    return false === $row ? null : $this->map($row);
  }

  public function save(Customer $customer, ?int $expectedRevision = null): void
  {
    $connection = $this->entityManager->getConnection();
    $values = ['organization_id' => $customer->organizationId, 'name' => $customer->name, 'code' => $customer->code, 'email' => $customer->email, 'phone' => $customer->phone, 'contacts' => json_encode($customer->contacts, JSON_THROW_ON_ERROR), 'archived_at' => $customer->archivedAt?->format('Y-m-d H:i:s.u'), 'created_at' => $customer->createdAt->format('Y-m-d H:i:s.u'), 'updated_at' => $customer->updatedAt->format('Y-m-d H:i:s.u'), 'revision' => $customer->revision];

    try {
      if (null === $expectedRevision) {
        $connection->insert('customers', ['id' => $customer->id] + $values);
      } else {
        $count = $connection->update('customers', $values, ['id' => $customer->id, 'organization_id' => $customer->organizationId, 'revision' => $expectedRevision]);
        if (1 !== $count) {
          throw CustomerException::stale();
        }
      }
    } catch (UniqueConstraintViolationException) {
      throw CustomerException::codeConflict();
    }
  }

  /**
   * @return list<Customer>
   */
  public function list(string $organizationId, string $search, bool $archived, int $offset, int $limit): array
  {
    $sql = 'SELECT * FROM customers WHERE ' . $this->criteria($archived) . ' ORDER BY LOWER(name), id LIMIT :limit OFFSET :offset';
    $rows = $this->entityManager->getConnection()->fetchAllAssociative($sql, ['organization' => $organizationId, 'search' => '%' . $search . '%', 'limit' => $limit, 'offset' => $offset], ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER]);

    return array_map($this->map(...), $rows);
  }

  public function count(string $organizationId, string $search, bool $archived): int
  {
    /** @var int|string $total */
    $total = $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM customers WHERE ' . $this->criteria($archived), ['organization' => $organizationId, 'search' => '%' . $search . '%']);

    return (int) $total;
  }

  private function criteria(bool $archived): string
  {
    return 'organization_id = :organization AND archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL AND (name ILIKE :search OR COALESCE(code, \'\') ILIKE :search OR COALESCE(email, \'\') ILIKE :search)';
  }

  /**
   * @param array<string,mixed> $row
   */
  private function map(array $row): Customer
  {
    /** @var array{id:string,organization_id:string,name:string,code:?string,email:?string,phone:?string,contacts:string,archived_at:?string,created_at:string,updated_at:string,revision:int|string} $row */
    /** @var list<array{name:string,email:?string,phone:?string,role:?string}> $contacts */
    $contacts = json_decode((string) $row['contacts'], true, 512, JSON_THROW_ON_ERROR);

    return Customer::reconstitute((string) $row['id'], (string) $row['organization_id'], (string) $row['name'], null === $row['code'] ? null : (string) $row['code'], null === $row['email'] ? null : (string) $row['email'], null === $row['phone'] ? null : (string) $row['phone'], $contacts, null === $row['archived_at'] ? null : new DateTimeImmutable((string) $row['archived_at']), new DateTimeImmutable((string) $row['created_at']), new DateTimeImmutable((string) $row['updated_at']), (int) $row['revision']);
  }
}
