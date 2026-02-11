<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Query\Organization\GetOrganization\{GetOrganizationHandler, GetOrganizationQuery, GetOrganizationResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationHandler::class)]
final class GetOrganizationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsMappedOrganizationResult(): void
  {
    $createdAt = new DateTimeImmutable('2025-01-01T00:00:00+00:00');

    $organization = Organization::reconstitute(
      id: new OrganizationId('550e8400-e29b-41d4-a716-446655440700'),
      name: new OrganizationName('Fireguard Rennes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: $createdAt,
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::isInstanceOf(OrganizationId::class))
      ->willReturn($organization);

    $handler = new GetOrganizationHandler($organizationRepository);

    $result = $handler->__invoke(new GetOrganizationQuery('550e8400-e29b-41d4-a716-446655440700'));

    self::assertInstanceOf(GetOrganizationResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-446655440700', $result->id);
    self::assertSame('Fireguard Rennes', $result->name);
    self::assertSame('550e8400-e29b-41d4-a716-446655440001', $result->createdByUserId);
    self::assertTrue($result->isActive);
    self::assertSame($createdAt, $result->createdAt);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new GetOrganizationHandler($organizationRepository);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationQuery('550e8400-e29b-41d4-a716-446655440701'));
  }
}
