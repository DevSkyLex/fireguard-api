<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationLastAdminGuardPort};
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMember\RemoveOrganizationMemberCommand;
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Presentation\Api\Dto\Input\Organization\RemoveOrganizationMembersInput;
use Organization\Presentation\Api\Dto\Output\Organization\RemoveOrganizationMembersOutput;
use Organization\Presentation\Api\Processor\Organization\RemoveOrganizationMembersProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

#[CoversClass(RemoveOrganizationMembersProcessor::class)]
final class RemoveOrganizationMembersProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), []);
  }

  #[Test]
  public function testProcessThrowsWhenInputIsNotMemberIdList(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissingAndSkipsGuard(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441600', '550e8400-e29b-41d4-a716-446655441610', 'organization.members.manage')
      ->willReturn(false);

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::never())->method('assertCanRemoveMembers');

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProcessCallsGuardOnceWithBatchIdsThenDispatchesPerMember(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanRemoveMembers')
      ->with(
        '550e8400-e29b-41d4-a716-446655441610',
        ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'],
      );

    $dispatchResult = $this->createStub(ResultMessage::class);
    $dispatchedMemberIds = [];

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(
        static function (RemoveOrganizationMemberCommand $command) use (&$dispatchedMemberIds, $dispatchResult): ResultMessage {
          self::assertSame('550e8400-e29b-41d4-a716-446655441610', $command->organizationId);
          $dispatchedMemberIds[] = $command->memberId;

          return $dispatchResult;
        },
      );

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $output = $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(RemoveOrganizationMembersOutput::class, $output);
    self::assertSame(
      ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'],
      $dispatchedMemberIds,
    );
    self::assertSame(
      ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'],
      $output->removedIds,
    );
    self::assertSame([], $output->failedIds);
  }

  #[Test]
  public function testProcessThrowsConflictWhenGuardRefusesBatchAndDispatchesNothing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationLastAdminGuardPort&MockObject $lastAdminGuard */
    $lastAdminGuard = $this->createMock(OrganizationLastAdminGuardPort::class);
    $lastAdminGuard->expects(self::once())
      ->method('assertCanRemoveMembers')
      ->with(
        '550e8400-e29b-41d4-a716-446655441610',
        ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'],
      )
      ->willThrowException(OrganizationLastAdminException::cannotRemoveLastAdmin());

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveOrganizationMembersProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $lastAdminGuard,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  private function createInput(): RemoveOrganizationMembersInput
  {
    $input = new RemoveOrganizationMembersInput();
    $input->memberIds = ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'];

    return $input;
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
