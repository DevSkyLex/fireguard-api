<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Controller;

use Auth\Application\Contract\User\AuthenticatedUser;
use Compliance\Application\UseCase\Command\Export\ExportSafetyRegister\{ExportSafetyRegisterCommand, ExportSafetyRegisterResult};
use Shared\Application\Port\Inbound\CommandBusPort;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function sprintf;

/**
 * Class ExportSafetyRegisterController
 *
 * Translates the HTTP export scope and returns the PDF produced by its owning use case.
 *
 * @category Controller
 */
final class ExportSafetyRegisterController extends AbstractController
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param CommandBusPort $commandBus register export use case
   * @param Security $security current HTTP principal
   *
   * @return void
   */
  public function __construct(private readonly CommandBusPort $commandBus, private readonly Security $security)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param Request $request HTTP route attributes
   *
   * @return Response rendered PDF attachment
   */
  public function __invoke(Request $request): Response
  {
    $user = $this->security->getUser();
    $organizationId = $request->attributes->get('organizationId');
    if (!$user instanceof AuthenticatedUser || !is_string($organizationId) || '' === $organizationId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $rawFacilityId = $request->attributes->get('facilityId');
    $facilityId = is_string($rawFacilityId) && '' !== $rawFacilityId ? $rawFacilityId : null;
    /** @var ExportSafetyRegisterResult $result */
    $result = $this->commandBus->dispatch(new ExportSafetyRegisterCommand($organizationId, $user->getId(), $facilityId));

    return new Response($result->contents(), Response::HTTP_OK, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => sprintf('attachment; filename="%s"', $result->fileName),
    ]);
  }
  // #endregion
}
