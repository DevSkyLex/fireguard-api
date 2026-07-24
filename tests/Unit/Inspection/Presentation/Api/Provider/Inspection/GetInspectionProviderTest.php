<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Inspection;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Inspection\GetInspection\{GetInspectionQuery, GetInspectionResult};
use Inspection\Domain\Exception\InspectionNotFoundException;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Factory\InspectionOutputFactory;
use Inspection\Presentation\Api\Provider\Inspection\GetInspectionProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(GetInspectionProvider::class)]
final class GetInspectionProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetInspectionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProvideThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $provider = new GetInspectionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID],
    );
  }

  #[Test]
  public function testProvideThrowsWhenPermissionDenied(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $provider = new GetInspectionProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProvideReturnsInspectionOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetInspectionQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && self::INSP_ID === $query->inspectionId;
      }))
      ->willReturn(new GetInspectionResult(
        inspectionId: self::INSP_ID,
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        facilityId: null,
        result: 'pass',
        status: 'draft',
        performedAt: '2026-01-15',
        inspectorType: 'user',
        inspectorName: 'John Doe',
        inspectorUserId: self::USER_ID,
        inspectorOrganizationName: null,
        checklistId: null,
        notes: 'All good',
        signature: null,
        nonConformitiesCount: 0,
        createdAt: $now,
        updatedAt: $now,
      ));

    $provider = new GetInspectionProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );

    $output = $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );

    self::assertInstanceOf(InspectionOutput::class, $output);
    self::assertSame(self::INSP_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame(self::EQUIP_ID, $output->equipmentId);
    self::assertSame('pass', $output->result);
    self::assertSame('draft', $output->status);
    self::assertSame('John Doe', $output->inspector?->displayName);
    self::assertSame('All good', $output->notes);
    self::assertSame(0, $output->nonConformitiesCount);
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenInspectionMissing(): void
  {
    $provider = $this->makeAuthorizedProvider(
      queryException: InspectionNotFoundException::withId(self::INSP_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProvideUnwrapsNotFoundFromMessengerException(): void
  {
    $provider = $this->makeAuthorizedProvider(
      queryException: MessengerRuntimeException::wrap(
        InspectionNotFoundException::withId(self::INSP_ID),
      ),
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  private function makeAuthorizedProvider(Throwable $queryException): GetInspectionProvider
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($queryException);

    return new GetInspectionProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
      outputMapper: $this->createOutputMapper(),
    );
  }

  private function createOutputMapper(): InspectionOutputFactory
  {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(new RuntimeException('User lookup unavailable.'));

    return new InspectionOutputFactory($queryBus);
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
