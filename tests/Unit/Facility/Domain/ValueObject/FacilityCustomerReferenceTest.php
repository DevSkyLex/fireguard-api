<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\Exception\FacilityCustomerAssignmentException;
use Facility\Domain\ValueObject\{FacilityCustomerReference, FacilityType};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/** Class FacilityCustomerReferenceTest. Customer scope stays on root sites. @category Test */
final class FacilityCustomerReferenceTest extends TestCase
{
  private const string ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function allowsUnassignedFacilitiesAndAssignedRootSite(): void
  {
    $organizationId = \Facility\Domain\ValueObject\FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440002');
    $site = \Facility\Domain\Model\Facility\Facility::create(
      \Facility\Domain\ValueObject\FacilityId::fromString(self::ID),
      $organizationId,
      FacilityType::SITE,
      new \Facility\Domain\ValueObject\FacilityName('Root Site'),
      new \Facility\Domain\Model\Facility\FacilityDetails(customerId: self::ID),
    );
    self::assertSame(self::ID, $site->customerId());
    $building = \Facility\Domain\Model\Facility\Facility::create(
      \Facility\Domain\ValueObject\FacilityId::fromString('550e8400-e29b-41d4-a716-446655440003'),
      $organizationId,
      FacilityType::BUILDING,
      new \Facility\Domain\ValueObject\FacilityName('Building'),
      new \Facility\Domain\Model\Facility\FacilityDetails(parentFacilityId: $site->id()),
    );
    self::assertNull($building->customerId());
  }

  #[Test]
  public function refusesCustomerOnBuilding(): void
  {
    $this->expectException(FacilityCustomerAssignmentException::class);
    FacilityCustomerReference::assertValid(self::ID, FacilityType::BUILDING, null);
  }

  #[Test]
  public function refusesCustomerOnNestedSite(): void
  {
    $this->expectException(FacilityCustomerAssignmentException::class);
    FacilityCustomerReference::assertValid(self::ID, FacilityType::SITE, self::ID);
  }

  /**
   * Method validatesCustomerFormatBeforeRootPlacement
   *
   * @access public
   *
   * @param string $customerId invalid identifier checked before placement
   *
   * @return void
   */
  #[Test]
  #[DataProvider('invalidCustomerIdentifiers')]
  public function validatesCustomerFormatBeforeRootPlacement(string $customerId): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Invalid UUID provided.');
    FacilityCustomerReference::assertValid($customerId, FacilityType::BUILDING, self::ID);
  }

  /**
   * Method invalidCustomerIdentifiers
   *
   * @access public
   *
   * @return iterable<string,array{string}>
   */
  public static function invalidCustomerIdentifiers(): iterable
  {
    yield 'empty identifier' => [''];
    yield 'malformed identifier' => ['unknown'];
    yield 'unsupported UUID version' => ['550e8400-e29b-81d4-a716-446655440001'];
  }

  /**
   * Method keepsOriginalCustomerIdentifierCase
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function keepsOriginalCustomerIdentifierCase(): void
  {
    $customerId = '550E8400-E29B-41D4-A716-446655440001';
    $site = \Facility\Domain\Model\Facility\Facility::create(
      \Facility\Domain\ValueObject\FacilityId::fromString(self::ID),
      \Facility\Domain\ValueObject\FacilityOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440002'),
      FacilityType::SITE,
      new \Facility\Domain\ValueObject\FacilityName('Root Site'),
      new \Facility\Domain\Model\Facility\FacilityDetails(customerId: $customerId),
    );
    self::assertSame($customerId, $site->customerId());
  }
}
