<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\{ArchiveFacilityCommand, ArchiveFacilityResult};
use Facility\Domain\Exception\{FacilityHasActiveDependentsException, FacilityNotFoundException};
use Facility\Presentation\Api\Processor\Facility\ArchiveFacilityProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
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

#[CoversClass(ArchiveFacilityProcessor::class)]
final class ArchiveFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441250';
    $facilityId = '550e8400-e29b-41d4-a716-446655441251';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441252');

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), $organizationId, 'organization.facilities.write')
      ->willReturn(true);

    $handlerFailure = new HandlerFailedException(
      envelope: new Envelope(new ArchiveFacilityCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
      )),
      exceptions: [FacilityNotFoundException::withId($facilityId)],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new ArchiveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );
  }

  #[Test]
  public function testProcessMapsResultToOutput(): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ArchiveFacilityCommand::class))
      ->willReturn(new ArchiveFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655441261',
        organizationId: '550e8400-e29b-41d4-a716-446655441260',
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
        status: 'archived',
        address: '10 rue',
        metadata: ['k' => 'v'],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
      ));

    $output = $this->makeProcessor(commandBus: $commandBus)->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441260',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441261',
      ],
    );

    self::assertSame('archived', $output->status);
    self::assertSame('SITE-001', $output->code);
    self::assertSame('2026-02-12T11:00:00+00:00', $output->updatedAt);
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new ArchiveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRejectsMissingUriVariables(): void
  {
    $processor = $this->makeProcessor();

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId and facilityId URI parameters are required.');

    $processor->process(data: null, operation: new Post(), uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441260']);
  }

  #[Test]
  public function testProcessRejectsCallerWithoutWritePermission(): void
  {
    $processor = $this->makeProcessor(hasPermission: false);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectNotFoundToHttp404(): void
  {
    $processor = $this->makeProcessor(exception: FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441261'));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectInvalidArgumentToHttp400(): void
  {
    $processor = $this->makeProcessor(exception: new InvalidArgumentException('Malformed identifier.'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Malformed identifier.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsWrappedActiveDependentsToHttp409(): void
  {
    $processor = $this->makeProcessor(exception: $this->handlerFailure(
      FacilityHasActiveDependentsException::withActiveChildFacilities('550e8400-e29b-41d4-a716-446655441261'),
    ));

    $this->expectException(ConflictHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441261'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedInvalidArgument(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedInvalidArgument(): void
  {
    $processor = $this->makeProcessor(exception: $this->handlerFailure(new InvalidArgumentException('Handler invalid argument.')));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Handler invalid argument.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRethrowsUnrecognisedMessengerFailure(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(new RuntimeException('infrastructure down')));

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('infrastructure down');

    $this->dispatch($processor);
  }

  private function dispatch(ArchiveFacilityProcessor $processor): void
  {
    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441260',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441261',
      ],
    );
  }

  private function makeProcessor(
    ?Throwable $exception = null,
    bool $hasPermission = true,
    ?CommandBusPort $commandBus = null,
  ): ArchiveFacilityProcessor {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441259'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($hasPermission);

    if (null === $commandBus) {
      $commandBus = $this->createStub(CommandBusPort::class);

      if (null !== $exception) {
        $commandBus->method('dispatch')->willThrowException($exception);
      }
    }

    return new ArchiveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new ArchiveFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441260',
        facilityId: '550e8400-e29b-41d4-a716-446655441261',
      )),
      exceptions: [$exception],
    ));
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
