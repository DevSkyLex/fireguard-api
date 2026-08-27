<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Processor\Organization;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Organization\Application\UseCase\Command\Organization\TransferOrganizationOwnership\{
  TransferOrganizationOwnershipCommand,
  TransferOrganizationOwnershipResult
};
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Domain\Exception\{
  OrganizationAccessDeniedException,
  OrganizationArchivedException,
  OrganizationDeletionConfirmationMismatchException,
  OrganizationMemberNotFoundException,
  OrganizationNotFoundException,
  OrganizationOwnershipUnchangedException
};
use Organization\Presentation\Api\Dto\Input\Organization\TransferOrganizationOwnershipInput;
use Organization\Presentation\Api\Processor\Organization\TransferOrganizationOwnershipProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException
};
use Symfony\Component\Messenger\Envelope;

#[CoversClass(TransferOrganizationOwnershipProcessor::class)]
final class TransferOrganizationOwnershipProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string NEW_OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testProcessThrowsWhenUnauthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new TransferOrganizationOwnershipProcessor(
      commandBus: $this->createStub(CommandBusPort::class),
      queryBus: $this->createStub(QueryBusPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsWhenOrganizationIdentifierIsMissing(): void
  {
    $processor = $this->createProcessor($this->createStub(CommandBusPort::class));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Organization identifier is required.');

    $processor->process($this->transferInput(), new Post(), []);
  }

  #[Test]
  public function testProcessDispatchesCommandWithTheActingUserAndSlugConfirmation(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (object $command): bool => $command instanceof TransferOrganizationOwnershipCommand
        && self::ORGANIZATION_ID === $command->organizationId
        && self::USER_ID === $command->actingUserId
        && self::NEW_OWNER_USER_ID === $command->newOwnerUserId
        && 'fireguard-nice' === $command->slugConfirmation))
      ->willReturn(new TransferOrganizationOwnershipResult(
        organizationId: self::ORGANIZATION_ID,
        previousOwnerUserId: self::USER_ID,
        newOwnerUserId: self::NEW_OWNER_USER_ID,
        transferredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn($this->organizationResult());

    $processor = $this->createProcessor($commandBus, $queryBus);

    $output = $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);

    self::assertSame(self::ORGANIZATION_ID, $output->id);
    self::assertSame(self::NEW_OWNER_USER_ID, $output->ownerUserId);
  }

  #[Test]
  public function testProcessThrowsForbiddenWhenActingUserIsNotTheOwner(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationAccessDeniedException::ownershipTransferRequiresCurrentOwner());

    $processor = $this->createProcessor($commandBus);

    // The processor no longer maps: `api_platform.exception_to_status` carries
    // the 403 and `BusFailureUnwrappingSubscriber` unwraps the bus envelope.
    // The unit's job is to let the domain exception through untouched.
    $this->expectException(OrganizationAccessDeniedException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsUnprocessableEntityWhenSlugConfirmationIsMissing(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationDeletionConfirmationMismatchException::missing());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $processor->process($this->transferInput(slug: null), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsUnprocessableEntityWhenSlugConfirmationIsMismatched(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationDeletionConfirmationMismatchException::mismatched());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $processor->process($this->transferInput(slug: 'wrong-slug'), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsMissing(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationNotFoundException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenTargetIsNotAnActiveMember(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationMemberNotFoundException::forUserInOrganization(self::NEW_OWNER_USER_ID, self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationMemberNotFoundException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenOrganizationIsArchived(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationArchivedException::cannotTransferOwnership());

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationArchivedException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsConflictWhenTargetAlreadyOwnsTheOrganization(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(OrganizationOwnershipUnchangedException::withOwnerUserId(self::NEW_OWNER_USER_ID));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(OrganizationOwnershipUnchangedException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  // The two "…IsWrappedInMessengerRuntimeException" tests are gone: unwrapping
  // is now `BusFailureUnwrappingSubscriber`'s job, covered by its own test and
  // by the functional tests that exercise the real kernel. Re-asserting it here
  // against a stub bus only froze the processor's former duplicate of it.

  #[Test]
  public function testProcessRethrowsMessengerFailureWhenNoDomainExceptionIsRecognised(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap(new RuntimeException('Bus transport is down.')));

    $processor = $this->createProcessor($commandBus);

    $this->expectException(MessengerRuntimeException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenRefreshedOrganizationCannotBeRead(): void
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new TransferOrganizationOwnershipResult(
      organizationId: self::ORGANIZATION_ID,
      previousOwnerUserId: self::USER_ID,
      newOwnerUserId: self::NEW_OWNER_USER_ID,
      transferredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    ));

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(OrganizationNotFoundException::withId(self::ORGANIZATION_ID));

    $processor = $this->createProcessor($commandBus, $queryBus);

    $this->expectException(OrganizationNotFoundException::class);

    $processor->process($this->transferInput(), new Post(), ['id' => self::ORGANIZATION_ID]);
  }

  private function transferInput(?string $slug = 'fireguard-nice'): TransferOrganizationOwnershipInput
  {
    $input = new TransferOrganizationOwnershipInput();
    $input->newOwnerUserId = self::NEW_OWNER_USER_ID;
    $input->slug = $slug;

    return $input;
  }

  private function createProcessor(CommandBusPort $commandBus, ?QueryBusPort $queryBus = null): TransferOrganizationOwnershipProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser());

    return new TransferOrganizationOwnershipProcessor(
      commandBus: $commandBus,
      queryBus: $queryBus ?? $this->createStub(QueryBusPort::class),
      security: $security,
    );
  }

  private function createSecurityUser(): SecurityUser
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

  private function organizationResult(): GetOrganizationResult
  {
    return new GetOrganizationResult(
      id: self::ORGANIZATION_ID,
      name: 'Fireguard Test',
      slug: 'fireguard-nice',
      ownerUserId: self::NEW_OWNER_USER_ID,
      createdByUserId: self::USER_ID,
      status: 'active',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      planId: null,
      planName: null,
    );
  }
}
