<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Patch;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\UpdateFacility\{UpdateFacilityCommand, UpdateFacilityResult};
use Facility\Domain\Exception\{FacilityCodeAlreadyExistsException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Input\Facility\UpdateFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\UpdateFacilityProcessor;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(UpdateFacilityProcessor::class)]
final class UpdateFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenNoPatchFieldsAreProvided(): void
  {
    $input = new UpdateFacilityInput();

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441100'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())
      ->method('dispatch');

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('At least one field must be provided for update.');

    $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441101',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441102',
      ],
    );
  }

  #[Test]
  public function testProcessDispatchesPartialUpdateCommandWithPresenceFlags(): void
  {
    $input = new UpdateFacilityInput();
    $input->name = 'HQ Updated';

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441110'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('550e8400-e29b-41d4-a716-446655441110', '550e8400-e29b-41d4-a716-446655441111', 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"name":"HQ Updated"}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateFacilityCommand $command): bool {
        return $command->hasName
          && !$command->hasType
          && !$command->hasCode
          && !$command->hasAddress
          && !$command->hasMetadata
          && 'HQ Updated' === $command->name;
      }))
      ->willReturn(new UpdateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655441112',
        organizationId: '550e8400-e29b-41d4-a716-446655441111',
        parentFacilityId: null,
        type: 'site',
        name: 'HQ Updated',
        code: 'SITE-1',
        status: 'active',
        address: 'Address',
        metadata: ['k' => 'v'],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
      ));

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441111',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441112',
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame('HQ Updated', $output->name);
    self::assertSame('site', $output->type);
    self::assertSame('SITE-1', $output->code);
  }

  #[Test]
  public function testProcessDispatchesCommandWithCoordinatePresenceFlags(): void
  {
    $input = new UpdateFacilityInput();
    $input->latitude = 48.8566;
    $input->longitude = 2.3522;

    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655442100'));

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"latitude":48.8566,"longitude":2.3522}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (UpdateFacilityCommand $command): bool {
        return $command->hasLatitude
          && $command->hasLongitude
          && 48.8566 === $command->latitude
          && 2.3522 === $command->longitude;
      }))
      ->willReturn(new UpdateFacilityResult(
        facilityId: '550e8400-e29b-41d4-a716-446655442102',
        organizationId: '550e8400-e29b-41d4-a716-446655442101',
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: null,
        status: 'active',
        address: null,
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T11:00:00+00:00'),
        latitude: 48.8566,
        longitude: 2.3522,
      ));

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $output = $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655442101',
        'facilityId' => '550e8400-e29b-41d4-a716-446655442102',
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertSame(48.8566, $output->latitude);
    self::assertSame(2.3522, $output->longitude);
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
      requestStack: new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRejectsMissingUriVariables(): void
  {
    $processor = $this->makeProcessor(requestStack: new RequestStack());

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('OrganizationId and facilityId URI parameters are required.');

    $processor->process(data: new UpdateFacilityInput(), operation: new Patch(), uriVariables: ['organizationId' => '']);
  }

  #[Test]
  public function testProcessRejectsCallerWithoutWritePermission(): void
  {
    $processor = $this->makeProcessor(requestStack: new RequestStack(), decision: OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsOutsideScopeToHttp404(): void
  {
    $processor = $this->makeProcessor(requestStack: new RequestStack(), decision: OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRejectsMissingRequest(): void
  {
    $processor = $this->makeProcessor(requestStack: new RequestStack());

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Request not available.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessRejectsMalformedJsonPayload(): void
  {
    $processor = $this->makeProcessor(content: '{not json');

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid JSON payload.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectCodeConflictToHttp409(): void
  {
    $processor = $this->makeProcessor(exception: FacilityCodeAlreadyExistsException::withCode('SITE-1'));

    $this->expectException(ConflictHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectNotFoundToHttp404(): void
  {
    $processor = $this->makeProcessor(exception: FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441121'));

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
  public function testProcessUnwrapsDirectlyWrappedCodeConflict(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      FacilityCodeAlreadyExistsException::withCode('SITE-2'),
    ));

    $this->expectException(ConflictHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedCodeConflict(): void
  {
    $processor = $this->makeProcessor(exception: $this->handlerFailure(FacilityCodeAlreadyExistsException::withCode('SITE-3')));

    $this->expectException(ConflictHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441122'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(exception: $this->handlerFailure(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441123'),
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

  private function dispatch(UpdateFacilityProcessor $processor): void
  {
    $input = new UpdateFacilityInput();
    $input->name = 'HQ Renamed';

    $processor->process(
      data: $input,
      operation: new Patch(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441120',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441121',
      ],
    );
  }

  private function makeProcessor(
    ?Throwable $exception = null,
    string $content = '{"name":"HQ Renamed"}',
    ?RequestStack $requestStack = null,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
  ): UpdateFacilityProcessor {
    if (null === $requestStack) {
      $requestStack = new RequestStack();
      $requestStack->push(new Request(server: ['CONTENT_TYPE' => 'application/json'], content: $content));
    }

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441119'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $commandBus = $this->createStub(CommandBusPort::class);

    if (null !== $exception) {
      $commandBus->method('dispatch')->willThrowException($exception);
    }

    return new UpdateFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new UpdateFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441120',
        facilityId: '550e8400-e29b-41d4-a716-446655441121',
        hasName: true,
        name: 'HQ Renamed',
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
