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
    $this->baseUrl = rtrim(trim($baseUrl ?: $this->baseUrl ?: ''), '/');
    $this->timeout = $timeout;
  }

  /**
   * Funcao que eh auto invocada para modificar a resposta Http.
   * @param RespostaHttp $respostaHttp
   * @return RespostaHttp
   */
  public function interceptarResposta(RespostaHttp $respostaHttp): RespostaHttp
  {
    //Padrao para erros JSON das APIs que utilizam o HttpHelper do Elias
    if (!$respostaHttp->error && $respostaHttp->response && $respostaHttp->code >= 400 && $respostaHttp->isJson()) {
      $conteudo = $respostaHttp->getJson();
      if (!empty($conteudo->mensagem)) $respostaHttp->error = $conteudo->mensagem;
    }
    return $respostaHttp;
  }

  /**
   * Envia uma requisição HTTP para a API e retorna a resposta encapsulada.
   * @param string $method Tipo da requisição (ex: 'GET', 'POST', 'PUT', 'PATCH').
   * @param string $endpoint Caminho relativo da URL da API (sem a base URL), ex: '/v2/cob/'.
   * @param string|null $body Corpo da requisição (usado para POST, PUT, PATCH). ex: json_encode(['valor' => 10]).
   * @param string[] $headers Lista de cabeçalhos adicionais a serem enviados na requisição. ex: ['Authorization: Bearer xxx']
   * @param resource|null $curl Recurso cURL reutilizável; se não fornecido, será criado internamente.
   * @return RespostaHttp Objeto contendo status HTTP, corpo da resposta, headers e outros metadados.
   */
  public function send(string $method, string $endpoint, ?string $body = null, array $headers = [], &$curl = null): RespostaHttp
  {
    $endpoint = ltrim($endpoint, '/');
    $url = $this->baseUrl . '/' . $endpoint;

    $headers[] = 'Cache-Control: no-cache';
    if ($body && in_array($method, array('POST','PUT','PATCH'))) $headers[] = 'Content-Type: application/json';

    // Configura o CURL
    if (!$curl) $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => true, // ← IMPORTANTE
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 8,
      CURLOPT_TIMEOUT => $this->timeout,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
    ]);

    if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

    // Executa
    $response = curl_exec($curl);
    $error = curl_error($curl) ?: null;
    $code = !$error ? curl_getinfo($curl, CURLINFO_HTTP_CODE) : null;
    $type = !$error ? curl_getinfo($curl, CURLINFO_CONTENT_TYPE) : null;

    $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);

    curl_close($curl);

    // Captura o nome do arquivo, se presente
    $filename = null;
    if (preg_match('/Content-Disposition:.*filename=["\']?([^"\';]+)["\']?/i', $header, $matches)) {
      $filename = $matches[1];
    }

    // Cria a resposta e insere o filename, se houver
    $resposta = new RespostaHttp($url, $error, $code, $type, $body);
    if ($filename) $resposta->filename = $filename;

    return $this->interceptarResposta($resposta);
  }
}