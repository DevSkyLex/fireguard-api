<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\CreateOrganization\{CreateOrganizationCommand, CreateOrganizationResult};
use Organization\Domain\Exception\OrganizationSlugAlreadyExistsException;
use Organization\Presentation\Api\Dto\Input\Organization\CreateOrganizationInput;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Organization\Presentation\Api\Processor\Organization\CreateOrganizationProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, ConflictHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[CoversClass(CreateOrganizationProcessor::class)]
final class CreateOrganizationProcessorTest extends TestCase
{
  #[Test]
  public function testProcessDispatchesCreateOrganizationCommandAndMapsOutput(): void
  {
    $input = new CreateOrganizationInput();
    $input->name = 'Fireguard Toulouse';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441100');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (CreateOrganizationCommand $command) use ($user): bool {
        return 'Fireguard Toulouse' === $command->name
          && $user->getId() === $command->ownerUserId;
      }))
      ->willReturn(new CreateOrganizationResult(
        organizationId: '550e8400-e29b-41d4-a716-446655441101',
        ownerMemberId: '550e8400-e29b-41d4-a716-446655441102',
        ownerRoleId: '550e8400-e29b-41d4-a716-446655441103',
        name: 'Fireguard Toulouse',
        slug: 'fireguard-toulouse',
        ownerUserId: $user->getId(),
        createdByUserId: $user->getId(),
        status: 'active',
        createdAt: new DateTimeImmutable('2026-02-10T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-10T10:00:00+00:00'),
      ));

    $processor = new CreateOrganizationProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $output = $processor->process($input, new Post());

    self::assertInstanceOf(OrganizationOutput::class, $output);
    self::assertSame('550e8400-e29b-41d4-a716-446655441101', $output->id);
    self::assertSame('Fireguard Toulouse', $output->name);
    self::assertSame('fireguard-toulouse', $output->slug);
    self::assertSame($user->getId(), $output->ownerUserId);
    self::assertSame($user->getId(), $output->createdByUserId);
    self::assertSame('active', $output->status);
    self::assertTrue($output->isActive);
    self::assertNotSame('', $output->createdAt);
    self::assertNotSame('', $output->updatedAt);
  }

  #[Test]
  public function testProcessThrowsConflictWhenSlugAlreadyExists(): void
  {
    $input = new CreateOrganizationInput();
    $input->name = 'Fireguard Nantes';
    $input->slug = 'fireguard-nantes';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441110');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(OrganizationSlugAlreadyExistsException::withSlug('fireguard-nantes'));

    $processor = new CreateOrganizationProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsConflictWhenSlugAlreadyExistsIsWrappedInMessengerRuntimeException(): void
  {
    $input = new CreateOrganizationInput();
    $input->name = 'Fireguard Nantes';
    $input->slug = 'fireguard-nantes';

    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441110');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    $slugConflict = OrganizationSlugAlreadyExistsException::withSlug('fireguard-nantes');
    $handlerFailure = new HandlerFailedException(
      new Envelope(new CreateOrganizationCommand('Fireguard Nantes', $user->getId(), 'fireguard-nantes')),
      [$slugConflict],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new CreateOrganizationProcessor(
      commandBus: $commandBus,
      security: $security,
    );

    $this->expectException(ConflictHttpException::class);

    $processor->process($input, new Post());
  }

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn(null);

    $processor = new CreateOrganizationProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new CreateOrganizationInput(), new Post());
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
