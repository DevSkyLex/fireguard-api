<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Equipment\Application\UseCase\Command\Equipment\ReplaceEquipment\{ReplaceEquipmentCommand, ReplaceEquipmentResult};
use Equipment\Presentation\Api\Dto\Input\Equipment\ReplaceEquipmentInput;
use Equipment\Presentation\Api\Processor\Equipment\ReplaceEquipmentProcessor;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Class ReplaceEquipmentProcessorTest
 *
 * Freezes permission denial and exact replacement input/output translation.
 *
 * @category Unit Tests
 */
final class ReplaceEquipmentProcessorTest extends TestCase
{
  // #region Methods
  /**
   * Method denials
   *
   * @access public
   *
   * @return iterable<string, array{OrganizationAccessDecision, int}> contextual denial decisions
   */
  public static function denials(): iterable
  {
    yield 'foreign organization is hidden' => [OrganizationAccessDecision::OUTSIDE_SCOPE, 404];
    yield 'missing permission' => [OrganizationAccessDecision::MISSING_PERMISSION, 403];
  }

  /**
   * Method deniesWithoutDispatching
   *
   * @access public
   *
   * @param OrganizationAccessDecision $decision the denied access
   * @param int $status the expected HTTP status
   *
   * @return void
   */
  #[Test]
  #[DataProvider('denials')]
  public function deniesWithoutDispatching(OrganizationAccessDecision $decision, int $status): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');

    try {
      $this->processor($commands, $decision)->process(new ReplaceEquipmentInput(), new Post(), ['organizationId' => 'org', 'equipmentId' => 'old']);
    } catch (HttpExceptionInterface $failure) {
      self::assertSame($status, $failure->getStatusCode());

      return;
    }
    self::fail('Expected contextual denial.');
  }

  /**
   * Method translatesExistingSuccessorAndDurableResult
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function translatesExistingSuccessorAndDurableResult(): void
  {
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $command): bool => $command instanceof ReplaceEquipmentCommand && 'org' === $command->organizationId
      && 'old' === $command->equipmentId && 'operation' === $command->clientOperationId && 'new' === $command->successorEquipmentId,
    ))->willReturn(new ReplaceEquipmentResult('old', 'new', 'operation', true));
    $input = new ReplaceEquipmentInput();
    $input->clientOperationId = 'operation';
    $input->successorEquipmentId = 'new';
    $output = $this->processor($commands)->process($input, new Post(), ['organizationId' => 'org', 'equipmentId' => 'old']);
    self::assertSame('old', $output->predecessorEquipmentId);
    self::assertSame('new', $output->successorEquipmentId);
    self::assertSame('operation', $output->clientOperationId);
    self::assertTrue($output->replayed);
  }

  /**
   * Method processor
   *
   * @access private
   *
   * @param CommandBusPort $commands the command spy
   * @param OrganizationAccessDecision $decision the access decision
   *
   * @return ReplaceEquipmentProcessor the HTTP translator
   */
  private function processor(CommandBusPort $commands, OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED): ReplaceEquipmentProcessor
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('550e8400-e29b-41d4-a716-446655440001', 'replace@example.test', 'hashed', ['ROLE_USER']));

    return new ReplaceEquipmentProcessor($commands, $authorization, $security);
  }
  // #endregion
}
