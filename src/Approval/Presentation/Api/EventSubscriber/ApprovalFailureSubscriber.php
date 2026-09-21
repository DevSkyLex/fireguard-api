<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\EventSubscriber;

use Approval\Domain\Exception\{ApprovalAccessDeniedException, ApprovalRequestNotFoundException, ApprovalRequestNotPendingException, ApprovalWithdrawalNotAllowedException, ApproverNotAuthorizedException, DeferredActionNoLongerApplicableException, SelfApprovalNotAllowedException};
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Stable public decision codes; preserves validation and scope errors handled by API Platform. */
final readonly class ApprovalFailureSubscriber implements EventSubscriberInterface
{
  public static function getSubscribedEvents(): array
  {
    return [KernelEvents::EXCEPTION => ['onException', 10]];
  }

  public function onException(ExceptionEvent $event): void
  {
    $error = $event->getThrowable();
    do {
      $code = match (true) {
        $error instanceof ApprovalRequestNotFoundException => 'approval_not_found',
        $error instanceof ApprovalWithdrawalNotAllowedException => 'approval_withdrawal_forbidden',
        $error instanceof ApprovalAccessDeniedException => 'approval_permission_required',
        $error instanceof ApproverNotAuthorizedException => 'approval_role_required',
        $error instanceof SelfApprovalNotAllowedException => 'approval_self_decision_forbidden',
        $error instanceof ApprovalRequestNotPendingException => 'approval_not_pending',
        $error instanceof DeferredActionNoLongerApplicableException => 'approval_subject_changed',
        default => null,
      };
      if (null !== $code) {
        $status = match (true) {
          $error instanceof ApprovalRequestNotFoundException => 404,
          $error instanceof ApprovalRequestNotPendingException, $error instanceof DeferredActionNoLongerApplicableException => 409,
          default => 403,
        };
        $event->setResponse(new JsonResponse([
          'type' => '/errors/' . $code, 'title' => 'Approval decision refused',
          'status' => $status, 'code' => $code, 'detail' => $error->getMessage(),
        ], $status, ['Content-Type' => 'application/problem+json']));

        return;
      }
      $error = $error->getPrevious();
    } while (null !== $error);
  }
}
