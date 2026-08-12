<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationPermissionGrantGuardPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationQuotaExceededException, OrganizationRoleNotFoundException};
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Organization\Presentation\Api\Dto\Input\Organization\AddOrganizationMemberInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Processor\Organization\AddOrganizationMemberProcessor;
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

#[CoversClass(AddOrganizationMemberProcessor::class)]
final class AddOrganizationMemberProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenUserLacksPermission(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441200', '550e8400-e29b-41d4-a716-446655441210', 'organization.members.manage')
      ->willReturn(false);

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsOutput(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200');
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
      ->with(self::callback(static function (AddOrganizationMemberCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441210' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441201' === $command->userId
          && ['550e8400-e29b-41d4-a716-446655441211'] === $command->roleIds;
      }))
      ->willReturn(new AddOrganizationMemberResult(
        memberId: '550e8400-e29b-41d4-a716-446655441212',
        organizationId: '550e8400-e29b-41d4-a716-446655441210',
        userId: '550e8400-e29b-41d4-a716-446655441201',
        roleIds: ['550e8400-e29b-41d4-a716-446655441211'],
        isActive: true,
        joinedAt: new DateTimeImmutable('-2 days'),
      ));

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441211'];

    $output = $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441212', $output->id);
    self::assertSame('550e8400-e29b-41d4-a716-446655441210', $output->organizationId);
    self::assertSame('550e8400-e29b-41d4-a716-446655441201', $output->userId);
    self::assertSame(['550e8400-e29b-41d4-a716-446655441211'], $output->roleIds);
    self::assertTrue($output->isActive);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdMissingInUri(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new AddOrganizationMemberInput(), new Post(), []);
  }

  #[Test]
  public function testProcessThrowsForbiddenWhenAssigningRolesActorCannotGrant(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

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

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $grantGuard,
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441211'];

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200');
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new AddOrganizationMemberCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441210',
        userId: '550e8400-e29b-41d4-a716-446655441201',
      )),
      [OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS, 5)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AddOrganizationMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      grantGuard: $this->createStub(OrganizationPermissionGrantGuardPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new AddOrganizationMemberInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsAMissingRoleToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus(
      OrganizationRoleNotFoundException::withId('550e8400-e29b-41d4-a716-446655441211'),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $processor = $this->processorWithFailingCommandBus(new InvalidArgumentException('Member is already registered.'));

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsAWrappedAccessDeniedToHttp403(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      OrganizationAccessDeniedException::cannotGrantPermission('organization.*'),
    ));

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsAWrappedMissingRoleToHttp404(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      OrganizationRoleNotFoundException::withId('550e8400-e29b-41d4-a716-446655441211'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $processor = $this->processorWithFailingCommandBus($this->wrapped(
      new InvalidArgumentException('Member is already registered.'),
    ));

    $this->expectException(BadRequestHttpException::class);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $failure = MessengerRuntimeException::wrap(new RuntimeException('the member store is offline'));
    $processor = $this->processorWithFailingCommandBus($failure);

    $this->expectExceptionObject($failure);

    $processor->process($this->createInput(), new Post(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441210']);
  }

  private function processorWithFailingCommandBus(Throwable $failure): AddOrganizationMemberProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441200'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    return new AddOrganizationMemberProcessor(
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
      new Envelope(new AddOrganizationMemberCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441210',
        userId: '550e8400-e29b-41d4-a716-446655441201',
      )),
      [$domainFailure],
    ));
  }

  private function createInput(): AddOrganizationMemberInput
  {
    $input = new AddOrganizationMemberInput();
    $input->userId = '550e8400-e29b-41d4-a716-446655441201';
    $input->roleIds = ['550e8400-e29b-41d4-a716-446655441211'];

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
