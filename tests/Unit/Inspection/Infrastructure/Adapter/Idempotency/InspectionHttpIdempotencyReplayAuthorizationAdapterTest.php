<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Inspection\Infrastructure\Adapter\Idempotency\{InspectionHttpIdempotencyOperationContributionAdapter, InspectionHttpIdempotencyReplayAuthorizationAdapter};
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyAuthorizationRequest;
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\OrdinaryHttpReplayAuthorizationPort;

use function count;

/**
 * Class InspectionHttpIdempotencyReplayAuthorizationAdapterTest
 *
 * Proves that each live owner lookup and the original intervention evidence retain their denial gates before replay.
 *
 * @category Unit Test
 */
#[CoversClass(InspectionHttpIdempotencyReplayAuthorizationAdapter::class)]
final class InspectionHttpIdempotencyReplayAuthorizationAdapterTest extends TestCase
{
  /**
   * @param array<string, string> $route
   */
  #[DataProvider('targetLookups')]
  public function testReplayResolvesCurrentScopeBeforeOpeningOriginalFacts(string $operation, array $route, string $table, string $identifier): void
  {
    $request = $this->request($operation, $route);
    $proof = $this->proof();
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')
      ->with(self::stringContains($table), ['id' => $identifier])
      ->willReturn(['id' => 'inspection', 'organization_id' => 'org', 'intervention_id' => null, 'record_status' => 'published']);
    $ordinary = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->expects(self::once())->method('authorize')->with($request, $proof, 'org')->willReturn(['organizationId' => 'org']);

    self::assertSame(['organizationId' => 'org'], $this->adapter($connection, $ordinary)->authorize($request, $proof));
  }

  /**
   * @return iterable<string, array{string, array<string, string>, string, string}>
   */
  public static function targetLookups(): iterable
  {
    yield 'checklist' => ['inspection_archive_checklist', ['checklistId' => 'checklist'], 'FROM checklists', 'checklist'];
    yield 'canonical inspection' => ['canonical_inspection_delete', ['id' => 'offline-inspection'], 'FROM inspections', 'offline-inspection'];
    yield 'inspection response' => ['_api_/inspection-responses/{id}_delete', ['id' => 'response'], 'FROM inspection_responses', 'response'];
    yield 'attachment' => ['delete_inspection_attachment', ['id' => 'attachment', 'inspectionId' => 'inspection'], 'FROM inspection_attachments', 'attachment'];
    yield 'finding' => ['inspection_update_non_conformity_status', ['nonConformityId' => 'finding', 'inspectionId' => 'inspection'], 'FROM non_conformities', 'finding'];
  }

  public function testMissingResponseResolvesItsRequestedInspectionFromCanonicalFields(): void
  {
    $request = $this->request('inspection_response_put', ['id' => 'offline-response'], ['body' => ['value' => ['fields' => ['inspection' => '/api/inspections/inspection']]]]);
    $connection = $this->createMock(Connection::class);
    $lookups = [];
    $connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $parameters) use (&$lookups): array|false {
      $lookups[] = [$sql, $parameters];

      return 1 === count($lookups) ? false : ['organization_id' => 'org', 'intervention_id' => null, 'record_status' => 'published'];
    });
    $ordinary = $this->ordinary(['organizationId' => 'org']);

    self::assertSame(['organizationId' => 'org'], $this->adapter($connection, $ordinary)->authorize($request, null));
    self::assertStringContainsString('FROM inspection_responses', $lookups[0][0]);
    self::assertSame(['id' => 'offline-response'], $lookups[0][1]);
    self::assertStringContainsString('FROM inspections', $lookups[1][0]);
    self::assertSame(['id' => 'inspection'], $lookups[1][1]);
  }

  public function testDeletedAttachmentUsesTheVerifiedOriginalParentRoute(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnOnConsecutiveCalls(false, ['id' => 'inspection', 'organization_id' => 'org', 'record_status' => 'published']);
    $request = $this->request('delete_inspection_attachment', ['id' => 'attachment', 'inspectionId' => 'inspection']);

    self::assertSame(['organizationId' => 'org'], $this->adapter($connection, $this->ordinary(['organizationId' => 'org']))->authorize($request, $this->proof()));
  }

  /**
   * @param array<string, string> $route
   */
  #[DataProvider('mismatchedParents')]
  public function testChildFromAnotherParentIsDeniedBeforeCommonReplayAuthorization(string $operation, array $route): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'foreign-inspection', 'organization_id' => 'org']);
    $ordinary = $this->createMock(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->expects(self::never())->method('authorize');

    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection, $ordinary)->authorize($this->request($operation, $route), $this->proof());
  }

  /**
   * @return iterable<string, array{string, array<string, string>}>
   */
  public static function mismatchedParents(): iterable
  {
    yield 'attachment' => ['delete_inspection_attachment', ['id' => 'attachment', 'inspectionId' => 'inspection']];
    yield 'finding' => ['inspection_update_non_conformity_status', ['nonConformityId' => 'finding', 'inspectionId' => 'inspection']];
  }

  public function testReplayCannotMoveToAnotherIntervention(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => 'org', 'intervention_id' => 'changed-intervention']);
    $ordinary = $this->ordinary(['organizationId' => 'org', 'interventionId' => 'original-intervention', 'interventionPermission' => 'organization.interventions.execute']);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->expects(self::never())->method('assertExecutionAccess');

    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection, $ordinary, $interventions)->authorize($this->request('canonical_inspection_delete', ['id' => 'inspection']), $this->proof());
  }

  public function testDeletedInspectionStillRechecksOriginalExecutionAssignment(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $ordinary = $this->ordinary(['organizationId' => 'org', 'interventionId' => 'original-intervention', 'interventionPermission' => 'organization.interventions.execute']);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->expects(self::once())->method('assertExecutionAccess')->with('org', 'original-intervention', 'user')->willThrowException(new IdempotencyDeniedException('access_denied'));

    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection, $ordinary, $interventions)->authorize($this->request('canonical_inspection_delete', ['id' => 'inspection']), $this->proof());
  }

  public function testFirstExecutionUsesTheCurrentDraftInterventionPermission(): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['organization_id' => 'org', 'intervention_id' => 'intervention', 'record_status' => 'draft']);
    $interventions = $this->createMock(FreshInterventionScopePort::class);
    $interventions->expects(self::once())->method('mutationPermission')->with('intervention', 'user', 'org')->willReturn('organization.interventions.execute');
    $authorization = $this->createMock(FreshOrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->expects(self::once())->method('hasPermission')->with('user', 'org', 'organization.interventions.execute')->willReturn(false);

    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($connection, $this->ordinary(['organizationId' => 'org']), $interventions, $authorization)->authorize($this->request('canonical_inspection_delete', ['id' => 'inspection']), null);
  }

  private function adapter(Connection $connection, OrdinaryHttpReplayAuthorizationPort $ordinary, ?FreshInterventionScopePort $interventions = null, ?FreshOrganizationAuthorizationPort $authorization = null): InspectionHttpIdempotencyReplayAuthorizationAdapter
  {
    if (null === $authorization) {
      $authorization = $this->createStub(FreshOrganizationAuthorizationPort::class);
      $authorization->method('isMemberOf')->willReturn(true);
      $authorization->method('hasPermission')->willReturn(true);
    }

    return new InspectionHttpIdempotencyReplayAuthorizationAdapter($connection, $ordinary, $authorization, $interventions ?? $this->createStub(FreshInterventionScopePort::class));
  }

  /**
   * @param array<string, string> $facts
   */
  private function ordinary(array $facts): OrdinaryHttpReplayAuthorizationPort
  {
    $ordinary = $this->createStub(OrdinaryHttpReplayAuthorizationPort::class);
    $ordinary->method('authorize')->willReturn($facts);

    return $ordinary;
  }

  /**
   * @param array<string, string> $route
   * @param array<string, mixed> $body
   */
  private function request(string $operation, array $route, array $body = []): HttpIdempotencyAuthorizationRequest
  {
    foreach (InspectionHttpIdempotencyOperationContributionAdapter::definitions() as $definition) {
      if ($operation === $definition->name) {
        return new HttpIdempotencyAuthorizationRequest('user', 'tenant', $definition, '/api/inspections', $route, $body);
      }
    }
    self::fail('The requested operation must have an owner declaration.');
  }

  private function proof(): IdempotencyProof
  {
    return new IdempotencyProof([], new DateTimeImmutable('+1 day'));
  }
}
