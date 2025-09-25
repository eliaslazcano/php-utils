<?php
/**
 * Essa classe serve para validar IPs de clientes e identificar se são confiáveis.
 * A lista pode ser personalizada estendendo a classe e sobrescrevendo a constante LISTA_BRANCA.
 */

namespace Eliaslazcano\Helpers;

class GuardiaoIp
{
  /** @var array Lista de IPs autorizados a usar APIs da I2BR, não inclua IP de terceiros, há outro lugar pra isso */
  public const LISTA_BRANCA = [];

  /** Retorna o IP de quem fez a requisição HTTP */
  private static function getIp(): string
  {
    return $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
  }

  /**
   * Verifica se um IP pertence a uma faixa CIDR.
   * @param string $ip IP a verificar.
   * @param string $subnet IP base da sub-rede.
   * @param int $bits Máscara de rede.
   * @return bool true = dentro da faixa, false = fora.
   */
  private static function ipDentroCidr(string $ip, string $subnet, int $bits): bool
  {
    $ipNum = ip2long($ip);
    $subnetNum = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    return ($ipNum & $mask) === ($subnetNum & $mask);
  }

  /**
   * Verifica se um IP está dentro de um intervalo explícito.
   * @param string $ip IP a verificar.
   * @param string $inicio IP inicial do intervalo.
   * @param string $fim IP final do intervalo.
   * @return bool true = dentro do intervalo, false = fora.
   */
  private static function ipDentroIntervalo(string $ip, string $inicio, string $fim): bool
  {
    $ipNum = ip2long($ip);
    return ($ipNum >= ip2long($inicio) && $ipNum <= ip2long($fim));
  }

  /**
   * Verifica se um IP corresponde a um IP exato, uma faixa CIDR ou um intervalo explícito.
   * @param string $ip IP a verificar.
   * @param string $permitido Regra da lista branca (IP exato, CIDR ou intervalo).
   * @return bool true = corresponde, false = não corresponde.
   */
  private static function verificarCorrespondencia(string $ip, string $permitido): bool
  {
    // Se for um IP exato
    if ($ip === $permitido) return true;

    // Se for uma faixa CIDR (ex: 192.168.1.0/24)
    if (strpos($permitido, '/') !== false) {
      [$subnet, $bits] = explode('/', $permitido);
      if (self::ipDentroCidr($ip, $subnet, (int) $bits)) return true;
    }

    // Se for um intervalo de IPs explícito (ex: 10.0.0.1 - 10.0.0.100)
    elseif (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s*-\s*(\d+\.\d+\.\d+\.\d+)$/', $permitido, $matches)) {
      if (self::ipDentroIntervalo($ip, $matches[1], $matches[2])) return true;
    }

    return false;
  }

  /**
   * Retorna um boleano pra saber se um IP está na lista branca.
   * @param string|null $ip Endereço de IP pra ser testado, null = detectar e usar o endereço atual.
   * @param string[] $ipsConfiaveis Endereços IP permitidos.
   * @param bool $incluirListaBranca Os endereços IP confiaveis contidos em LISTA_BRANCA serão considerados.
   * @return bool true = aprovado, false = reprovado
   */
  public static function testarIp(string $ip = null, array $ipsConfiaveis = [], bool $incluirListaBranca = true): bool
  {
    $ip = $ip ?: self::getIp();
    if ($incluirListaBranca) $ipsConfiaveis += static::LISTA_BRANCA;
    foreach ($ipsConfiaveis as $i) {
      if (self::verificarCorrespondencia($ip, $i)) return true;
    }
    return false;
  }

  /**
   * Emite uma mensagem de erro em JSON caso o IP do cliente não esteja na lista branca.
   * @param string|null $ip Endereço de IP pra ser testado. null = detectar e usar o endereço atual.
   * @param string[] $ipsConfiaveis Endereços IP permitidos, null para usar a lista branca padrão.
   * @return void|no-return Caso o IP seja desautorizado emite um erro HTTP 401 com uma mensagem em JSON.
   */
  public static function validarIp(string $ip = null, array $ipsConfiaveis = [], bool $incluirListaBranca = true): void
  {
    if (!self::testarIp($ip, $ipsConfiaveis, $incluirListaBranca)) {
      HttpHelper::erroJson(401, "IP desautorizado", 1, ['ip' => self::getIp()]);
    }
  }
}