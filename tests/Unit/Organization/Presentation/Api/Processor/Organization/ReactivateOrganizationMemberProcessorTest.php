<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\ReactivateOrganizationMember\{ReactivateOrganizationMemberCommand, ReactivateOrganizationMemberResult};
use Organization\Domain\Exception\{
  OrganizationArchivedException,
  OrganizationMemberNotFoundException,
  OrganizationMemberNotInactiveException,
  OrganizationNotFoundException,
  OrganizationQuotaExceededException
};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Processor\Organization\ReactivateOrganizationMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test ReactivateOrganizationMemberProcessorTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ReactivateOrganizationMemberProcessor::class)]
final class ReactivateOrganizationMemberProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655442600';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655442601';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655442602';

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new ReactivateOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenUriVariablesMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $processor = new ReactivateOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.members.manage')
      ->willReturn(false);

    $processor = new ReactivateOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ReactivateOrganizationMemberCommand $command): bool {
        return self::ORG_ID === $command->organizationId
          && self::MEMBER_ID === $command->memberId;
      }))
      ->willReturn(new ReactivateOrganizationMemberResult(
        memberId: self::MEMBER_ID,
        organizationId: self::ORG_ID,
        userId: self::USER_ID,
        roleIds: ['550e8400-e29b-41d4-a716-446655442603'],
        isActive: true,
        joinedAt: new DateTimeImmutable('-10 days'),
      ));

    $processor = new ReactivateOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame(self::MEMBER_ID, $output->id);
    self::assertSame(self::ORG_ID, $output->organizationId);
    self::assertSame(self::USER_ID, $output->userId);
    self::assertTrue($output->isActive);
    self::assertSame(['550e8400-e29b-41d4-a716-446655442603'], $output->roleIds);
  }

  #[Test]
  public function testProcessMapsAWrappedOrganizationNotFoundToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus(OrganizationNotFoundException::withId(self::ORG_ID));

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessMapsAWrappedMemberNotFoundToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus(OrganizationMemberNotFoundException::withId(self::MEMBER_ID));

    $this->expectException(NotFoundHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessMapsAWrappedOrganizationArchivedToHttp409(): void
  {
    $processor = $this->processorWithFailingCommandBus(OrganizationArchivedException::cannotReactivateMember());

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessMapsAWrappedMemberNotInactiveToHttp409(): void
  {
    $processor = $this->processorWithFailingCommandBus(OrganizationMemberNotInactiveException::withId(self::MEMBER_ID));

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  #[Test]
  public function testProcessMapsAWrappedQuotaExceededToHttp409(): void
  {
    $processor = $this->processorWithFailingCommandBus(
      OrganizationQuotaExceededException::forResource(\Organization\Domain\ValueObject\OrganizationQuotaResource::MEMBERS, 5),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => self::ORG_ID, 'memberId' => self::MEMBER_ID]);
  }

  /**
   * @param Throwable $domainFailure the domain exception the command handler
   *                                 would throw — wrapped in
   *                                 MessengerRuntimeException exactly as the
   *                                 real MessengerCommandBusAdapter does, so
   *                                 this test exercises the processor's
   *                                 actual unwrap path rather than a
   *                                 direct-catch that never fires in
   *                                 production
   */
  private function processorWithFailingCommandBus(Throwable $domainFailure): ReactivateOrganizationMemberProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($domainFailure));

    return new ReactivateOrganizationMemberProcessor(
      commandBus: $commandBus,
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
