<?php

declare(strict_types=1);

namespace Tests\Unit\Customer\Domain\Model\Customer;

use Customer\Domain\Exception\CustomerException;
use Customer\Domain\Model\Customer\Customer;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Class CustomerTest. Domain identity, normalization and retained archival behavior. @category Test */
final class CustomerTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORG = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function normalizesContactDetailsAndPreservesHistoryAcrossArchive(): void
  {
    $now = new DateTimeImmutable('2026-10-06T12:00:00Z');
    $customer = Customer::create(self::ID, self::ORG, '  Building Owner  ', '  C-1  ', ' ', '+33 123', [['name' => ' Pat ', 'email' => 'pat@example.com', 'role' => ' Manager ']], $now);
    self::assertSame('Building Owner', $customer->name);
    self::assertSame('C-1', $customer->code);
    self::assertNull($customer->email);
    self::assertSame([['name' => 'Pat', 'email' => 'pat@example.com', 'phone' => null, 'role' => 'Manager']], $customer->contacts);
    $archived = $customer->archive($now->modify('+1 day'));
    self::assertSame(2, $archived->revision);
    self::assertSame($customer->id, $archived->id);
    self::assertSame($customer->contacts, $archived->contacts);
    self::assertSame($archived, $archived->archive($now->modify('+2 days')));
    $restored = $archived->restore($now->modify('+3 days'));
    self::assertNull($restored->archivedAt);
    self::assertSame(3, $restored->revision);
    self::assertSame($customer->createdAt, $restored->createdAt);
  }

  #[Test]
  public function patchesOmittedFieldsAndExplicitNullDifferently(): void
  {
    $now = new DateTimeImmutable();
    $customer = Customer::create(self::ID, self::ORG, 'Owner', 'C-1', 'owner@example.com', null, [], $now);
    $changed = $customer->change(['code' => null, 'name' => 'New owner'], $now);
    self::assertNull($changed->code);
    self::assertSame('owner@example.com', $changed->email);
    self::assertSame(2, $changed->revision);
  }

  #[Test]
  public function refusesInvalidContactAndNullName(): void
  {
    $this->expectException(CustomerException::class);
    Customer::create(self::ID, self::ORG, 'Owner', null, null, null, [['name' => 'Pat', 'email' => 'invalid']], new DateTimeImmutable());
  }

  #[Test]
  public function refusesNullNameRatherThanTreatingItAsAbsent(): void
  {
    $customer = Customer::create(self::ID, self::ORG, 'Owner', null, null, null, [], new DateTimeImmutable());
    $this->expectException(CustomerException::class);
    $customer->change(['name' => null], new DateTimeImmutable());
  }
}
