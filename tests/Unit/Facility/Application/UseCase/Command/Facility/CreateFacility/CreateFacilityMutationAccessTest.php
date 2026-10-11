<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use Facility\Application\Port\Outbound\Internal\{FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler};
use Facility\Domain\Model\Facility\Facility;
use Intervention\Application\Contract\Resource\InterventionResourceAssignment;
use Intervention\Application\Port\Inbound\InterventionResourcesPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationQuotaPort};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort, UuidGeneratorPort};

/** Protects the draft parent lock boundary between preflight and persistence. */
final class CreateFacilityMutationAccessTest extends TestCase
{
  #[Test]
  public function preflightReadsAndTheCommandRechecksWithALockBeforeWriting(): void
  {
    $insideTransaction = false;
    $transactions = $this->createStub(TransactionManagerPort::class);
    $transactions->method('transactional')->willReturnCallback(static function (callable $operation) use (&$insideTransaction): mixed {
      $insideTransaction = true;

      try {
        return $operation();
      } finally {
        $insideTransaction = false;
      }
    });
    $interventions = $this->createMock(InterventionResourcesPort::class);
    $interventions->expects(self::exactly(2))->method('mutationPermission')->willReturnCallback(static function (string $interventionId, string $userId, string $organizationId, bool $forUpdate) use (&$insideTransaction): string {
      self::assertSame($insideTransaction, $forUpdate);

      return 'organization.interventions.plan';
    });
    $interventions->expects(self::once())->method('attach')->willReturnCallback(static function () use (&$insideTransaction): InterventionResourceAssignment {
      self::assertTrue($insideTransaction);

      return new InterventionResourceAssignment('550e8400-e29b-41d4-a716-446655449912', 'draft', 1);
    });
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willReturnCallback(static function (Facility $facility) use (&$insideTransaction): void {
      self::assertTrue($insideTransaction);
    });
    $uuid = $this->createStub(UuidGeneratorPort::class);
    $uuid->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655449910');
    $handler = new CreateFacilityHandler(
      $repository,
      $uuid,
      $this->createStub(OrganizationQuotaPort::class),
      $transactions,
      $this->createStub(EventDispatcherPort::class),
      new FacilityMetadataSchemaGuard($this->createStub(FacilityMetadataFieldRepositoryPort::class)),
      interventions: $interventions,
      authorization: $authorization,
    );

    $result = $handler(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655449911',
      type: 'site',
      name: 'Draft site',
      interventionId: '550e8400-e29b-41d4-a716-446655449912',
      userId: '550e8400-e29b-41d4-a716-446655449913',
    ));

    self::assertSame('550e8400-e29b-41d4-a716-446655449910', $result->facilityId);
    self::assertFalse($insideTransaction);
  }
}
