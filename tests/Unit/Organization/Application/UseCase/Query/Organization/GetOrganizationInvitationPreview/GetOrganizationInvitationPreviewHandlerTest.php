<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\OrganizationInvitationTokenHasher;
use Organization\Application\UseCase\Query\Organization\GetOrganizationInvitationPreview\{GetOrganizationInvitationPreviewHandler, GetOrganizationInvitationPreviewQuery, GetOrganizationInvitationPreviewResult};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

#[CoversClass(GetOrganizationInvitationPreviewHandler::class)]
final class GetOrganizationInvitationPreviewHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsPreviewForKnownToken(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444400';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444401';
    $email = 'member@example.com';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444402'),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findById')
      ->willReturn(UserTestFactory::createActive($inviterUserId, 'inviter@example.com'));

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertInstanceOf(GetOrganizationInvitationPreviewResult::class, $result);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame('Fireguard HQ', $result->organizationName);
    self::assertSame('m****r@example.com', $result->invitedEmail);
    self::assertSame('pending', $result->status);
    self::assertSame('Test User', $result->inviterDisplayName);
  }

  #[Test]
  public function testInvokeThrowsWhenTokenIsUnknown(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn(null);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new GetOrganizationInvitationPreviewQuery('unknown-token'));
  }

  #[Test]
  public function testInvokeThrowsWhenTokenIsBlank(): void
  {
    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByTokenHash');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new GetOrganizationInvitationPreviewQuery("  \t "));
  }

  #[Test]
  public function testInvokeReportsExpiredStatusForStillPendingInvitationPastExpiry(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655444410';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655444411';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId('550e8400-e29b-41d4-a716-446655444412'),
      organizationId: new OrganizationId($organizationId),
      email: new Email('ab@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('-1 hour'),
      createdAt: new DateTimeImmutable('-8 days'),
      updatedAt: new DateTimeImmutable('-8 days'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findByTokenHash')->willReturn($invitation);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findById')->willReturn(null);

    $handler = new GetOrganizationInvitationPreviewHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      tokenHasher: new OrganizationInvitationTokenHasher(),
    );

    $result = $handler->__invoke(new GetOrganizationInvitationPreviewQuery('raw-token'));

    self::assertSame(OrganizationInvitationStatus::EXPIRED->value, $result->status);
    self::assertSame('', $result->organizationName);
    self::assertNull($result->organizationLogoUrl);
    self::assertSame('', $result->inviterDisplayName);
    // Local parts of two characters or fewer are masked wholesale.
    self::assertSame('a***@example.com', $result->invitedEmail);
  }
}
