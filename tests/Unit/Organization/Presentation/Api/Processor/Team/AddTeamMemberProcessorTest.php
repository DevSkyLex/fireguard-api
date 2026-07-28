<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\AddTeamMember\{
  AddTeamMemberCommand,
  AddTeamMemberResult
};
use Organization\Domain\Exception\{OrganizationMemberNotFoundException, TeamNotFoundException};
use Organization\Presentation\Api\Dto\Input\Team\AddTeamMemberInput;
use Organization\Presentation\Api\Processor\Team\AddTeamMemberProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};

/**
 * Test AddTeamMemberProcessorTest.
 *
 * @category Processor Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddTeamMemberProcessor::class)]
final class AddTeamMemberProcessorTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string TEAM_ID = '550e8400-e29b-41d4-a716-446655440030';

  private const string MEMBER_ID = '550e8400-e29b-41d4-a716-446655440011';

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
  }

  #[Test]
  public function testProcessAddsTheMemberAndReturnsTheMembership(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (AddTeamMemberCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && self::TEAM_ID === $command->teamId
        && self::MEMBER_ID === $command->memberId
        && 'lead' === $command->role))
      ->willReturn(new AddTeamMemberResult(
        teamId: self::TEAM_ID,
        memberId: self::MEMBER_ID,
        role: 'lead',
        addedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      ));

    $input = new AddTeamMemberInput();
    $input->memberId = self::MEMBER_ID;
    $input->role = 'lead';

    $output = $this->createProcessor($commandBus)->process($input, new Post(), $this->uriVariables());

    self::assertSame(self::MEMBER_ID, $output->memberId);
    self::assertSame('lead', $output->role);
    self::assertSame('2026-01-01T00:00:00+00:00', $output->addedAt);
  }

  #[Test]
  public function testProcessThrowsWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AddTeamMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(true),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new AddTeamMemberInput(), new Post(), $this->uriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor()->process(new AddTeamMemberInput(), new Post(), $uriVariables);
  }

  #[Test]
  public function testProcessThrowsWhenThePermissionIsMissing(): void
  {
    $processor = new AddTeamMemberProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->authorization(false),
      security: $this->securityWithUser(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.teams.write permission.');

    $processor->process(new AddTeamMemberInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingTeamToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(TeamNotFoundException::withId(self::TEAM_ID));

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(new AddTeamMemberInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAMissingOrganizationMemberToHttp404(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(
      OrganizationMemberNotFoundException::withId(self::MEMBER_ID),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->createProcessor($commandBus)->process(new AddTeamMemberInput(), new Post(), $this->uriVariables());
  }

  #[Test]
  public function testProcessMapsAnInvalidArgumentToHttp400(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException(new InvalidArgumentException('Unknown role.'));

    $this->expectException(BadRequestHttpException::class);

    $this->createProcessor($commandBus)->process(new AddTeamMemberInput(), new Post(), $this->uriVariables());
  }

  /**
   * @return array<string, string>
   */
  private function uriVariables(): array
  {
    return ['organizationId' => self::ORGANIZATION_ID, 'teamId' => self::TEAM_ID];
  }

  private function createProcessor(?CommandBusPort $commandBus = null): AddTeamMemberProcessor
  {
    return new AddTeamMemberProcessor(
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
  // #endregion
}
