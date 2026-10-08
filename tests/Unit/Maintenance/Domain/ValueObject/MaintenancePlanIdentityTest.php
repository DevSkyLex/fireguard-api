<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use Maintenance\Domain\ValueObject\MaintenancePlanIdentity;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Class MaintenancePlanIdentityTest
 *
 * Verifies that a plan retains validated plan, organization and equipment identities.
 *
 * @category Tests
 */
#[CoversClass(MaintenancePlanIdentity::class)]
final class MaintenancePlanIdentityTest extends TestCase
{
  // #region Methods
  /**
   * Method testValidIdentifiersAreRetained
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testValidIdentifiersAreRetained(): void
  {
    $identity = new MaintenancePlanIdentity(
      '019bc6de-1234-7123-8123-123456789abc',
      'A1111111-1111-4111-8111-111111111111',
      'b2222222-2222-4222-9222-222222222222',
    );

    self::assertSame('019bc6de-1234-7123-8123-123456789abc', $identity->id);
    self::assertSame('A1111111-1111-4111-8111-111111111111', $identity->organizationId);
    self::assertSame('b2222222-2222-4222-9222-222222222222', $identity->equipmentId);
  }

  /**
   * Method testEveryIdentifierMustBeAValidUuid
   *
   * @access public
   *
   * @param string $id the plan identifier
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidIdentifiers')]
  public function testEveryIdentifierMustBeAValidUuid(string $id, string $organizationId, string $equipmentId): void
  {
    $this->expectException(InvalidValueException::class);

    new MaintenancePlanIdentity($id, $organizationId, $equipmentId);
  }

  /**
   * Method invalidIdentifiers
   *
   * @access public
   *
   * @return iterable<string, array{string, string, string}> invalid identifiers at each ownership boundary
   */
  public static function invalidIdentifiers(): iterable
  {
    $valid = 'a1111111-1111-4111-8111-111111111111';
    foreach (['empty' => '', 'malformed' => 'not-a-uuid', 'nil' => '00000000-0000-0000-0000-000000000000', 'invalid variant' => 'a1111111-1111-4111-7111-111111111111'] as $case => $invalid) {
      yield 'plan ' . $case => [$invalid, $valid, $valid];
      yield 'organization ' . $case => [$valid, $invalid, $valid];
      yield 'equipment ' . $case => [$valid, $valid, $invalid];
    }
  }
  // #endregion
}
