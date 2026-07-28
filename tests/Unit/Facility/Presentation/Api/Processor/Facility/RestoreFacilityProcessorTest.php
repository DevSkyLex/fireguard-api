<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Presentation\Api\Processor\Facility\RestoreFacilityProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(RestoreFacilityProcessor::class)]
final class RestoreFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessMapsResultToOutput(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441260';
    $facilityId = '550e8400-e29b-41d4-a716-446655441261';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441262');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(RestoreFacilityCommand::class))
      ->willReturn(new RestoreFacilityResult(
        facilityId: $facilityId,
        organizationId: $organizationId,
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
      ));

    $processor = new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => $organizationId, 'facilityId' => $facilityId],
    );

    self::assertSame('active', $output->status);
  }

  #[Test]
  public function testProcessMapsNotFoundToHttp404(): void
  {
    $processor = $this->makeProcessor(FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441271'));

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441270', 'facilityId' => '550e8400-e29b-41d4-a716-446655441271'],
    );
  }

  #[Test]
  public function testProcessMapsArchivedParentToBadRequest(): void
  {
    $processor = $this->makeProcessor(MessengerRuntimeException::wrap(FacilityArchivedException::withId('550e8400-e29b-41d4-a716-446655441281')));

    $this->expectException(BadRequestHttpException::class);

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441280', 'facilityId' => '550e8400-e29b-41d4-a716-446655441281'],
    );
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441290', 'facilityId' => '550e8400-e29b-41d4-a716-446655441291'],
    );
  }

  #[Test]
  public function testProcessRejectsMissingUriVariables(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441292'));

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId and facilityId URI parameters are required.');

    $processor->process(data: null, operation: new Patch(), uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441293']);
  }

  #[Test]
  public function testProcessRejectsCallerWithoutWritePermission(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441294'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');

    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: ['organizationId' => '550e8400-e29b-41d4-a716-446655441295', 'facilityId' => '550e8400-e29b-41d4-a716-446655441296'],
    );
  }

  #[Test]
  public function testProcessMapsDirectInvalidArgumentToBadRequest(): void
  {
    $processor = $this->makeProcessor(new InvalidArgumentException('Malformed identifier.'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Malformed identifier.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441297'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedNotFound(): void
  {
    $processor = $this->makeProcessor($this->handlerFailure(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441298'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedArchived(): void
  {
    $processor = $this->makeProcessor($this->handlerFailure(
      FacilityArchivedException::withId('550e8400-e29b-41d4-a716-446655441299'),
    ));

    $this->expectException(BadRequestHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedInvalidArgument(): void
  {
    $processor = $this->makeProcessor(MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedInvalidArgument(): void
  {
    $processor = $this->makeProcessor($this->handlerFailure(new InvalidArgumentException('Handler invalid argument.')));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Handler invalid argument.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRethrowsUnrecognisedMessengerFailure(): void
  {
    $processor = $this->makeProcessor(MessengerRuntimeException::wrap(new RuntimeException('infrastructure down')));

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('infrastructure down');

    $this->dispatch($processor);
  }

  private function dispatch(RestoreFacilityProcessor $processor): void
  {
    $processor->process(
      data: null,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441300',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441301',
      ],
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new RestoreFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441300',
        facilityId: '550e8400-e29b-41d4-a716-446655441301',
      )),
      [$exception],
    ));
  }

  private function makeProcessor(Throwable $exception): RestoreFacilityProcessor
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441282'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($exception);

    return new RestoreFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );
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
