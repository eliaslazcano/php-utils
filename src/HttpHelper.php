<?php
/**
 * Oferece ferramentas para manipular facilmente a requisicao HTTP.
 * @author Elias Lazcano Castro Neto
 * @since 7.1
 */

namespace Eliaslazcano\Helpers;

abstract class HttpHelper
{
  /** @var string|null Repostas HTTP serão emitidas por padrão com este valor no header 'Access-Control-Allow-Origin'. */
  protected static $headerAllowOrigin = '*';

  /** @var string|null Repostas HTTP serão emitidas por padrão com este valor no header 'Access-Control-Allow-Headers'. */
  protected static $headerAllowHeaders = 'Accept, Authorization, Content-Type, Cache-Control, Content-Disposition';

  /** @var bool Repostas HTTP serão emitidas por padrão com este valor no header 'Access-Control-Allow-Credentials'. */
  protected static $headerAllowCredentials = false;

  /**
   * Aplica os header's CORS: Access-Control-Allow-Origin, Access-Control-Allow-Headers e Access-Control-Allow-Credentials.
   * @param string|null $headerAllowOrigin null para utilizar o valor padrão da classe.
   * @param string|null $headerAllowHeaders null para utilizar o valor padrão da classe.
   * @param bool|null $headerAllowCredentials null para utilizar o valor padrão da classe.
   * @return void
   */
  private static function applyCorsHeaders(string $headerAllowOrigin = null, string $headerAllowHeaders = null, ?bool $headerAllowCredentials = null)
  {
    if ($headerAllowOrigin === null) $headerAllowOrigin = self::$headerAllowOrigin;
    if ($headerAllowHeaders === null) $headerAllowHeaders = self::$headerAllowHeaders;
    if ($headerAllowCredentials === null) $headerAllowCredentials = self::$headerAllowCredentials;
    if ($headerAllowOrigin) header('Access-Control-Allow-Origin: ' . self::$headerAllowOrigin);
    if ($headerAllowHeaders) header('Access-Control-Allow-Headers: ' . self::$headerAllowHeaders);
    if ($headerAllowCredentials) header('Access-Control-Allow-Credentials: true');
  }

  /**
   * Define o valor padrão para o header 'Access-Control-Allow-Origin' nas respostas.
   * @param string|null $valor
   */
  public static function setAllowOrigin(?string $valor)
  {
    self::$headerAllowOrigin = $valor;
  }

  /**
   * Define o valor padrão para o header 'Access-Control-Allow-Headers' nas respostas.
   * @param string|null $valor
   */
  public static function setAllowHeaders(?string $valor)
  {
    self::$headerAllowHeaders = $valor;
  }

  /**
   * Define o valor padrão para o header 'Access-Control-Allow-Credentials' nas respostas.
   * @param bool $valor
   */
  public static function setAllowCredentials(bool $valor)
  {
    self::$headerAllowCredentials = $valor;
  }

  /**
   * Obtem o valor de um Header da requisicao.
   * @param string $header Nome do header desejado.
   * @return string|null Valor do header, null caso o header nao esteja presente na requisicao.
   */
  public static function getHeader(string $header): ?string
  {
    $headerUpper = mb_strtoupper($header, 'UTF-8');
    $headerAlt = str_replace('-', '_', $headerUpper);

    if (isset($_SERVER['HTTP_' . $headerUpper])) return trim($_SERVER['HTTP_' . $headerUpper]);
    elseif (isset($_SERVER[$headerUpper])) return trim($_SERVER[$headerUpper]);
    elseif (isset($_SERVER[$headerAlt])) return trim($_SERVER[$headerAlt]);
    elseif (isset($_SERVER[$header])) return trim($_SERVER[$header]);
    elseif (function_exists('apache_request_headers')) {
      $request_headers = apache_request_headers();
      $request_headers = array_combine(array_map('ucwords', array_keys($request_headers)), array_values($request_headers));
      if (isset($request_headers[$header])) return trim($request_headers[$header]);
      else return null;
    }
    return null;
  }

  /**
   * Informa o metodo da requisicao HTTP.
   * @return string 'GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'.
   */
  public static function getMethod(): string
  {
    return $_SERVER['REQUEST_METHOD'];
  }

  /**
   * Obtem os dados trafegados via FormData, util quando o metodo nao eh POST ou GET, pois o PHP nao trata dados de outros metodos.
   * @return array Dados parseados em array com indice = key do FormData.
   */
  private static function getFormData(): array
  {
    $dados = array();
    $raw_data = file_get_contents('php://input');
    $boundary = substr($raw_data, 0, strpos($raw_data, "\r\n"));
    if (!$boundary) return $dados;

    $parts = array_slice(explode($boundary, $raw_data), 1);

    foreach ($parts as $part) {
      if ($part == "--\r\n") break;

      $part = ltrim($part, "\r\n");
      list($raw_headers, $body) = explode("\r\n\r\n", $part, 2);

      $raw_headers = explode("\r\n", $raw_headers);
      $headers = array();
      foreach ($raw_headers as $header) {
        list($name, $value) = explode(':', $header);
        $headers[strtolower($name)] = ltrim($value, ' ');
      }

      if (isset($headers['content-disposition'])) {
        $filename = null;
        preg_match(
          '/^(.+); *name="([^"]+)"(; *filename="([^"]+)")?/',
          $headers['content-disposition'],
          $matches
        );
        list(, , $name) = $matches;
        isset($matches[4]) and $filename = $matches[4];

        switch ($name) {
          case 'userfile':
            file_put_contents($filename, $body);
            break;
          default:
            $dados[$name] = substr($body, 0, strlen($body) - 2);
            break;
        }
      }
    }
    return $dados;
  }

  /**
   * Saiba se o conteudo recebido na requisicao atual eh um JSON.
   * @return bool true: JSON, false: NOT JSON.
   */
  public static function isJson(): bool
  {
    $contentType = self::getHeader('Content-Type');
    if(!$contentType) return false;
    return substr($contentType, 0, 16) === 'application/json';
  }

  /**
   * Informa se o metodo HTTP da requisicao eh GET.
   * @return bool
   */
  public static function isGet(): bool
  {
    return self::getMethod() === 'GET';
  }

  /**
   * Informa se o metodo HTTP da requisicao eh POST.
   * @return bool
   */
  public static function isPost(): bool
  {
    return self::getMethod() === 'POST';
  }

  /**
   * Informa se o metodo HTTP da requisicao eh PUT.
   * @return bool
   */
  public static function isPut(): bool
  {
    return self::getMethod() === 'PUT';
  }

  /**
   * Informa se o metodo HTTP da requisicao eh DELETE.
   * @return bool
   */
  public static function isDelete(): bool
  {
    return self::getMethod() === 'DELETE';
  }

  /**
   * Informa se o metodo HTTP da requisicao eh PATCH.
   * @return bool
   */
  public static function isPatch(): bool
  {
    return self::getMethod() === 'PATCH';
  }

  /**
   * Emite um conteudo JSON como resposta HTTP 200. Esta funcao encerra o script.
   * @param mixed $content Conteudo do JSON.
   * @return never-return
   */
  public static function emitirJson($content)
  {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($content);
    die();
  }

  /**
   * Emite a resposta HTTP com o numero desejado para o codigo HTTP. Esta funcao encerra o script.
   * @param int $httpCode Codigo HTTP.
   * @return never-return
   */
  public static function emitirHttp(int $httpCode = 200)
  {
    if (function_exists('http_response_code')) http_response_code($httpCode);
    else header("HTTP/1.1 $httpCode", true, $httpCode);
    die();
  }

  /**
   * Emite uma resposta HTTP de erro com um JSON contendo as seguintes propriedades (em pt-br):
   * {http: Int, mensagem: String, erro: Int, dados: Any}.
   * @param int $httpCode Codigo HTTP da resposta.
   * @param string $message Mensagem de erro.
   * @param int $errorId Identificador do erro.
   * @param mixed|null $extra Dados extras de qualquer formato.
   * @return never-return
   */
  public static function erroJson(int $httpCode = 400, string $message = '', int $errorId = 1, $extra = '')
  {
    if (function_exists('http_response_code')) http_response_code($httpCode);
    else header("HTTP/1.1 $httpCode", true, $httpCode);
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
      'http' => $httpCode,
      'mensagem' => $message,
      'erro' => $errorId,
      'dados' => $extra,
    ));
    die();
  }

  /**
   * Confere se a requisicao eh do metodo HTTP GET, caso contrario mata o script e responde HTTP 405.
   * Metodo OPTIONS eh validado, encerrado o script com resposta HTTP positiva para funcionar com CORS.
   * @param bool $emitirErro Se a validacao for rejeitada encerra o script emitindo um erro JSON e HTTP 405. Ou entao retorna um boleano.
   * @param string|null $headerAllowOrigin null para utilizar o valor padrão da classe.
   * @param string|null $headerAllowHeaders null para utilizar o valor padrão da classe.
   * @param bool|null $headerAllowCredentials null para utilizar o valor padrão da classe.
   * @return bool Retorna o boleano se programou para nao emitir o erro.
   */
  public static function validarGet(bool $emitirErro = true, ?string $headerAllowOrigin = null, ?string $headerAllowHeaders = null, ?bool $headerAllowCredentials = null): bool
  {
    return self::validarMetodos(['GET'], $emitirErro, $headerAllowOrigin, $headerAllowHeaders, $headerAllowCredentials);
  }

  /**
   * Confere se a requisicao eh do metodo HTTP POST, caso contrario mata o script e responde HTTP 405.
   * Metodo OPTIONS eh validado, encerrado o script com resposta HTTP positiva para funcionar com CORS.
   * @param bool $emitirErro Se a validacao for rejeitada encerra o script emitindo um erro JSON e HTTP 405. Ou entao retorna um boleano.
   * @param string|null $headerAllowOrigin null para utilizar o valor padrão da classe.
   * @param string|null $headerAllowHeaders null para utilizar o valor padrão da classe.
   * @param bool|null $headerAllowCredentials null para utilizar o valor padrão da classe.
   * @return bool Retorna o boleano se programou para nao emitir o erro.
   */
  public static function validarPost(bool $emitirErro = true, ?string $headerAllowOrigin = null, ?string $headerAllowHeaders = null, ?bool $headerAllowCredentials = null): bool
  {
    return self::validarMetodos(['POST'], $emitirErro, $headerAllowOrigin, $headerAllowHeaders, $headerAllowCredentials);
  }

  /**
   * Confere se a requisicao eh do metodo HTTP especificado, caso contrario mata o script e responde HTTP 405.
   * Metodo OPTIONS eh validado, encerrado o script com resposta HTTP positiva para funcionar com CORS.
   * @param string $metodo Metodo HTTP. Ex: 'GET','POST','PUT','DELETE'.
   * @param bool $emitirErro Se a validacao for rejeitada encerra o script emitindo um erro JSON e HTTP 405. Ou entao retorna um boleano.
   * @param string|null $headerAllowOrigin null para utilizar o valor padrão da classe.
   * @param string|null $headerAllowHeaders null para utilizar o valor padrão da classe.
   * @param bool|null $headerAllowCredentials null para utilizar o valor padrão da classe.
   * @return bool Retorna o boleano se programou para nao emitir o erro.
   */
  public static function validarMetodo(string $metodo, bool $emitirErro = true, ?string $headerAllowOrigin = null, ?string $headerAllowHeaders = null, ?bool $headerAllowCredentials = null): bool
  {
    return self::validarMetodos([$metodo], $emitirErro, $headerAllowOrigin, $headerAllowHeaders, $headerAllowCredentials);
  }

  /**
   * Confere se a requisicao eh um dos metodos HTTP especificados, caso contrario mata o script e responde HTTP 405.
   * Metodo OPTIONS eh validado, encerrado o script com resposta HTTP positiva para funcionar com CORS.
   * @param string[] $metodos Metodos HTTP. Ex: ['GET','POST','PUT','DELETE'].
   * @param bool $emitirErro Se a validacao for rejeitada encerra o script emitindo um erro JSON e HTTP 405. Ou entao retorna um boleano.
   * @param string|null $headerAllowOrigin null para utilizar o valor padrão da classe.
   * @param string|null $headerAllowHeaders null para utilizar o valor padrão da classe.
   * @param bool|null $headerAllowCredentials null para utilizar o valor padrão da classe.
   * @return bool Retorna o boleano se programou para nao emitir o erro.
   */
  public static function validarMetodos(array $metodos = ['GET','POST','PUT','DELETE'], bool $emitirErro = true, ?string $headerAllowOrigin = null, ?string $headerAllowHeaders = null, ?bool $headerAllowCredentials = null): bool
  {
    if (gettype($metodos) !== 'array') self::erroJson(400, "validarMetodos() precisa receber um array de string no primeiro parametro");
    if (count($metodos) === 0) self::erroJson(400, "validarMetodos() precisa receber ao menos 1 metodo http no array do primeiro parametro");

    $metodos = array_map(function ($metodo) { return strtoupper($metodo); }, $metodos); //Passa para caixa alta.
    $metodosString = count($metodos) > 1 ? implode(", ", $metodos) : $metodos[0]; //Une em string separado por virgula.

    self::applyCorsHeaders($headerAllowOrigin, $headerAllowHeaders, $headerAllowCredentials);
    header("Access-Control-Allow-Methods: $metodosString, OPTIONS");

    $metodoAtual = self::getMethod();
    if ($metodoAtual === 'OPTIONS') self::emitirHttp();

    if (!in_array($metodoAtual, $metodos)) {
      if ($emitirErro) self::erroJson(405, 'Metodo HTTP desautorizado');
      else return false;
    }
    return true;
  }

  /**
   * Obtem um dado contido no corpo da requisicao, se nao existir devolve null.
   * Funciona com dados em FormData e JSON.
   * @param string $nome identificacao do parametro.
   * @return mixed|null
   */
  public static function obterParametro(string $nome)
  {
    if (self::isJson()) {
      $dados = json_decode(file_get_contents('php://input'), true);
      if (array_key_exists($nome, $dados)) return $dados[$nome];
      else return null;
    } else {
      switch (self::getMethod()) {
        case "DELETE":
        case "GET":
          if (!isset($_GET[$nome])) return null;
          else return $_GET[$nome];
        case "POST":
          if (!isset($_POST[$nome]) && !isset($_FILES[$nome])) return null;
          else return (isset($_FILES[$nome])) ? $_FILES[$nome] : $_POST[$nome];
        case "PUT":
        case "PATCH":
          $dados = self::getFormData();
          if (!isset($dados[$nome]) && !isset($_FILES[$nome])) return null;
          else return (isset($_FILES[$nome])) ? $_FILES[$nome] : $dados[$nome];
        default:
          return null;
      }
    }
  }

  /**
   * Obtem os dados contidos no corpo da requisicao de acordo com os nomes informados, aquele que nao existir sera atribuido o valor null.
   * Funciona com dados em FormData e JSON.
   * @param array $nomes identificacao dos parametros.
   * @return array ['parametro' => valor, ..]
   */
  public static function obterParametros(array $nomes): array
  {
    $dados = array();
    foreach ($nomes as $n) {
      $dados[$n] = self::obterParametro($n);
    }
    return $dados;
  }

  /**
   * Obtem um dado contido no corpo da requisicao, se nao existir dispara o erro JSON em HTTP 400.
   * Funciona com dados em FormData e JSON.
   * @param string $nome identificacao do parametro.
   * @param string $mensagemErro mensagem de erro quando o parametro nao existir.
   * @return mixed|null
   */
  public static function validarParametro(string $nome, string $mensagemErro = 'Faltam dados na requisicao')
  {
    if (self::isJson()) {
      $dados = json_decode(file_get_contents('php://input'), true);
      if (!array_key_exists($nome, $dados)) self::erroJson(400, $mensagemErro, 0, $nome);
      return $dados[$nome];
    } else {
      switch (self::getMethod()) {
        case "DELETE":
        case "GET":
          if (!isset($_GET[$nome])) self::erroJson(400, $mensagemErro, 0, $nome);
          return $_GET[$nome];
        case "POST":
          if (!isset($_POST[$nome]) && !isset($_FILES[$nome])) self::erroJson(400, $mensagemErro, 0, $nome);
          return (isset($_FILES[$nome])) ? $_FILES[$nome] : $_POST[$nome];
        case "PUT":
        case "PATCH":
          $dados = self::getFormData();
          if (!isset($dados[$nome]) && !isset($_FILES[$nome])) self::erroJson(400, $mensagemErro, 0, $nome);
          return (isset($_FILES[$nome])) ? $_FILES[$nome] : $dados[$nome];
        default:
          return null;
      }
    }
  }

  /**
   * Obtem os dados contidos no corpo da requisicao de acordo com os nomes informados, se um nao existir dispara o erro JSON em HTTP 400.
   * Funciona com dados em FormData e JSON.
   * @param array $nomes identificacao dos parametros.
   * @param string $mensagemErro mensagem de erro quando o parametro nao existir.
   * @return array ['parametro' => valor, ..]
   */
  public static function validarParametros(array $nomes, string $mensagemErro = 'Faltam dados na requisicao'): array
  {
    $dados = array();
    foreach ($nomes as $n) {
      $dados[$n] = self::validarParametro($n, $mensagemErro);
    }
    return $dados;
  }

  /**
   * Obtem os dados JSON do corpo da requisicao parseados em variavel PHP. Se nao houver JSON na requisicao retorna null.
   * @param bool $associativo O parse para PHP deve converter objetos para array associativo.
   * @return mixed|null Os dados do JSON parseados para variavel PHP
   */
  public static function obterJson(bool $associativo = false)
  {
    if (!self::isJson()) return null;
    return json_decode(file_get_contents('php://input'), $associativo);
  }

  /**
   * Obtem os dados JSON do corpo da requisicao parseados em variavel PHP. Se nao houver JSON na requisicao emite erro JSON em HTTP 400.
   * @param bool $associativo O parse para PHP deve converter objetos para array associativo.
   * @param string $mensagemErro Mensagem do erro quando nao ha JSON no corpo da requisicao.
   * @return mixed Os dados do JSON parseados para variavel PHP
   */
  public static function validarJson(bool $associativo = false, string $mensagemErro = 'Os dados nao estao no formato esperado')
  {
    if (!self::isJson()) self::erroJson(400, $mensagemErro, 0);
    return json_decode(file_get_contents('php://input'), $associativo);
  }

  /**
   * Obtem o IP Publico do cliente.
   * @return string|null
   */
  public static function obterIp(): ?string
  {
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) return $_SERVER['HTTP_X_REAL_IP'];
    elseif (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
    return null;
  }

  /**
   * Obtem o IP Publico do cliente.
   * @return string|null
   */
  public static function getIp(): ?string
  {
    return self::obterIp();
  }

  /**
   * Obtem o conteudo atual de um Cabecalho desejado que estiver presente na requisicao atual.
   * @param string $cabecalho Nome do cabecalho desejado.
   * @return string|null Valor do cabecalho, null caso o cabecalho nao esteja presente na requisicao.
   */
  public static function obterCabecalho(string $cabecalho): ?string
  {
    return self::getHeader($cabecalho);
  }

  /**
   * Emite os HEADERS que previnem que o navegador armazene cache da resposta HTTP.
   * @return void
   */
  public static function prevenirCache()
  {
    $ts = gmdate('D, d M Y H:i:s') . ' GMT';
    header("Expires: $ts");
    header("Last-Modified: $ts");
    header('Pragma: no-cache');
    header('Cache-Control: no-cache, no-store, must-revalidate');
  }

  /**
   * Alias da funcao prevenirCache().
   * @return void
   */
  public static function preventCache()
  {
    self::prevenirCache();
  }

  /**
   * Realiza um var_dump() com quebra de linha [e encerra o script].
   * @param mixed $var A variavel para debugar.
   * @param bool $matar_script Matar o script apos o var_dump.
   * @return void|never-return
   */
  public static function var_dump($var, bool $matar_script = true)
  {
    echo "<pre>\n";
    var_dump($var);
    echo "</pre>\n";
    if ($matar_script) die();
  }

  /**
   * Realiza o download de um arquivo a partir de uma string binaria. Esta funcao encerra o script.
   * @param string $binaryString
   * @param string $contentType
   * @param int $contentLength
   * @param string $filename
   * @param bool $inline
   * @return never-return
   */
  public static function downloadBinary(string $binaryString, string $contentType, int $contentLength, string $filename, bool $inline = true)
  {
    $filename = str_replace(' ', '_', trim($filename));
    $disposition = $inline ? 'inline' : 'attachment';
    if ($contentType) header('Content-Type: ' . $contentType);
    if ($contentLength) header('Content-Length: ' . $contentLength);
    if ($filename) header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
    echo $binaryString;
    die();
  }

  /**
   * Realiza o download de um arquivo que esta no armazenamento local. Esta funcao encerra o script.
   * @param string $filePath
   * @param string $contentType
   * @param string|null $filename
   * @param bool $inline
   * @return never-return
   */
  public static function downloadFile(string $filePath, string $contentType, ?string $filename = null, bool $inline = true)
  {
    if ($filename) {
      $extensao = pathinfo($filePath, PATHINFO_EXTENSION);
      $filename = $extensao ? pathinfo($filename, PATHINFO_FILENAME) . '.' . $extensao : pathinfo($filename, PATHINFO_FILENAME);
    } else {
      $filename = pathinfo($filePath, PATHINFO_BASENAME);
    }
    $filename = str_replace(' ', '_', trim($filename));

    $file_size = filesize($filePath);
    $disposition = $inline ? 'inline' : 'attachment';
    if ($contentType) header('Content-Type: ' . $contentType);
    if ($file_size) header('Content-Length: ' . $file_size);
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');

    flush();
    $resource = fopen($filePath, 'r');
    $binary_string = fread($resource, $file_size);
    fclose($resource);
    echo $binary_string;
    die();
  }

  /**
   * Executa uma instancia CURL e retorna varias informacoes de seu resultado.
   * @param resource $curl Instancia CURL criada por curl_init().
   * @return array|null Em caso de falha retorna false. Sucesso retorna array no formato ['code' => int, 'type' => string, 'size' => int, 'body' => mixed, 'json' => bool].
   */
  private static function execCurl($curl): ?array
  {
    $response = curl_exec($curl);
    if (curl_errno($curl)) return null;

    $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $responseSize = curl_getinfo($curl, CURLINFO_SIZE_DOWNLOAD);

    $isJson = substr($contentType, 0, 16) === 'application/json';
    $response = array(
      'code' => $httpStatus,
      'type' => $contentType,
      'size' => $responseSize,
      'body' => $isJson ? json_decode($response) : $response,
      'json' => $isJson
    );
    curl_close($curl);
    return $response;
  }

  /**
   * Realiza uma requisicao GET atraves do Curl.
   * @param string $url Endpoint da requisicao.
   * @param array $headers Um array com headers HTTP a definir, no formato array('Content-type: text/plain', 'Content-length: 100').
   * @param int $timeout Tempo limite em segundos para receber a resposta da requisicao.
   * @return array|null False em caso de erro. Array com colunas 'code','type','size','body','json'. Body em JSON ja eh entregue adaptado.
   */
  public static function curlGet(string $url, array $headers = [], int $timeout = 30): ?array
  {
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
    ));

    return self::execCurl($curl);
  }

  /**
   * Realiza uma requisicao POST.
   * @param string $url Endpoint da requisicao.
   * @param array|string|null $data Para formdata use array associativo com chave => valor. Para JSON use uma string.
   * @param array $headers Um array com headers HTTP a definir, no formato array('Content-type: text/plain', 'Content-length: 100').
   * @param int $timeout Tempo limite em segundos para receber a resposta da requisicao.
   * @return array|null False em caso de erro. Array com colunas 'code','type','size','body','json'. Body em JSON ja eh entregue adaptado.
   */
  public static function curlPost(string $url, $data = null, array $headers = [], int $timeout = 30): ?array
  {
    $headers[] = is_array($data) ? 'Content-Type: multipart/form-data' : 'Content-Type: application/json; charset=utf-8';
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT => $timeout,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_POSTFIELDS => $data,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
    ));

    return self::execCurl($curl);
  }

  /**
   * Grava um arquivo de texto, se o arquivo ja existir o texto sera incluido em uma nova linha.
   * @param string $texto Texto a incrementar, automaticamente concatena a data e hora do PHP no inicio do texto.
   * @param string $diretorio Diretorio absoluto onde o arquivo sera gravado, tanto faz se o ultimo caractere for '/'.
   * @param string $arquivo Nome do arquivo de texto.
   * @return string|null Retorna null em caso de sucesso, string para mensagem de erro.
   */
  public static function gravarLog(string $texto, string $diretorio, string $arquivo = 'log.txt'): ?string
  {
    $diretorio = rtrim($diretorio, '/');
    if (!file_exists($diretorio)) {
      if (!mkdir($diretorio, 0777, true)) return 'Não foi possível criar o diretório de log';
    }

    $fullPath = "$diretorio/$arquivo";
    if (file_exists($fullPath) && is_dir($fullPath)) return 'O log não pode ser gravado porque há um diretório com o mesmo nome do arquivo';

    $texto = '[' . date('Y-m-d H:i:s') . '] ' . trim($texto) . PHP_EOL;
    $sucesso = file_put_contents($fullPath, $texto);
    if (!$sucesso) return 'Não foi possível gravar o arquivo de log';
    return null;
  }

  /**
   * Expoe um outro diretorio para atuar como diretorio primario onde as paginas PHP serao expostas ao cliente.
   * @param string $mainDir Caminho do diretorio raiz dos arquivos que sera expostos.
   * @param string|null $logFile Nome do arquivo de log para registrar erros, coloque o caminho completo e nome do arquivo desejado.
   * @param string $noRouteMessage Se a rota nao existir, e nao houver um arquivo 404.php, esta mensagem eh retornada em JSON com erro 404.
   * @return void
   */
  public static function useRouter(string $mainDir, ?string $logFile = null, string $noRouteMessage = 'Endpoint desconhecido')
  {
    if ($logFile) {
      ini_set('log_errors', 1);
      ini_set('error_log', $logFile);
      ini_set('display_errors', 0);
      error_reporting(E_ALL);
    }

    $mainDir = rtrim($mainDir, '/');
    $url = $_SERVER['PATH_INFO'] ?? ($_SERVER['ORIG_PATH_INFO'] ?? null);
    $caminhoLocal = $url ? trim($url,'/') : null;

    if ($caminhoLocal && file_exists("$mainDir/$caminhoLocal.php")) {
      return require "$mainDir/$caminhoLocal.php";
    }
    elseif ($caminhoLocal && file_exists("$mainDir/$caminhoLocal/index.php")) {
      return require "$mainDir/$caminhoLocal/index.php";
    }
    elseif (!$caminhoLocal && file_exists("$mainDir/index.php")) {
      return require "$mainDir/index.php";
    }
    elseif (file_exists("$mainDir/404.php")) {
      return require "$mainDir/404.php";
    }
    else {
      self::erroJson(404, $noRouteMessage);
    }
  }
}
