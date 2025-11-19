<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Domain\Http\HttpResponse;

/**
 * Port HttpClientPort
 *
 * Port for making HTTP requests to external services.
 * Useful for OAuth providers, webhooks, external APIs.
 *
 * @category Outbound Port
 * @package Shared\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface HttpClientPort
{
  //#region Methods
  /**
   * Method get
   *
   * Performs a GET request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $url The URL to request.
   * @param array<string, string> $headers Optional headers.
   *
   * @return HttpResponse The HTTP response.
   */
  public function get(string $url, array $headers = []): HttpResponse;

  /**
   * Method post
   *
   * Performs a POST request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $url The URL to request.
   * @param array<string, mixed>|null $body Optional request body.
   * @param array<string, string> $headers Optional headers.
   *
   * @return HttpResponse The HTTP response.
   */
  public function post(string $url, ?array $body = null, array $headers = []): HttpResponse;

  /**
   * Method put
   *
   * Performs a PUT request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $url The URL to request.
   * @param array<string, mixed>|null $body Optional request body.
   * @param array<string, string> $headers Optional headers.
   *
   * @return HttpResponse The HTTP response.
   */
  public function put(string $url, ?array $body = null, array $headers = []): HttpResponse;

  /**
   * Method delete
   *
   * Performs a DELETE request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $url The URL to request.
   * @param array<string, string> $headers Optional headers.
   *
   * @return HttpResponse The HTTP response.
   */
  public function delete(string $url, array $headers = []): HttpResponse;

  /**
   * Method patch
   *
   * Performs a PATCH request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $url The URL to request.
   * @param array<string, mixed>|null $body Optional request body.
   * @param array<string, string> $headers Optional headers.
   *
   * @return HttpResponse The HTTP response.
   */
  public function patch(string $url, ?array $body = null, array $headers = []): HttpResponse;
  //#endregion
}
