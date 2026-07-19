<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Template\CreateInterventionTemplate;

use DateInterval;
use Exception;
use Intervention\Application\Port\Outbound\InterventionTemplatePort;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionValidationException};
use Intervention\Domain\ValueObject\{InterventionPriority, InterventionType};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;

use function mb_strlen;
use function trim;

/**
 * UseCase CreateInterventionTemplateHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateInterventionTemplateHandler implements CommandHandler
{
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateInterventionTemplateHandler class.
   *
   * @since 1.0.0
   *
   * @param InterventionTemplatePort $templates the templates value
   * @param OrganizationAuthorizationPort $authorization the authorization value
   */
  public function __construct(
    private InterventionTemplatePort $templates,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  /**
   * Method __invoke.
   *
   * Executes the   invoke operation.
   *
   * @since 1.0.0
   *
   * @param CreateInterventionTemplateCommand $command the command value
   *
   * @return CreateInterventionTemplateResult the   invoke result
   */
  public function __invoke(CreateInterventionTemplateCommand $command): CreateInterventionTemplateResult
  {
    if (!$this->authorization->hasPermission($command->userId, $command->organizationId, 'organization.interventions.plan')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $name = self::validatedName($command->name);
    $type = self::validatedType($command->type);
    $priority = self::validatedPriority($command->priority);
    $duration = self::validatedDuration($command->duration);
    $items = self::validatedItems($command->items);

    return new CreateInterventionTemplateResult(
      $this->templates->create(
        organizationId: $command->organizationId,
        name: $name,
        description: $command->description,
        type: $type,
        priority: $priority,
        defaultSiteId: $command->defaultSiteId,
        defaultResponsibleId: $command->defaultResponsibleId,
        duration: $duration,
        labelIds: $command->labelIds,
        items: $items,
      ),
    );
  }

  /**
   * Method validatedName.
   *
   * @since 1.0.0
   *
   * @param string $name the raw name value
   *
   * @return string the trimmed, validated name
   */
  public static function validatedName(string $name): string
  {
    $trimmed = trim($name);
    $length = mb_strlen($trimmed);
    if ($length < 2 || $length > 160) {
      throw new InterventionValidationException('The template name must be between 2 and 160 characters.');
    }

    return $trimmed;
  }

  /**
   * Method validatedType.
   *
   * @since 1.0.0
   *
   * @param string $type the raw intervention type value
   *
   * @return string the validated intervention type value
   */
  public static function validatedType(string $type): string
  {
    $case = InterventionType::tryFrom($type);
    if (!$case instanceof InterventionType) {
      throw new InterventionValidationException('The template intervention type is invalid.');
    }

    return $case->value;
  }

  /**
   * Method validatedPriority.
   *
   * @since 1.0.0
   *
   * @param string $priority the raw intervention priority value
   *
   * @return string the validated intervention priority value
   */
  public static function validatedPriority(string $priority): string
  {
    $case = InterventionPriority::tryFrom($priority);
    if (!$case instanceof InterventionPriority) {
      throw new InterventionValidationException('The template default priority is invalid.');
    }

    return $case->value;
  }

  /**
   * Method validatedDuration.
   *
   * @since 1.0.0
   *
   * @param ?string $duration the raw ISO-8601 duration string value
   *
   * @return ?string the validated duration string
   */
  public static function validatedDuration(?string $duration): ?string
  {
    if (null === $duration || '' === $duration) {
      return null;
    }

    try {
      new DateInterval($duration);
    } catch (Exception $exception) {
      throw new InterventionValidationException('The template duration must be a valid ISO-8601 duration string.', previous: $exception);
    }

    return $duration;
  }

  /**
   * Method validatedItems.
   *
   * @since 1.0.0
   *
   * @param list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> $items the raw items value
   *
   * @return list<array{action: string, target: ?string, resultResource: ?string, required: bool, defaultAssigneeId: ?string}> the validated items
   */
  public static function validatedItems(array $items): array
  {
    $validated = [];
    foreach ($items as $item) {
      $action = trim($item['action']);
      $length = mb_strlen($action);
      if ($length < 1 || $length > 60) {
        throw new InterventionValidationException('Each template item action must be between 1 and 60 characters.');
      }
      $validated[] = [
        'action' => $action,
        'target' => $item['target'],
        'resultResource' => $item['resultResource'],
        'required' => $item['required'],
        'defaultAssigneeId' => $item['defaultAssigneeId'],
      ];
    }

    return $validated;
  }
}
