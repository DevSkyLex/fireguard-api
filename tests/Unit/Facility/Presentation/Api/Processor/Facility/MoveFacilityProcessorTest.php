<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\MoveFacility\{MoveFacilityCommand, MoveFacilityResult};
use Facility\Domain\Exception\{FacilityHierarchyException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Input\Facility\MoveFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\MoveFacilityProcessor;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(MoveFacilityProcessor::class)]
final class MoveFacilityProcessorTest extends TestCase
{
  #[Test]
  public function testProcessThrowsWhenParentFacilityIdFieldIsMissing(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441236';
    $facilityId = '550e8400-e29b-41d4-a716-446655441237';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441238');

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

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new MoveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Field "parentFacilityId" must be provided. Use null to detach.');

    $processor->process(
      data: new MoveFacilityInput(),
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );
  }

  #[Test]
  public function testProcessAllowsExplicitNullToDetachFacility(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441233';
    $facilityId = '550e8400-e29b-41d4-a716-446655441234';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441235');

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

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (MoveFacilityCommand $command): bool {
        return '550e8400-e29b-41d4-a716-446655441233' === $command->organizationId
          && '550e8400-e29b-41d4-a716-446655441234' === $command->facilityId
          && null === $command->parentFacilityId;
      }))
      ->willReturn(new MoveFacilityResult(
        facilityId: $facilityId,
        organizationId: $organizationId,
        parentFacilityId: null,
        type: 'site',
        name: 'HQ',
        code: 'SITE-001',
        status: 'active',
        address: '10 rue',
        metadata: [],
        createdAt: new DateTimeImmutable('2026-02-12T10:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-02-12T10:10:00+00:00'),
      ));

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"parentFacilityId":null}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new MoveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $input = new MoveFacilityInput();
    $input->parentFacilityId = null;

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );

    self::assertInstanceOf(FacilityOutput::class, $output);
    self::assertNull($output->parentFacilityId);
  }

  #[Test]
  public function testProcessMapsWrappedHierarchyExceptionToHttp400(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441240';
    $facilityId = '550e8400-e29b-41d4-a716-446655441241';
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655441242');

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
      envelope: new Envelope(new MoveFacilityCommand(
        organizationId: $organizationId,
        facilityId: $facilityId,
        parentFacilityId: '550e8400-e29b-41d4-a716-446655441243',
      )),
      exceptions: [FacilityHierarchyException::hierarchyCycleDetected()],
    );

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $request = new Request(
      server: ['CONTENT_TYPE' => 'application/json'],
      content: '{"parentFacilityId":"550e8400-e29b-41d4-a716-446655441243"}',
    );
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $processor = new MoveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );

    $input = new MoveFacilityInput();
    $input->parentFacilityId = '550e8400-e29b-41d4-a716-446655441243';

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Cannot move facility: hierarchy cycle detected.');

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => $organizationId,
        'facilityId' => $facilityId,
      ],
    );
  }

  #[Test]
  public function testProcessRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new MoveFacilityProcessor(
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

    $processor->process(data: new MoveFacilityInput(), operation: new Post(), uriVariables: ['facilityId' => '']);
  }

  #[Test]
  public function testProcessRejectsCallerWithoutWritePermission(): void
  {
    $processor = $this->makeProcessor(requestStack: new RequestStack(), hasPermission: false);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');

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
  public function testProcessMapsDirectNotFoundToHttp404(): void
  {
    $processor = $this->makeProcessor(
      exception: FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441251'),
    );

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessMapsDirectHierarchyExceptionToHttp400(): void
  {
    $processor = $this->makeProcessor(exception: FacilityHierarchyException::cannotUseSelfAsParent());

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('A facility cannot be its own parent.');

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441252'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsHandlerWrappedNotFound(): void
  {
    $processor = $this->makeProcessor(exception: $this->handlerFailure(
      FacilityNotFoundException::withId('550e8400-e29b-41d4-a716-446655441253'),
    ));

    $this->expectException(NotFoundHttpException::class);

    $this->dispatch($processor);
  }

  #[Test]
  public function testProcessUnwrapsDirectlyWrappedHierarchyException(): void
  {
    $processor = $this->makeProcessor(exception: MessengerRuntimeException::wrap(
      FacilityHierarchyException::parentInAnotherOrganization(),
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Parent facility must belong to the same organization.');

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

  private function dispatch(MoveFacilityProcessor $processor): void
  {
    $input = new MoveFacilityInput();
    $input->parentFacilityId = null;

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: [
        'organizationId' => '550e8400-e29b-41d4-a716-446655441250',
        'facilityId' => '550e8400-e29b-41d4-a716-446655441251',
      ],
    );
  }

  private function makeProcessor(
    ?Throwable $exception = null,
    string $content = '{"parentFacilityId":null}',
    ?RequestStack $requestStack = null,
    bool $hasPermission = true,
  ): MoveFacilityProcessor {
    if (null === $requestStack) {
      $requestStack = new RequestStack();
      $requestStack->push(new Request(server: ['CONTENT_TYPE' => 'application/json'], content: $content));
    }

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441249'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($hasPermission);

    $commandBus = $this->createStub(CommandBusPort::class);

    if (null !== $exception) {
      $commandBus->method('dispatch')->willThrowException($exception);
    }

    return new MoveFacilityProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
      requestStack: $requestStack,
    );
  }

  private function handlerFailure(Throwable $exception): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      envelope: new Envelope(new MoveFacilityCommand(
        organizationId: '550e8400-e29b-41d4-a716-446655441250',
        facilityId: '550e8400-e29b-41d4-a716-446655441251',
        parentFacilityId: null,
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
