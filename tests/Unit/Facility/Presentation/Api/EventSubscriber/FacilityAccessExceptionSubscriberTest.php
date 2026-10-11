<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\EventSubscriber;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use ApiPlatform\Metadata\Get;
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityAttachmentNotFoundException, FacilityCreationPreconditionFailedException, FacilityNotFoundException};
use Facility\Presentation\Api\EventSubscriber\FacilityAccessExceptionSubscriber;
use Intervention\Application\Contract\Resource\ClientResourceAlreadyExistsException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Contract\Exception\MessengerRuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, HttpExceptionInterface};
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/** Pins HTTP denial contracts after decisions move into typed application queries and commands. */
#[CoversClass(FacilityAccessExceptionSubscriber::class)]
final class FacilityAccessExceptionSubscriberTest extends TestCase
{
  #[Test]
  #[DataProvider('denials')]
  public function mapsDirectAndWrappedApplicationDenials(Throwable $failure, int $status): void
  {
    foreach ([$failure, MessengerRuntimeException::wrap($failure)] as $error) {
      $event = $this->event($error);
      new FacilityAccessExceptionSubscriber()->onException($event);
      self::assertInstanceOf(HttpExceptionInterface::class, $event->getThrowable());
      self::assertSame($status, $event->getThrowable()->getStatusCode());
      self::assertSame($failure->getMessage(), $event->getThrowable()->getMessage());
    }
  }

  /**
   * @return list<array{Throwable,int}>
   */
  public static function denials(): array
  {
    return [[new FacilityAccessDeniedException('Missing permission.'), 403], [new FacilityNotFoundException('Facility not found.'), 404], [FacilityAttachmentNotFoundException::withId('attachment-id'), 404], [new FacilityCreationPreconditionFailedException('Client identity already exists.'), 412]];
  }

  #[Test]
  public function unrelatedFailureRemainsUntouched(): void
  {
    $error = new RuntimeException('Storage unavailable.');
    $event = $this->event($error);
    new FacilityAccessExceptionSubscriber()->onException($event);
    self::assertSame($error, $event->getThrowable());
  }

  #[Test]
  public function clientIdentityConflictKeepsItsProblemDetails(): void
  {
    $failure = new ClientResourceAlreadyExistsException('A resource with this client identifier already exists.');
    foreach ([$failure, MessengerRuntimeException::wrap($failure)] as $error) {
      $event = $this->event($error);
      $event->getRequest()->attributes->set('_api_resource_class', 'Facility\\Presentation\\Api\\Resource\\CanonicalFacilityResource');
      new FacilityAccessExceptionSubscriber()->onException($event);
      $mapped = $event->getThrowable();
      self::assertInstanceOf(ProblemExceptionInterface::class, $mapped);
      self::assertSame(409, $mapped->getStatus());
      self::assertSame('/problems/client-resource-already-exists', $mapped->getType());
      self::assertSame('Client resource already exists', $mapped->getTitle());
      self::assertSame($failure->getMessage(), $mapped->getDetail());
      self::assertSame($failure, $mapped->getPrevious());
    }
  }

  #[Test]
  public function clientIdentityConflictFromAnotherModuleRemainsUntouched(): void
  {
    $failure = new ClientResourceAlreadyExistsException('A resource with this client identifier already exists.');
    $event = $this->event($failure);
    $event->getRequest()->attributes->set('_api_resource_class', 'Equipment\\Presentation\\Api\\Resource\\CanonicalEquipmentResource');
    new FacilityAccessExceptionSubscriber()->onException($event);
    self::assertSame($failure, $event->getThrowable());
  }

  #[Test]
  public function operationMetadataProvidesTheFacilityScope(): void
  {
    $failure = new ClientResourceAlreadyExistsException('A resource with this client identifier already exists.');
    $event = $this->event($failure);
    $event->getRequest()->attributes->set('_api_operation', new Get(class: 'Facility\\Presentation\\Api\\Resource\\CanonicalFacilityResource'));
    new FacilityAccessExceptionSubscriber()->onException($event);
    self::assertInstanceOf(ProblemExceptionInterface::class, $event->getThrowable());
    self::assertSame(409, $event->getThrowable()->getStatus());
  }

  #[Test]
  public function alreadyMappedConflictRemainsUntouched(): void
  {
    $failure = new ConflictHttpException('Mapped detail.', new ClientResourceAlreadyExistsException('Original detail.'));
    $event = $this->event($failure);
    $event->getRequest()->attributes->set('_api_resource_class', 'Facility\\Presentation\\Api\\Resource\\CanonicalFacilityResource');
    new FacilityAccessExceptionSubscriber()->onException($event);
    self::assertSame($failure, $event->getThrowable());
  }

  private function event(Throwable $error): ExceptionEvent
  {
    return new ExceptionEvent($this->createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $error);
  }
}
