<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\AcceptOrganizationInvitation\{AcceptOrganizationInvitationCommand, AcceptOrganizationInvitationResult};
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationQuotaExceededException};
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use Organization\Presentation\Api\Dto\Input\Organization\AcceptOrganizationInvitationInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Processor\Organization\AcceptOrganizationInvitationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(AcceptOrganizationInvitationProcessor::class)]
final class AcceptOrganizationInvitationProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsWrappedQuotaExceededToHttp409(): void
  {
    $processor = new AcceptOrganizationInvitationProcessor(
      commandBus: $this->throwingCommandBus(
        OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::MEMBERS, 5),
      ),
      security: $this->securityWithUser(),
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsWrappedInvitationNotFoundToHttp404(): void
  {
    $processor = new AcceptOrganizationInvitationProcessor(
      commandBus: $this->throwingCommandBus(OrganizationInvitationNotFoundException::withToken()),
      security: $this->securityWithUser(),
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process($this->input(), new Post());
  }

  #[Test]
  public function testProcessMapsResultToOutput(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willReturn(new AcceptOrganizationInvitationResult(
        invitationId: '550e8400-e29b-41d4-a716-4466554460a0',
        memberId: '550e8400-e29b-41d4-a716-4466554460a1',
        organizationId: '550e8400-e29b-41d4-a716-4466554460a2',
        userId: '550e8400-e29b-41d4-a716-4466554460a3',
        roleIds: ['550e8400-e29b-41d4-a716-4466554460a4'],
        isActive: true,
        joinedAt: new DateTimeImmutable('-1 hour'),
      ));

    $processor = new AcceptOrganizationInvitationProcessor(
      commandBus: $commandBus,
      security: $this->securityWithUser(),
    );

    $output = $processor->process($this->input(), new Post());

    self::assertInstanceOf(OrganizationMemberOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-4466554460a1', $output->id);
    self::assertTrue($output->isActive);
  }

  private function input(): AcceptOrganizationInvitationInput
  {
    $input = new AcceptOrganizationInvitationInput();
    $input->token = 'plain-token';

    return $input;
  }

  private function throwingCommandBus(Throwable $domainException): CommandBusPort
  {
    $handlerFailure = new HandlerFailedException(
      new Envelope(new AcceptOrganizationInvitationCommand(
        token: 'plain-token',
        userId: '550e8400-e29b-41d4-a716-4466554460b0',
        userEmail: 'member@example.com',
      )),
      [$domainException],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    return $commandBus;
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser(
      id: '550e8400-e29b-41d4-a716-4466554460b0',
      email: 'member@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    ));

    return $security;
  }
}
