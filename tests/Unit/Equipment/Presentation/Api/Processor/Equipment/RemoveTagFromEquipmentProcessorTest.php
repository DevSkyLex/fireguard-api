<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment\RemoveTagFromEquipmentCommand;
use Equipment\Domain\Exception\{EquipmentNotFoundException, TagNotFoundException};
use Equipment\Presentation\Api\Processor\Equipment\RemoveTagFromEquipmentProcessor;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{
  AccessDeniedHttpException,
  BadRequestHttpException,
  NotFoundHttpException
};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Throwable;

#[CoversClass(RemoveTagFromEquipmentProcessor::class)]
final class RemoveTagFromEquipmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655452001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655452002';

  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655452003';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655452010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => self::TAG_ID],
    );
  }

  #[Test]
  public function testProcessThrowsNotFoundWhenOrganizationIsOutsideCallersScope(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655452014');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    // Not AccessDeniedHttpException: a 403 for a caller outside the organization's
    // scope would confirm the record exists across an organization boundary.
    try {
      $processor->process(
        data: null,
        operation: new Post(),
        uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => self::TAG_ID],
      );
      self::fail('Expected NotFoundHttpException to be thrown.');
    } catch (NotFoundHttpException $exception) {
      self::assertSame('Organization not found.', $exception->getMessage());
    }
  }

  #[Test]
  public function testProcessMapsWrappedEquipmentNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655452011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new RemoveTagFromEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        tagId: self::TAG_ID,
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => self::TAG_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedTagNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655452012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handlerFailure = new HandlerFailedException(
      new Envelope(new RemoveTagFromEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        tagId: self::TAG_ID,
      )),
      [TagNotFoundException::withId(self::TAG_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => self::TAG_ID],
    );
  }

  #[Test]
  public function testProcessReturnsNullOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655452013');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())->method('dispatch');

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: null,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => self::TAG_ID],
    );

    self::assertNull($output);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  /**
   * @param array<string, mixed> $uriVariables
   */
  #[Test]
  #[DataProvider('incompleteUriVariablesProvider')]
  public function testProcessThrowsBadRequestWhenUriVariablesAreIncomplete(array $uriVariables): void
  {
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->removeTagSecurity(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: null, operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectEquipmentNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->removeTagProcessorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectTagNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->removeTagProcessorThrowing(TagNotFoundException::withId(self::TAG_ID))
      ->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Tag is not attached to this equipment.');

    $this->removeTagProcessorThrowing(new InvalidArgumentException('Tag is not attached to this equipment.'))
      ->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->removeTagProcessorThrowing(
      $this->removeTagWrapped(new InvalidArgumentException('Tag is not attached to this equipment.')),
    )->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->removeTagProcessorThrowing($this->removeTagWrapped(new RuntimeException('database is down')))
      ->process(data: null, operation: new Post(), uriVariables: $this->removeTagUriVariables());
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'missing tagId' => [['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID]];
    yield 'blank tagId' => [['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID, 'tagId' => '']];
    yield 'blank equipmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => '', 'tagId' => self::TAG_ID]];
  }

  /**
   * @return array<string, string>
   */
  private function removeTagUriVariables(): array
  {
    return [
      'organizationId' => self::ORG_ID,
      'equipmentId' => self::EQUIP_ID,
      'tagId' => self::TAG_ID,
    ];
  }

  private function removeTagSecurity(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655452010'));

    return $security;
  }

  private function removeTagWrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new RemoveTagFromEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        tagId: self::TAG_ID,
      )),
      [$failure],
    ));
  }

  private function removeTagProcessorThrowing(Throwable $failure): RemoveTagFromEquipmentProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    return new RemoveTagFromEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->removeTagSecurity(),
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
