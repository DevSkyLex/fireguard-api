<?php

declare(strict_types=1);

namespace Facility\Presentation\Api\EventSubscriber;

use ApiPlatform\Metadata\Operation;
use Facility\Presentation\Api\Operation\FacilityModelOperations;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function is_string;

/**
 * Subscriber FacilityModelCreatedSubscriber. Canonical Location for newly uploaded immutable models.
 *
 * @category EventSubscriber
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityModelCreatedSubscriber implements EventSubscriberInterface
{
  // #region Methods
  /**
   * Method getSubscribedEvents.
   *
   * Declares the kernel events handled by this subscriber.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::RESPONSE => 'onResponse'];
  }

  /**
   * Method onResponse.
   *
   * Adds the canonical Location header to successful model uploads.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ResponseEvent $event the event
   *
   * @return void no return value
   */
  public function onResponse(ResponseEvent $event): void
  {
    $request = $event->getRequest();
    $operation = $request->attributes->get('_api_operation');
    $iri = $request->attributes->get('_api_write_item_iri');
    if ($operation instanceof Operation && FacilityModelOperations::UPLOAD === $operation->getName()
      && 201 === $event->getResponse()->getStatusCode() && is_string($iri)) {
      $event->getResponse()->headers->set('Location', $iri);
    }
  }
  // #endregion
}
