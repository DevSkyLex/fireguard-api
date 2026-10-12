<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Equipment\Infrastructure\Adapter\Idempotency\{EquipmentHttpIdempotencyOperationContributionAdapter, EquipmentHttpIdempotencyReplayAuthorizationAdapter};
use Facility\Infrastructure\Adapter\Idempotency\{FacilityHttpIdempotencyOperationContributionAdapter, FacilityHttpIdempotencyReplayAuthorizationAdapter};
use Inspection\Infrastructure\Adapter\Idempotency\{InspectionHttpIdempotencyOperationContributionAdapter, InspectionHttpIdempotencyReplayAuthorizationAdapter};
use Intervention\Application\Port\Inbound\FreshInterventionScopePort;
use Organization\Application\Port\Inbound\FreshOrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\{HttpIdempotencyAccount, HttpIdempotencyAuthorizationRequest};
use Shared\Application\Contract\Idempotency\{IdempotencyDeniedException, IdempotencyProof};
use Shared\Application\Port\Outbound\Idempotency\{HttpIdempotencyAccountPort, HttpIdempotencyOrganizationAuthorityPort, HttpIdempotencyPlatformAuthorityPort, HttpIdempotencyReplayAuthorizationPort};
use Shared\Infrastructure\Idempotency\OrdinaryHttpReplayAuthorizer;

use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** A complete receipt cannot bypass a changed owner scope or current read entitlement. @category Unit Test */
final class OperationalOwnerReplayAuthorizationTest extends TestCase
{
  #[DataProvider('deletedResources')]
  public function testDeletedGlobalResourceRequiresSignedOriginalScopeAndCurrentGrants(string $module, string $operation, string $permission): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $adapter = $this->adapter($module, $connection, [$permission]);
    $request = $this->request($module, $operation);
    self::assertNull($adapter->scope($request)->organizationId);
    $facts = $adapter->authorize($request, $this->proof($request, [$permission]));
    self::assertSame('org', $facts['organizationId']);
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($module, $connection, [])->authorize($request, $this->proof($request, [$permission]));
  }

  #[DataProvider('deletedResources')]
  public function testMissingTargetWithoutOriginalProofNeverBecomesAnAuthorizedReplay(string $module, string $operation, string $permission): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(false);
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($module, $connection, [$permission])->authorize($this->request($module, $operation), null);
  }

  #[DataProvider('deletedResources')]
  public function testCurrentResourceTransferDeniesOriginalOrganizationReceipt(string $module, string $operation, string $permission): void
  {
    $organization = 'org';
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::exactly(2))->method('fetchAssociative')->willReturnCallback(static function () use (&$organization): array {
      return ['id' => 'resource', 'organization_id' => $organization, 'intervention_id' => null, 'record_status' => 'published'];
    });
    $adapter = $this->adapter($module, $connection, [$permission]);
    $request = $this->request($module, $operation);
    $proof = $this->proof($request, [$permission]);
    self::assertSame('org', $adapter->authorize($request, $proof)['organizationId']);
    $organization = 'foreign';
    $this->expectException(IdempotencyDeniedException::class);
    $adapter->authorize($request, $proof);
  }

  #[DataProvider('readProjections')]
  public function testOriginalWriteGrantAloneCannotDecodeSavedReadProjection(string $module, string $operation, string $permission): void
  {
    $connection = $this->createStub(Connection::class);
    $connection->method('fetchAssociative')->willReturn(['id' => 'resource', 'organization_id' => 'org', 'intervention_id' => null, 'record_status' => 'published']);
    $request = $this->request($module, $operation);
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter($module, $connection, [$permission])->authorize($request, $this->proof($request, [$permission]));
  }

  public function testCreationReplayResolvesTheCreatedIdentifierRatherThanTheOfflineRouteIdentifier(): void
  {
    $connection = $this->createMock(Connection::class);
    $connection->expects(self::once())->method('fetchAssociative')->with(self::stringContains('FROM equipment'), ['id' => 'created-resource'])->willReturn(['id' => 'created-resource', 'organization_id' => 'foreign', 'intervention_id' => null, 'record_status' => 'published']);
    $request = $this->request('Equipment', 'canonical_equipment_put');
    $proof = $this->proof($request, ['organization.equipment.write'], ['resourceId' => 'created-resource']);
    $this->expectException(IdempotencyDeniedException::class);
    $this->adapter('Equipment', $connection, ['organization.equipment.write'])->authorize($request, $proof);
  }

  /**
   * @return iterable<string, array{string, string, string}>
   */
  public static function deletedResources(): iterable
  {
    yield 'equipment' => ['Equipment', 'canonical_equipment_delete', 'organization.equipment.write'];
    yield 'facility' => ['Facility', 'canonical_facility_delete', 'organization.facilities.write'];
    yield 'inspection' => ['Inspection', 'canonical_inspection_delete', 'organization.inspection.write'];
  }

  /**
   * @return iterable<string, array{string, string, string}>
   */
  public static function readProjections(): iterable
  {
    yield 'facility' => ['Facility', 'canonical_facility_patch', 'organization.facilities.write'];
    yield 'inspection' => ['Inspection', 'canonical_inspection_patch', 'organization.inspection.write'];
  }

  /**
   * @param list<string> $permissions
   */
  private function adapter(string $module, Connection $connection, array $permissions): HttpIdempotencyReplayAuthorizationPort
  {
    $users = $this->createStub(HttpIdempotencyAccountPort::class);
    $users->method('current')->willReturn(new HttpIdempotencyAccount('user', 'tenant', 3, true, true));
    $platform = $this->createStub(HttpIdempotencyPlatformAuthorityPort::class);
    $platform->method('permitsKey')->willReturn(true);
    $organizations = $this->createStub(HttpIdempotencyOrganizationAuthorityPort::class);
    $organizations->method('isMemberOf')->willReturn(true);
    $organizations->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $permission): bool => 'org' === $org && in_array($permission, $permissions, true));
    $ordinary = new OrdinaryHttpReplayAuthorizer($users, $platform, $organizations);
    $authorization = $this->createStub(FreshOrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn(true);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $user, string $org, string $permission): bool => 'org' === $org && in_array($permission, $permissions, true));
    $interventions = $this->createStub(FreshInterventionScopePort::class);

    return match ($module) {
      'Equipment' => new EquipmentHttpIdempotencyReplayAuthorizationAdapter($connection, $ordinary, $authorization, $interventions),
      'Facility' => new FacilityHttpIdempotencyReplayAuthorizationAdapter($connection, $ordinary, $authorization, $interventions),
      default => new InspectionHttpIdempotencyReplayAuthorizationAdapter($connection, $ordinary, $authorization, $interventions),
    };
  }

  private function request(string $module, string $name): HttpIdempotencyAuthorizationRequest
  {
    $definitions = match ($module) {
      'Equipment' => EquipmentHttpIdempotencyOperationContributionAdapter::definitions(),
      'Facility' => FacilityHttpIdempotencyOperationContributionAdapter::definitions(),
      default => InspectionHttpIdempotencyOperationContributionAdapter::definitions(),
    };
    foreach ($definitions as $definition) {
      if ($definition->name === $name) {
        return new HttpIdempotencyAuthorizationRequest('user', 'tenant', $definition, '/api/resources/resource', ['id' => 'resource'], ['method' => 204 === $definition->statusCode ? 'DELETE' : 'PATCH']);
      }
    }
    self::fail('The actual API operation must have an explicit owner declaration.');
  }

  /**
   * @param list<string> $permissions
   * @param array<string, string> $extra
   */
  private function proof(HttpIdempotencyAuthorizationRequest $request, array $permissions, array $extra = []): IdempotencyProof
  {
    return new IdempotencyProof([
      'authorizationVersion' => '1',
      'principalId' => 'user',
      'tenantId' => 'tenant',
      'principalSecurityRevision' => '3',
      'operation' => $request->operation->name,
      'resourceUri' => $request->resourceUri,
      'organizationId' => 'org',
      'platformSecurityKey' => '',
      'authorizationRequirements' => json_encode([['organizationId' => 'org', 'permissions' => $permissions, 'membership' => true]], JSON_THROW_ON_ERROR),
      'platformAuthorizationRequirements' => '[]',
      ...$extra,
    ], new DateTimeImmutable('+1 day'));
  }
}
