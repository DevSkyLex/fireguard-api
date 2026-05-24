<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\NonConformity;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\NonConformity\GetNonConformity\{GetNonConformityQuery, GetNonConformityResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput;
use Inspection\Presentation\Api\Provider\NonConformity\GetNonConformityProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(GetNonConformityProvider::class)]
final class GetNonConformityProviderTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';

  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440020';

  #[Test]
  public function testProvideThrowsWhenUriVariablesMissing(): void
  {
    $provider = new GetNonConformityProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->makeSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID],
    );
  }

  #[Test]
  public function testProvideReturnsNonConformityOutput(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetNonConformityQuery $query): bool {
        return self::ORG_ID === $query->organizationId
          && self::INSP_ID === $query->inspectionId
          && self::NC_ID === $query->nonConformityId;
      }))
      ->willReturn($this->makeResult());

    $provider = new GetNonConformityProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
    );

    $output = $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID, 'nonConformityId' => self::NC_ID],
    );

    self::assertInstanceOf(NonConformityOutput::class, $output);
    self::assertSame(self::NC_ID, $output->id);
    self::assertSame('open', $output->status);
  }

  #[Test]
  public function testProvideThrowsWhenNotAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new GetNonConformityProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID, 'nonConformityId' => self::NC_ID],
    );
  }

  #[Test]
  public function testProvideThrowsNotFoundWhenMissing(): void
  {
    $provider = $this->makeProvider(NonConformityNotFoundException::withId(self::NC_ID));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID, 'nonConformityId' => self::NC_ID],
    );
  }

  #[Test]
  public function testProvideUnwrapsInspectionNotFoundFromMessengerException(): void
  {
    $provider = $this->makeProvider(MessengerRuntimeException::wrap(InspectionNotFoundException::withId(self::INSP_ID)));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      operation: new Get(),
      uriVariables: ['organizationId' => self::ORG_ID, 'inspectionId' => self::INSP_ID, 'nonConformityId' => self::NC_ID],
    );
  }

  private function makeProvider(Throwable $queryException): GetNonConformityProvider
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException($queryException);

    return new GetNonConformityProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $this->makeSecurity(),
    );
  }

  private function makeResult(): GetNonConformityResult
  {
    $now = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    return new GetNonConformityResult(
      nonConformityId: self::NC_ID,
      inspectionId: self::INSP_ID,
      description: 'Extinguisher seal missing',
      severity: 'high',
      status: 'open',
      dueAt: null,
      resolvedAt: null,
      notes: null,
      createdAt: $now,
      updatedAt: $now,
    );
  }

  private function makeSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
