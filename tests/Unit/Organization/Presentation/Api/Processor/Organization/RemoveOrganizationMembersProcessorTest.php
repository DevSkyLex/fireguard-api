<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\Internal\OrganizationLastAdminGuardPort;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationMembers\{RemoveOrganizationMembersCommand, RemoveOrganizationMembersResult};
use Organization\Domain\Exception\OrganizationLastAdminException;
use Organization\Presentation\Api\Dto\Input\Organization\RemoveOrganizationMembersInput;
use Organization\Presentation\Api\Dto\Output\Organization\RemoveOrganizationMembersOutput;
use Organization\Presentation\Api\Processor\Organization\RemoveOrganizationMembersProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};
use Throwable;

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
  public function testProcessCallsGuardOnceThenDispatchesOneBatchWithItsExactResult(): void
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

    $dispatchResult = new RemoveOrganizationMembersResult(['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'], []);
    $dispatchedMemberIds = [];

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturnCallback(
        static function (RemoveOrganizationMembersCommand $command) use (&$dispatchedMemberIds, $dispatchResult): RemoveOrganizationMembersResult {
          self::assertSame('550e8400-e29b-41d4-a716-446655441610', $command->organizationId);
          $dispatchedMemberIds = $command->memberIds;

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

  #[Test]
  public function testProcessPreservesTheBatchHandlersOrderedMissingTargetResult(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (RemoveOrganizationMembersCommand $command): bool => '550e8400-e29b-41d4-a716-446655441610' === $command->organizationId
        && ['550e8400-e29b-41d4-a716-446655441611', '550e8400-e29b-41d4-a716-446655441612'] === $command->memberIds,
    ))->willReturn(new RemoveOrganizationMembersResult(['550e8400-e29b-41d4-a716-446655441611'], ['550e8400-e29b-41d4-a716-446655441612']));
    $processor = new RemoveOrganizationMembersProcessor($commandBus, $authorization, $this->createStub(OrganizationLastAdminGuardPort::class), $security);

    $output = $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertSame(['550e8400-e29b-41d4-a716-446655441611'], $output->removedIds);
    self::assertSame(
      ['550e8400-e29b-41d4-a716-446655441612'],
      $output->failedIds,
    );
  }

  /**
   * The batch pre-check is advisory and unlocked; each removal re-runs the
   * guard under the advisory lock inside the one owning batch transaction. A refusal
   * aborts that transaction and remains a 409 about the invariant rather than a
   * missing-target result.
   */
  #[Test]
  public function testProcessThrowsConflictWhenAMidBatchRemovalIsRefusedByTheLastAdminGuard(): void
  {
    $processor = $this->processorWithFailingCommandBus(MessengerRuntimeException::wrap(
      OrganizationLastAdminException::cannotRemoveLastAdmin(),
    ));

    $this->expectException(ConflictHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $failure = MessengerRuntimeException::wrap(new RuntimeException('the member store is offline'));
    $processor = $this->processorWithFailingCommandBus($failure);

    $this->expectExceptionObject($failure);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  private function processorWithFailingCommandBus(Throwable $failure): RemoveOrganizationMembersProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    return new RemoveOrganizationMembersProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      lastAdminGuard: $this->createStub(OrganizationLastAdminGuardPort::class),
      security: $security,
    );
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
