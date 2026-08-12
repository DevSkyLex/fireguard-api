<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException, OrganizationQuotaExceededException};
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Organization\Presentation\Api\Dto\Input\Organization\InviteOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationInvitationOutput;
use Organization\Presentation\Api\Processor\Organization\InviteOrganizationMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(InviteOrganizationMemberProcessor::class)]
final class InviteOrganizationMemberProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUserLacksPermission(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441930', '550e8400-e29b-41d4-a716-446655441931', 'organization.members.manage')
      ->willReturn(false);

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new InviteOrganizationMemberInput();
    $input->email = 'member@example.com';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930');
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (InviteOrganizationMemberCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441931' === $command->organizationId
          && 'member@example.com' === $command->email
          && '550e8400-e29b-41d4-a716-446655441930' === $command->invitedByUserId
          && ['550e8400-e29b-41d4-a716-446655441932'] === $command->roleIds;
      }))
      ->willReturn(new InviteOrganizationMemberResult(
        invitationId: '550e8400-e29b-41d4-a716-446655441933',
        organizationId: '550e8400-e29b-41d4-a716-446655441931',
        email: 'member@example.com',
        status: 'pending',
        invitedByUserId: '550e8400-e29b-41d4-a716-446655441930',
        expiresAt: new DateTimeImmutable('+7 days'),
        createdAt: new DateTimeImmutable('-1 minute'),
        updatedAt: new DateTimeImmutable('-1 minute'),
        roleIds: ['550e8400-e29b-41d4-a716-446655441932'],
      ));

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new InviteOrganizationMemberInput();
    $input->email = 'member@example.com';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441932'];

    $output = $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);

    self::assertInstanceOf(OrganizationInvitationOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441933', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441931', $output->organizationId);
    self::assertSame('member@example.com', $output->email);
    self::assertSame('pending', $output->status);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441932'], $output->roleIds);
  }

  #[Test]
  public function testProcessThrowsForbiddenWhenInvitingWithRolesActorCannotGrant(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var OrganizationPermissionGrantGuardPort&MockObject $grantGuard */
    $grantGuard = $this->createMock(OrganizationPermissionGrantGuardPort::class);
    $grantGuard->expects(self::once())
      ->method('assertCanAssignRoles')
      ->willThrowException(OrganizationAccessDeniedException::cannotGrantPermission('organization.*'));

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      security: $security,
    );

    $input = new InviteOrganizationMemberInput();
    $input->email = 'member@example.com';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441932'];

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930');
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new InviteOrganizationMemberCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441931',
        email: 'member@example.com',
        invitedByUserId: '550e8400-e29b-41d4-a716-446655441930',
      )),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS, 5)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new InviteOrganizationMemberInput();
    $input->email = 'member@example.com';

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930'));

    $processor = new InviteOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), []);
  }

  #[Test]
  public function testProcessMapsAMissingOrganizationToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus(
      OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441931'),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $processor = $this->processorWithFailingCommandBus(new InvalidArgumentException('The email is malformed.'));

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessMapsAWrappedAccessDeniedToHttp403(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      OrganizationAccessDeniedException::cannotGrantPermission('organization.*'),
    ));

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessMapsAWrappedMissingOrganizationToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441931'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      new InvalidArgumentException('The email is malformed.'),
    ));

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $failure = MessengerRuntimeException::wrap(new RuntimeException('the invitation store is offline'));
    $processor = $this->processorWithFailingCommandBus($failure);

    $this->expectExceptionObject($failure);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441931']);
  }

  private function processorWithFailingCommandBus(Throwable $failure): InviteOrganizationMemberProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441930'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    return new InviteOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );
  }

  /**
   * Wraps a domain failure exactly as the command bus adapter does at runtime.
   */
  private function wrapped(Throwable $domainFailure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new InviteOrganizationMemberCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441931',
        email: 'member@example.com',
        invitedByUserId: '550e8400-e29b-41d4-a716-446655441930',
      )),
      [$domainFailure],
    ));
  }

  private function createInput(): InviteOrganizationMemberInput
  {
    $input = new InviteOrganizationMemberInput();
    $input->email = 'member@example.com';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441932'];

    return $input;
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'owner@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
