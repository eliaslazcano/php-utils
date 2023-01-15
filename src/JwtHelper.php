<?php
/**
 * Oferece funcoes simples para criar Json Web Tokens, tambem valida-los e obter seus dados embutidos.
 * @author Elias Lazcano Castro Neto
 * @since 5.3
 */

namespace Eliaslazcano\Helpers;

class JwtHelper
{
  /**
   * Cria um token e retorna ele. Voce pode fornecer o secret ou deixar a funcao gerar um para voce.
   * @param string|null $secret Um string secreta de sua escolha para futuramente validar a autenticidade do token. Se nao informado, sera gerada aleatoriamente.
   * @param array|object $payload Dados guardados no token. Evite dados sigilosos como senhas.
   * @return array|string Se forneceu um secret o retorno sera a string do JWT. Se nao forneceu um secret entao o retorno sera array associativo com indices 'token' e 'secret'.
   */
  public static function tokenCreate($secret = null, $payload = [])
  {
    $key = $secret ?: md5(uniqid(mt_rand().mt_rand(), true), false);

    $header = [
      'alg' => 'HS256', //algoritmo de criptografia
      'typ' => 'JWT'    //tipo de token
    ];
    $header = json_encode($header);         //converte em string JSON
    $header = base64_encode($header);       //codifica em texto BASE64

    $payload = json_encode((object) $payload);  //converte em string JSON
    $payload = base64_encode($payload);         //codifica em texto BASE64

    $signature = hash_hmac('sha256', "$header.$payload", $key, true); //gera a assinatura
    $signature = base64_encode($signature); //codifica em texto BASE64

    $token = "$header.$payload.$signature";
    return $secret ? $token : ['secret' => $key, 'token' => $token];
  }

  /**
   * Valida um token a partir de uma string de segredo (secret), sabendo se ela foi a mesma utilizada na criacao do token.
   * @param string $token JWT em string.
   * @param string $secret A string que foi utilizada na criacao do JWT como secret.
   * @return bool Sucesso da validacao.
   */
  public static function tokenValidate($token, $secret)
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
  public static function getPayload($token, $associative = false)
  {
    $part = explode('.', $token);
    $payload = $part[1];
    $payload = base64_decode($payload);
    return json_decode($payload, $associative);
  }
}
