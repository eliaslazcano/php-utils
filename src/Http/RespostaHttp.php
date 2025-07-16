<?php
/**
 * Modelo de objeto para respostas HTTP.
 */

namespace Eliaslazcano\Helpers\Http;

class RespostaHttp
{
  /** @var string URL da requisição. */
  public $url;
  /** @var string|null Mensagem de erro quando houver. */
  public $error;
  /** @var int|null Código HTTP da resposta. */
  public $code;
  /** @var string|null O mime type da resposta. */
  public $type;
  /** @var string|null O corpo da resposta em texto puro (body). */
  public $response;
  /** @var string|null O nome do arquivo obtido (filtrado do header Content-Disposition). */
  public $filename = null;

  /**
   * @param string|null $error Mensagem de erro quando houver.
   * @param int|null $code Código HTTP da resposta.
   * @param string|null $type O mime type da resposta.
   * @param string|null $response O corpo da resposta.
   */
  public function __construct(string $url, ?string $error = null, ?int $code = null, ?string $type = null, ?string $response = null)
  {
    $this->url = $url;
    $this->error = $error;
    $this->code = $code;
    $this->type = $type;
    $this->response = $response;
  }

  /**
   * Descubra se a resposta está em formato JSON.
   * @return bool
   */
  public function isJson(): bool
  {
    return $this->type && substr($this->type, 0, 16) === 'application/json';
  }

  /**
   * Descubra se a resposta está em formato JSON.
   * @return bool
   */
  public function ehJson(): bool
  {
    return $this->isJson();
  }

  /**
   * Retorna o conteúdo JSON parseado. Se o conteúdo não for do tipo JSON, retorna null.
   * @param bool $associative false para objeto, true para array associativo.
   * @return mixed|null
   */
  public function getJson(bool $associative = false)
  {
    if (!$this->isJson()) return null;
    return json_decode($this->response, $associative);
  }

  /**
   * Retorna o conteúdo JSON parseado. Se o conteúdo não for do tipo JSON, retorna null.
   * @param bool $associativo false para objeto, true para array associativo.
   * @return mixed|null
   */
  public function obterJson(bool $associativo = false)
  {
    return $this->getJson($associativo);
  }

  public function __get($name)
  {
    if ($name === 'body') return $this->response;
    if ($name === 'erro') return $this->error;
    return null;
  }
}