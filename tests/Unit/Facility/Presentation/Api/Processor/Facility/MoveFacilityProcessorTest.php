<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\UseCase\Command\Facility\MoveFacility\{MoveFacilityCommand, MoveFacilityResult};
use Facility\Domain\Exception\FacilityHierarchyException;
use Facility\Presentation\Api\Dto\Input\Facility\MoveFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Processor\Facility\MoveFacilityProcessor;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Infrastructure\Exception\MessengerRuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

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
