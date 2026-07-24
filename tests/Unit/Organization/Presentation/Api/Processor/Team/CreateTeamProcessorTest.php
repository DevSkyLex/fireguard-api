<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Team;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Command\Team\CreateTeam\{CreateTeamCommand, CreateTeamResult};
use Organization\Domain\Exception\TeamNameAlreadyExistsException;
use Organization\Presentation\Api\Dto\Input\Team\CreateTeamInput;
use Organization\Presentation\Api\Dto\Output\Team\TeamOutput;
use Organization\Presentation\Api\Processor\Team\CreateTeamProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException};

#[CoversClass(CreateTeamProcessor::class)]
final class CreateTeamProcessorTest extends TestCase
{
  private const string USER_ID = '550e8400-e29b-41d4-a716-446655441300';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441310';

  #[Test]
  public function testProcessThrowsWhenPermissionIsMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.teams.write')
      ->willReturn(false);

    $processor = new CreateTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $input = new CreateTeamInput();
    $input->name = 'Field crew A';

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessDispatchesCommandAndMapsTeamOutput(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (CreateTeamCommand $command): bool => self::ORGANIZATION_ID === $command->organizationId
        && 'Field crew A' === $command->name
        && 'Rooftop crew' === $command->description))
      ->willReturn(new CreateTeamResult(
        id: '550e8400-e29b-41d4-a716-446655441311',
        organizationId: self::ORGANIZATION_ID,
        name: 'Field crew A',
        description: 'Rooftop crew',
        createdAt: $createdAt,
        updatedAt: $createdAt,
      ));

    $processor = new CreateTeamProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $input = new CreateTeamInput();
    $input->name = 'Field crew A';
    $input->description = 'Rooftop crew';

    $output = $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);

    self::assertInstanceOf(TeamOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441311', $output->id);
    self::assertSame(self::ORGANIZATION_ID, $output->organizationId);
    self::assertSame('Field crew A', $output->name);
    self::assertSame('Rooftop crew', $output->description);
    self::assertSame(0, $output->memberCount);
    self::assertSame($createdAt->format('c'), $output->createdAt);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenOrganizationIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new CreateTeamProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(new CreateTeamInput(), new Post(), []);
  }

  #[Test]
  public function testProcessThrowsConflictWhenTeamNameAlreadyExists(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(TeamNameAlreadyExistsException::withName('Field crew A'));

    $processor = new CreateTeamProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $input = new CreateTeamInput();
    $input->name = 'Field crew A';

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post(), ['organizationId' => self::ORGANIZATION_ID]);
  }

  private function securityUser(): SecurityUser
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
