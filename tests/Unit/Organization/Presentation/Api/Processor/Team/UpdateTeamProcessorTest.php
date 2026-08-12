<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\UpdateTeam\{UpdateTeamCommand, UpdateTeamResult};
use Organization\Application\UseCase\Query\Team\GetTeam\GetTeamResult;
use Organization\Domain\Exception\{TeamNameAlreadyExistsException, TeamNotFoundException};
use Organization\Presentation\Api\Dto\Input\Team\UpdateTeamInput;
use Organization\Presentation\Api\Processor\Team\UpdateTeamProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  ConflictHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

/**
 * Test UpdateTeamProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateTeamProcessor::class)]
final class UpdateTeamProcessorTest extends TestCase
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
    yield 'blank teamId' => [['organizationId' => self::ORGANIZATION_ID, 'teamId' => '']];
    yield 'missing organizationId' => [['teamId' => self::TEAM_ID]];
  }

  #[Test]
  public function testProcessUpdatesTheTeamAndReturnsItsRefreshedState(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (UpdateTeamCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::TEAM_ID === $command->teamId
        && 'Night shift' === $command->name
        && 'Covers 22:00-06:00' === $command->description))
      ->willReturn(new UpdateTeamResult(
        id: self::TEAM_ID,
        organizationId: self::ORGANIZATION_ID,
        name: 'Night shift',
        description: 'Covers 22:00-06:00',
        createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
      ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->teamResult());

    $input = new UpdateTeamInput();
    $input->name = 'Night shift';
    $input->description = 'Covers 22:00-06:00';

    $output = $this->createProcessor($commandBus, $queryBus)->process($input, new Patch(), $this->uriVariables());

    self::assertSame(self::TEAM_ID, $output->id);
    self::assertSame('Night shift', $output->name);
    self::assertSame(4, $output->memberCount);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->createdAt);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new UpdateTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(new UpdateTeamInput(), new Patch(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenThePermissionIsMissing(): void
  {
    $processor = new UpdateTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.teams.write permission.');

    $processor->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsADuplicateNameToHttp409(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      TeamNameAlreadyExistsException::withName('Night shift'),
      $this->updateTeamCommand(),
    ));

    $this->expectException(ConflictHttpException::class);

    $this->createProcessor($commandBus)->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingTeamToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      TeamNotFoundException::withId(self::TEAM_ID),
      $this->updateTeamCommand(),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($this->wrapped(
      new InvalidArgumentException('Name cannot be blank.'),
      $this->updateTeamCommand(),
    ));

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')),
    );

    $this->expectException(MessengerRuntimeException::class);

    $this->createProcessor($commandBus)->process(new UpdateTeamInput(), new Patch(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'teamId' => self::TEAM_ID];
  }

  private function createProcessor(
    ?CommandBusPort $commandBus = null,
    ?QueryBusPort $queryBus = null,
  ): UpdateTeamProcessor {
    if (null === $queryBus) {
      $queryBus = $this->createStub(QueryBusPort::class);
      $queryBus->method('ask')->willReturn($this->teamResult());
    }

    return new UpdateTeamProcessor(
      commandBus: $commandBus ?? $this->createStub(CommandBusPort::class),
      queryBus: $queryBus,
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

  private function teamResult(): GetTeamResult
  {
    return new GetTeamResult(
      id: self::TEAM_ID,
      organizationId: self::ORGANIZATION_ID,
      name: 'Night shift',
      description: 'Covers 22:00-06:00',
      memberCount: 4,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-02T00:00:00+00:00'),
    );
  }

  private function updateTeamCommand(): UpdateTeamCommand
  {
    return new UpdateTeamCommand(organizationId: self::ORGANIZATION_ID, teamId: self::TEAM_ID);
  }

  private function wrapped(Throwable $domainFailure, object $message): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(
      new HandlerFailedException(new Envelope($message), [$domainFailure]),
    );
  }
  // #endregion
}
