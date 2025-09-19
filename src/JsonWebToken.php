<?php

namespace Eliaslazcano\Helpers;

use DomainException;
use Exception;
use function array_merge;
use function base64_decode;
use function base64_encode;
use function function_exists;
use function hash_hmac;
use function implode;
use function json_decode;
use function json_encode;
use function json_last_error;
use function str_repeat;
use function str_replace;
use function strlen;
use function strtr;
use const JSON_UNESCAPED_SLASHES;

class JsonWebToken
{
  /** @var array<string, string[]> */
  private static $supported_algs = [
    'HS256' => ['hash_hmac', 'SHA256'],
    'HS384' => ['hash_hmac', 'SHA384'],
    'HS512' => ['hash_hmac', 'SHA512'],
    'EdDSA' => ['sodium_crypto', 'EdDSA'],
  ];

  /**
   * Helper que dispara a mensagem de erro apropriada para uma falha ao tentar decodificar/criar um JSON.
   * @param int $errno O número do erro obtido por json_last_error()
   * @throws DomainException
   * @return void
   */
  private static function handleJsonError(int $errno): void
  {
    $messages = [
      JSON_ERROR_DEPTH => 'Profundidade máxima da pilha excedida',
      JSON_ERROR_STATE_MISMATCH => 'JSON inválido ou malformado',
      JSON_ERROR_CTRL_CHAR => 'Caractere de controle inesperado encontrado',
      JSON_ERROR_SYNTAX => 'Erro de sintaxe, JSON malformado',
      JSON_ERROR_UTF8 => 'Caracteres UTF-8 malformados', // PHP >= 5.3.3
    ];
    throw new DomainException($messages[$errno] ?? 'Erro JSON desconhecido: ' . $errno);
  }

  /**
   * Codifica um array PHP em uma string JSON.
   * @param array $input Um array PHP
   * @return string Representação JSON do array PHP
   * @throws DomainException O objeto fornecido não pôde ser codificado para um JSON válido
   */
  private static function jsonEncode(array $input): string
  {
    $json = json_encode($input, JSON_UNESCAPED_SLASHES);
    if ($errno = json_last_error()) self::handleJsonError($errno);
    elseif ($json === 'null') throw new DomainException('Resultado nulo com entrada não nula');

    if ($json === false) throw new DomainException('O objeto fornecido não pôde ser codificado para um JSON válido');
    return $json;
  }

  /**
   * Decodifica uma string JSON em um objeto PHP.
   * @param string $input String JSON
   * @param bool $associative Se true, o resultado será retornado em array associativo.
   * @return mixed A string JSON decodificada
   * @throws DomainException A string fornecida não é um JSON válido
   */
  private static function jsonDecode(string $input, bool $associative = true)
  {
    $obj = json_decode($input, $associative, 512, JSON_BIGINT_AS_STRING);
    if ($errno = json_last_error()) self::handleJsonError($errno);
    elseif ($obj === null && $input !== 'null') throw new DomainException('Resultado nulo com entrada não nula');
    return $obj;
  }

  /**
   * Converte uma string da codificação base64url (Base64 seguro para URL) para base64 padrão.
   * @param string $input Uma string codificada em Base64 com caracteres seguros para URL (-_ e sem preenchimento)
   * @return string Uma string codificada em Base64 com caracteres padrão (+/) e preenchimento (=), quando necessário.
   * @see https://www.rfc-editor.org/rfc/rfc4648
   */
  private static function convertBase64UrlToBase64(string $input): string
  {
    $remainder = strlen($input) % 4;
    if ($remainder) {
      $padlen = 4 - $remainder;
      $input .= str_repeat('=', $padlen);
    }
    return strtr($input, '-_', '+/');
  }

  /**
   * Codifica uma string em Base64 segura para URLs.
   * @param string $input A string que você quer codificar.
   * @return string A string codificada em Base64, adaptada para URLs.
   */
  private static function urlsafeB64Encode(string $input): string
  {
    return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
  }

  /**
   * Decodifica uma string que estiver em Base64 seguro para URL.
   * @param string $input Uma string codificada em Base64
   * @return string Uma string decodificada
   */
  private static function urlsafeB64Decode(string $input): string
  {
    return \base64_decode(self::convertBase64UrlToBase64($input));
  }

  /**
   * Assina uma string com uma chave e algoritmo informados.
   * @param string $msg A mensagem a ser assinada.
   * @param string $key A chave secreta.
   * @param string $alg Algoritmos suportados: 'EdDSA', 'HS256', 'HS384', 'HS512'.
   * @return string A assinatura/mensagem criptografada.
   * @throws DomainException Algoritmo não suportado ou chave inválida especificada.
   */
  private static function criptografarString(string $msg, string $key, string $alg): string
  {
    if (empty(static::$supported_algs[$alg])) throw new DomainException('Algoritmo não suportado');
    list($function, $algorithm) = static::$supported_algs[$alg];
    switch ($function) {
      case 'hash_hmac':
        return hash_hmac($algorithm, $msg, $key, true);
      case 'sodium_crypto':
        if (!function_exists('sodium_crypto_sign_detached')) throw new DomainException('libsodium não está disponível');
        try {
          // A última linha não vazia é usada como chave.
          $lines = array_filter(explode("\n", $key));
          $key = base64_decode((string) end($lines));
          if (strlen($key) === 0) throw new DomainException('A chave não pode ser uma string vazia');
          return sodium_crypto_sign_detached($msg, $key);
        } catch (Exception $e) {
          throw new DomainException($e->getMessage(), 0, $e);
        }
    }
    throw new DomainException('Algoritmo não suportado');
  }

  /**
   * Converte e assina um array PHP em uma string JWT.
   * @param array<string, mixed> $payload Array associativo chave-valor em PHP.
   * @param string|null $key A chave secreta; se não informar, será criada e retornada por referência.
   * @param string $alg Algoritmos suportados: 'HS256', 'HS384', 'HS512'.
   * @param string|null $keyId ID da chave (kid) opcional para embutir no header do token.
   * @param array<string, string> $head Array com elementos de cabeçalho para anexar.
   * @return string O JWT.
   * @uses jsonEncode
   * @uses urlsafeB64Encode
   */
  public static function gerarToken(array $payload, ?string &$key = null, string $alg = 'HS256', ?string $keyId = null, ?array $head = null): string
  {
    $header = ['typ' => 'JWT'];
    if (isset($head)) $header = array_merge($header, $head);
    $header['alg'] = $alg;
    if ($keyId !== null) $header['kid'] = $keyId;
    $segments = [];
    $segments[] = static::urlsafeB64Encode(static::jsonEncode($header));
    $segments[] = static::urlsafeB64Encode(static::jsonEncode($payload));
    $signing_input = implode('.', $segments);

    if (!$key) $key = md5(uniqid(mt_rand().mt_rand(), true));
    $signature = static::criptografarString($signing_input, $key, $alg);
    $segments[] = static::urlsafeB64Encode($signature);

    return implode('.', $segments);
  }

  public static function extrairPayload(string $token, bool $associative = true)
  {
    $tks = explode('.', $token);
    list(, $bodyb64,) = $tks;
    $payloadRaw = self::urlsafeB64Decode($bodyb64);
    $payload = self::jsonDecode($payloadRaw, $associative);
  }
}