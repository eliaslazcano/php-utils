<?php
/**
 * Oferece funcoes simples para criar Json Web Tokens, tambem valida-los e obter seus dados embutidos.
 * @author Elias Lazcano Castro Neto
 * @since 5.3
 */

namespace Eliaslazcano\Helpers;

class JwtHelper
{
  /** @var mixed */
  private $payload;

  /** @var string|null */
  private $secret;

  /**
   * @param array|null $payload
   * @param string|null $secret
   */
  public function __construct(?array $payload = null, ?string $secret = null)
  {
    $this->payload = ($payload ?: array());
    $this->secret = ($secret ?: self::randomSecret());
  }

  /**
   * @param array $payload
   * @return JwtHelper
   */
  public function setPayload(array $payload): JwtHelper
  {
    $this->payload = $payload;
    return $this;
  }

  /**
   * @param string $secret
   * @return JwtHelper
   */
  public function setSecret(string $secret): JwtHelper
  {
    $this->secret = $secret;
    return $this;
  }

  /**
   * Obtem a chave secreta
   * @return string|null
   */
  public function getSecret(): ?string
  {
    return $this->secret;
  }

  /**
   * Obtem a representacao string do Json Web Token.
   * @return string
   */
  public function getToken(): string
  {
    $result = self::tokenCreate($this->payload, $this->secret);
    return $result['token'];
  }

  /**
   * Obtem a representacao string do Json Web Token.
   * @return string
   */
  public function __toString()
  {
    return $this->getToken();
  }


  /**
   * Cria um token. Voce pode fornecer o secret ou deixar a funcao gerar um para voce.
   * @param array $payload Dados guardados no token. Evite dados sigilosos como senhas.
   * @param string|null $secret Um string secreta de sua escolha para futuramente validar a autenticidade do token. Se nao informado, sera gerada aleatoriamente.
   * @return array Array no formato: ['secret' => string, 'token' => string].
   */
  public static function tokenCreate(array $payload = array(), ?string $secret = null): array
  {
    $key = $secret ?: self::randomSecret();

    $header = array(
      'alg' => 'HS256', //algoritmo de criptografia
      'typ' => 'JWT'    //tipo de token
    );
    $header = json_encode($header);         //converte em string JSON
    $header = base64_encode($header);       //codifica em texto BASE64

    $payload = json_encode((object) $payload);  //converte em string JSON
    $payload = base64_encode($payload);         //codifica em texto BASE64

    $signature = hash_hmac('sha256', "$header.$payload", $key, true); //gera a assinatura
    $signature = base64_encode($signature); //codifica em texto BASE64

    $token = "$header.$payload.$signature";
    return array('secret' => $key, 'token' => $token);
  }

  /**
   * Valida um token a partir de uma string de segredo (secret), sabendo se ela foi a mesma utilizada na criacao do token.
   * @param string $token JWT em string.
   * @param string $secret A string que foi utilizada na criacao do JWT como secret.
   * @return bool Sucesso da validacao.
   */
  public static function tokenValidate(string $token, string $secret): bool
  {
    $part = explode('.', $token);
    $header = $part[0];
    $payload = $part[1];
    $signature = $part[2];

    $valid = hash_hmac('sha256', "$header.$payload", $secret, true);
    $valid = base64_encode($valid);

    return $signature === $valid;
  }

  /**
   * Obtem os dados que estao guardados no token.
   * @param string $token JWT em string.
   * @param bool $associative O parse para PHP deve converter objetos para array associativo.
   * @return mixed
   */
  public static function getPayload(string $token, bool $associative = false)
  {
    $part = explode('.', $token);
    $payload = $part[1];
    $payload = base64_decode($payload);
    return json_decode($payload, $associative);
  }

  /**
   * Gera uma chave aleatoria.
   * @return string
   */
  private static function randomSecret(): string
  {
    return md5(uniqid(mt_rand().mt_rand(), true), false);
  }

}
