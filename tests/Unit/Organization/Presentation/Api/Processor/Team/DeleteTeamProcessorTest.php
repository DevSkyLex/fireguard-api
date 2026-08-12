<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\DeleteTeam\{DeleteTeamCommand, DeleteTeamResult};
use Organization\Domain\Exception\{OrganizationNotFoundException, TeamNotFoundException};
use Organization\Presentation\Api\Processor\Team\DeleteTeamProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test DeleteTeamProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteTeamProcessor::class)]
final class DeleteTeamProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';
  // #endregion

  // #region Methods
  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'missing teamId' => [['organizationId' => self::ORGANIZATION_ID]];
    yield 'blank organizationId' => [['organizationId' => '', 'teamId' => self::TEAM_ID]];
  }

  #[Test]
  public function testProcessDeletesTheTeamAndReturnsNull(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (DeleteTeamCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::TEAM_ID === $command->teamId))
      ->willReturn(new DeleteTeamResult(self::TEAM_ID, self::ORGANIZATION_ID));

    self::assertNull($this->createProcessor($commandBus)->process(null, new Delete(), $this->uriVariables()));
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new DeleteTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(null, new Delete(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(null, new Delete(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenThePermissionIsMissing(): void
  {
    $processor = new DeleteTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.teams.manage permission.');

    $processor->process(null, new Delete(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingOrganizationToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      OrganizationNotFoundException::withId(self::ORGANIZATION_ID),
      $this->deleteTeamCommand(),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingTeamToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      TeamNotFoundException::withId(self::TEAM_ID),
      $this->deleteTeamCommand(),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      new InvalidArgumentException('Malformed team id.'),
      $this->deleteTeamCommand(),
    ));

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->createProcessor($commandBus)->process(null, new Delete(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'teamId' => self::TEAM_ID];
  }

  private function createProcessor(?CommandBusPort $commandBus = null): DeleteTeamProcessor
  {
    return new DeleteTeamProcessor(
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $this->securityWithUser(),
    );
  }

  private function authorization(bool $granted): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($granted);

    return $authorization;
  }

  private function securityWithUser(): Security
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

  private function deleteTeamCommand(): DeleteTeamCommand
  {
    return new DeleteTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID);
  }

  private function wrapped(Throwable $domainFailure, object $message): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope($message), [$domainFailure]),
    );
  }
  // #endregion
}
