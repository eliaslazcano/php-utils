<?php
/**
 * Realiza requisições HTTP.
 */

namespace Eliaslazcano\Helpers\Http;

class ClienteHttp
{
  /** @var string Prefixo da URL da API */
  public $baseUrl;
  /** @var int Tempo limite para receber a resposta HTTP */
  public $timeout;

  /**
   * @param string $baseUrl Prefixo da URL para as requisições.
   * @param int $timeout Tempo limite para receber a resposta HTTP.
   */
  public function __construct(string $baseUrl = '', int $timeout = 60)
  {
    if ($baseUrl) $this->baseUrl = rtrim(trim($baseUrl), '/');
    if ($timeout !== null) $this->timeout = $timeout;
  }

  public function interceptarResposta(RespostaHttp $resposta): RespostaHttp
  {
    //Padrao para erros JSON das APIs que utilizam o HttpHelper do Elias
    if (!$resposta->error && $resposta->response && $resposta->code >= 400 && $resposta->isJson()) {
      $conteudo = $resposta->getJson();
      if (!empty($conteudo->mensagem)) $resposta->error = $conteudo->mensagem;
    }
    return $resposta;
  }

  /**
   * Envia requisições HTTP para o sistema da API.
   * @param string $method Método da requisição (GET, POST, PUT, PATCH).
   * @param string $endpoint URL da requisição, omitindo a baseUrl, exemplo: '/v2/cob/'.
   * @param string|null $body Texto JSON para o corpo da requisição.
   * @param array $headers Cabeçalhos da requisição.
   * @return RespostaHttp Um objeto contendo informações da resposta HTTP.
   */
  public function send(string $method, string $endpoint, string $body = null, array $headers = []): RespostaHttp
  {
    $endpoint = ltrim($endpoint, '/');
    $url = $this->baseUrl . '/' . $endpoint;

    $headers[] = 'Cache-Control: no-cache';
    if (in_array($method, ['POST','PUT','PATCH'])) $headers[] = 'Content-Type: application/json';

    //CONFIGURA O CURL
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 12,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
    ]);

    if (in_array($method, ['POST','PUT','PATCH'])) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

    //EXECUTA O CURL
    $return = array();
    $return['response'] = curl_exec($curl);
    $return['error'] = curl_error($curl) ?: null;
    $return['code'] = !$return['error'] ? curl_getinfo($curl, CURLINFO_HTTP_CODE) : null;
    $return['type'] = !$return['error'] ? curl_getinfo($curl, CURLINFO_CONTENT_TYPE) : null;
    curl_close($curl);

    $resposta = new RespostaHttp($url, $return['error'], $return['code'], $return['type'], $return['response']);
    return $this->interceptarResposta($resposta);
  }
}