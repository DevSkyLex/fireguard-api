<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Facility;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Facility\Application\UseCase\Query\Facility\GetFacilityBuildingModel\{GetFacilityBuildingModelQuery, GetFacilityBuildingModelResult};
use Facility\Presentation\Api\Dto\Output\Facility\FacilityBuildingModelOutput;
use Facility\Presentation\Api\Provider\Facility\FacilityBuildingModelProvider;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test FacilityBuildingModelProviderTest.
 *
 * Exercises `FacilityBuildingModelProvider` in isolation, with the query bus
 * and the authorization port both mocked. Deliberately narrower than the
 * functional suite: it pins the provider's own gate (authentication,
 * URI-variable validation, the organization-scoped access decision) and its
 * Result-to-Output mapping as separable units, so a regression in either is
 * attributed here rather than surfacing as an opaque HTTP status.
 *
 * This test was written while the operation still carried `read: false`,
 * which made API Platform's `ReadProvider` return before ever invoking this
 * class — every functional scenario answered `200` with a `null` body. That
 * metadata has since been removed and `FacilityBuildingModelApiTest` now
 * covers the same paths end to end; this suite is kept for the isolation it
 * gives, not because the HTTP surface is untestable.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityBuildingModelProvider::class)]
final class FacilityBuildingModelProviderTest extends TestCase
{
  private const string ORGANIZATION_ID = '990e8400-e29b-41d4-a716-446655470001';

  private const string FACILITY_ID = '990e8400-e29b-41d4-a716-446655470002';

  private const string USER_ID = '990e8400-e29b-41d4-a716-446655470003';

  #[Test]
  public function testProvideRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new FacilityBuildingModelProvider(
      queryBus: $queryBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideRejectsMissingUriVariables(): void
  {
    $provider = $this->makeProvider(expectAsk: false);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId and facilityId URI parameters are required.');

    $provider->provide(operation: new Get(), uriVariables: ['organizationId' => self::ORGANIZATION_ID, 'facilityId' => '']);
  }

  #[Test]
  public function testProvideMapsOutsideScopeToHttp404WithoutQueryingTheBus(): void
  {
    $provider = $this->makeProvider(decision: OrganizationAccessDecision::OUTSIDE_SCOPE, expectAsk: false);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideRejectsCallerWithoutReadPermissionWithoutQueryingTheBus(): void
  {
    $provider = $this->makeProvider(decision: OrganizationAccessDecision::MISSING_PERMISSION, expectAsk: false);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.read permission.');

    $this->ask($provider);
  }

  #[Test]
  public function testProvideDispatchesTheQueryWithTheResolvedIdentifiersAndMapsTheResultFieldByField(): void
  {
    $user = $this->createSecurityUser();

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), self::ORGANIZATION_ID, 'organization.facilities.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $resultFloors = [
      [
        'facilityId' => 'floor-1',
        'name' => 'Ground Floor',
        'levelIndex' => 0,
        'status' => 'active',
        'plan' => ['attachmentId' => 'plan-1', 'imageWidth' => 1200, 'imageHeight' => 900],
        'outline' => ['source' => 'rooms_bbox', 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4], [0.1, 0.4]]],
        'rooms' => [
          ['facilityId' => 'room-1', 'name' => 'Lobby', 'type' => 'zone', 'status' => 'active', 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]],
        ],
      ],
    ];

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(
        static fn (GetFacilityBuildingModelQuery $query): bool => self::ORGANIZATION_ID === $query->organizationId
          && self::FACILITY_ID === $query->facilityId,
      ))
      ->willReturn(new GetFacilityBuildingModelResult(
        buildingId: self::FACILITY_ID,
        buildingName: 'Test Tower',
        floors: $resultFloors,
      ));

    $provider = new FacilityBuildingModelProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $this->ask($provider);

    self::assertInstanceOf(FacilityBuildingModelOutput::class, $output);
    self::assertSame(self::FACILITY_ID, $output->buildingId);
    self::assertSame('Test Tower', $output->buildingName);
    self::assertSame($resultFloors, $output->floors);
  }

  private function ask(FacilityBuildingModelProvider $provider): FacilityBuildingModelOutput
  {
    $output = $provider->provide(
      operation: new Get(),
      uriVariables: [
        'organizationId' => self::ORGANIZATION_ID,
        'facilityId' => self::FACILITY_ID,
      ],
    );

    self::assertInstanceOf(FacilityBuildingModelOutput::class, $output);

    return $output;
  }

  private function makeProvider(OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED, bool $expectAsk = true): FacilityBuildingModelProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    if ($expectAsk) {
      $queryBus->expects(self::once())->method('ask');
    } else {
      $queryBus->expects(self::never())->method('ask');
    }

    return new FacilityBuildingModelProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
