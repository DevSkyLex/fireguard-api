<?php

declare(strict_types=1);

namespace Messaging\Presentation\Api\Dto\Input;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO GetOrCreateConversationInput.
 *
 * `POST /api/conversations` request body (get-or-create by subject).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class GetOrCreateConversationInput
{
  /**
   * Property organization.
   *
   * The organization IRI (or bare identifier).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $organization = '';

  /**
   * Property subjectType.
   *
   * One of `facility`, `equipment`, `intervention`, `non_conformity`.
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $subjectType = '';

  /**
   * Property subject.
   *
   * The subject IRI (or bare identifier).
   *
   * @since 1.0.0
   */
  #[Assert\NotBlank]
  public string $subject = '';
}
