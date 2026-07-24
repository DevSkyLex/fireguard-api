<?php

declare(strict_types=1);

namespace Billing\Presentation\Controller;

use Billing\Application\UseCase\Command\HandleStripeWebhook\HandleStripeWebhookCommand;
use Billing\Domain\Exception\InvalidWebhookSignatureException;
use Billing\Presentation\Api\Trait\ResolvesMessengerFailure;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};

/**
 * Controller StripeWebhookController.
 *
 * Public endpoint receiving Stripe webhook events. Reads the raw request body and
 * signature header (signature is verified inside the gateway) and hands the event
 * to the reconciliation use case. Returns 204 on success, 400 on an invalid
 * signature, and lets other failures surface as 5xx so Stripe retries.
 *
 * @category Controller
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StripeWebhookController extends AbstractController
{
  use ResolvesMessengerFailure;

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the StripeWebhookController class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles an incoming Stripe webhook request.
   *
   * @since 1.0.0
   *
   * @param Request $request the incoming HTTP request
   *
   * @return Response the HTTP response
   */
  public function __invoke(Request $request): Response
  {
    $payload = $request->getContent();
    $signature = (string) $request->headers->get('Stripe-Signature', '');

    try {
      $this->commandBus->dispatch(new HandleStripeWebhookCommand(
        payload: $payload,
        signature: $signature,
      ));
    } catch (MessengerRuntimeException $exception) {
      if (null !== $this->firstFailureOf($exception, InvalidWebhookSignatureException::class)) {
        return new Response('Invalid signature.', Response::HTTP_BAD_REQUEST);
      }

      throw $exception;
    }

    return new Response(status: Response::HTTP_NO_CONTENT);
  }
  // #endregion
}
