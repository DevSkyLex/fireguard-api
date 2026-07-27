<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment\{AddTagToEquipmentCommand, AddTagToEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Presentation\Api\Dto\Input\Equipment\AddTagInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\TagOutput;
use Equipment\Presentation\Api\Processor\Equipment\AddTagToEquipmentProcessor;
use InvalidArgumentException;
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

#[CoversClass(AddTagToEquipmentProcessor::class)]
final class AddTagToEquipmentProcessorTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655457001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655457002';

  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655457003';

  #[Test]
  public function testProcessThrowsAccessDeniedWhenPermissionMissing(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655457010');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with($user->getId(), self::ORG_ID, 'organization.equipment.write')
      ->willReturn(false);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $input = new AddTagInput();
    $input->name = 'urgent';

    $processor = new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsWrappedNotFoundToHttp404(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655457011');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $input = new AddTagInput();
    $input->name = 'urgent';

    $handlerFailure = new HandlerFailedException(
      new Envelope(new AddTagToEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        tagName: 'urgent',
      )),
      [EquipmentNotFoundException::withId(self::EQUIP_ID)],
    );

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $processor = new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessReturnsTagOutputOnSuccess(): void
  {
    $user = $this->createSecurityUser('550e8400-e29b-41d4-a716-446655457012');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($user);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $input = new AddTagInput();
    $input->name = 'urgent';

    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willReturn(new AddTagToEquipmentResult(
      tagId: self::TAG_ID,
      tagName: 'urgent',
      organizationId: self::ORG_ID,
    ));

    $processor = new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $processor->process(
      data: $input,
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );

    self::assertInstanceOf(TagOutput::class, $output);
    self::assertSame(self::TAG_ID, $output->id);
    self::assertSame('urgent', $output->name);
    self::assertSame(self::ORG_ID, $output->organizationId);
  }

  #[Test]
  public function testProcessThrowsAccessDeniedWhenNoUserIsAuthenticated(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $processor = new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $processor->process(
      data: new AddTagInput(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
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

    $processor = new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $this->securityWithUser(),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(data: new AddTagInput(), operation: new Post(), uriVariables: $uriVariables);
  }

  #[Test]
  public function testProcessMapsADirectNotFoundToHttp404(): void
  {
    $this->expectException(NotFoundHttpException::class);

    $this->processorThrowing(EquipmentNotFoundException::withId(self::EQUIP_ID))->process(
      data: $this->input(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsADirectInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Tag name cannot be blank.');

    $this->processorThrowing(new InvalidArgumentException('Tag name cannot be blank.'))->process(
      data: $this->input(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessMapsAWrappedInvalidArgumentToHttp400(): void
  {
    $this->expectException(BadRequestHttpException::class);

    $this->processorThrowing($this->wrapped(new InvalidArgumentException('Tag name cannot be blank.')))->process(
      data: $this->input(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  #[Test]
  public function testProcessRethrowsAnUnrecognisedMessengerFailure(): void
  {
    $this->expectException(MessengerRuntimeException::class);

    $this->processorThrowing($this->wrapped(new RuntimeException('database is down')))->process(
      data: $this->input(),
      operation: new Post(),
      uriVariables: ['organizationId' => self::ORG_ID, 'equipmentId' => self::EQUIP_ID],
    );
  }

  /**
   * @return iterable<string, array{array<string, mixed>}>
   */
  public static function incompleteUriVariablesProvider(): iterable
  {
    yield 'no variables' => [[]];
    yield 'blank organizationId' => [['organizationId' => '', 'equipmentId' => self::EQUIP_ID]];
    yield 'missing equipmentId' => [['organizationId' => self::ORG_ID]];
    yield 'blank equipmentId' => [['organizationId' => self::ORG_ID, 'equipmentId' => '']];
  }

  private function input(): AddTagInput
  {
    $input = new AddTagInput();
    $input->name = 'urgent';

    return $input;
  }

  private function wrapped(Throwable $failure): MessengerRuntimeException
  {
    return MessengerRuntimeException::wrap(new HandlerFailedException(
      new Envelope(new AddTagToEquipmentCommand(
        organizationId: self::ORG_ID,
        equipmentId: self::EQUIP_ID,
        tagName: 'urgent',
      )),
      [$failure],
    ));
  }

  private function processorThrowing(Throwable $failure): AddTagToEquipmentProcessor
  {
    $commandBus = $this->createStub(CommandBusPort::class);
    $commandBus->method('dispatch')->willThrowException($failure);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return new AddTagToEquipmentProcessor(
      commandBus: $commandBus,
      authorization: $authorization,
      security: $this->securityWithUser(),
    );
  }

  private function securityWithUser(): Security
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655457013'));

    return $security;
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
