<?php

namespace Eliaslazcano\Helpers;

use finfo;
use InvalidArgumentException;

class FileHelper
{
  /**
   * Descobre o tipo (MIME) de um arquivo analisando seu conteúdo binário. (Requer extensão finfo)
   * @param string $conteudoBinario Conteúdo do arquivo em string binaria.
   * @return string Se não detectado retorna 'application/octet-stream'.
   */
  public static function getMimeFromString(string $conteudoBinario): string
  {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    return $finfo->buffer($conteudoBinario) ?: 'application/octet-stream';
  }

  /**
   * Extrai o tipo (MIME) de um arquivo analisando seu conteúdo em formato base64.
   * @param string $base64 Conteúdo do arquivo em string formato base64.
   * @return string Se não detectado retorna 'application/octet-stream'.
   */
  public static function getMimeFromBase64(string $base64): string {
    if (preg_match('#^data(?://)?:(.*?);base64,#', $base64, $matches)) {
      $mime = $matches[1];
      if (substr($mime, 0, 2) === '//') $mime = substr($mime, 2);
      return $mime;
    }
    return 'application/octet-stream';
  }

  /**
   * Descobre o tipo (MIME) de um arquivo armazenado localmente.
   * @param string $path Caminho do arquivo alocado.
   * @return string Se não detectado retorna 'application/octet-stream'.
   */
  public static function getMimeFromFile(string $path): string
  {
    $str = self::fileToString($path);
    return self::getMimeFromString($str);
  }

  /**
   * Obtem um arquivo representado em string binaria atraves do seu conteudo em formato base64.
   * Aceita conteúdo com prefixo data URI (ex.: data:image/png;base64,...) ou apenas o base64 puro.
   * @param string $base64 String base64 que pode possuir (ou não) o mime + conteúdo em data URI.
   * @param string|null $mime Tipo do arquivo em convenção mime (retornado por referência quando presente).
   * @return string String binária do arquivo; retorna string vazia em caso de falha de decodificação.
   */
  public static function base64ToString(string $base64, ?string &$mime = ''): string
  {
    $mime = '';
    $data = trim($base64);

    if ($data === '') return '';

    // Se vier como data URI: data:<mime>(;...)?;base64,<dados>
    if (stripos($data, 'data:') === 0) {
      // Captura mime e conteúdo após a primeira vírgula
      if (preg_match('/^data:([^;,\s]+)?(?:;[^,]*)?,(.*)$/is', $data, $m)) {
        $mime = isset($m[1]) ? strtolower(trim($m[1])) : '';
        $data = $m[2];
      }
    }

    // Remover espaços e quebras, e tratar base64 url-safe
    $data = preg_replace('/\s+/', '', $data);
    // Converter URL-safe para padrão, se necessário
    if (strpos($data, '-') !== false || strpos($data, '_') !== false) {
      $data = strtr($data, '-_', '+/');
    }

    // Ajustar padding para múltiplo de 4
    $mod = strlen($data) % 4;
    if ($mod > 0) $data .= str_repeat('=', 4 - $mod);

    $decoded = base64_decode($data, true);
    if ($decoded === false) return '';

    return $decoded;
  }

  /**
   * Obtem um arquivo representado em formato base64 atraves do seu conteudo em string binaria.
   * @param string $conteudoBinario Conteúdo do arquivo em string binária.
   * @param string|null $mime Tipo do arquivo em convenção mime. Se não fornecer será detectado.
   * @return string String base64 que possui o mime + conteúdo (data URI).
   */
  public static function stringToBase64(string $conteudoBinario, ?string &$mime = null): string
  {
    $mime = $mime ? strtolower(trim($mime)) : self::getMimeFromString($conteudoBinario);
    return 'data:' . $mime . ';base64,' . base64_encode($conteudoBinario);
  }

  /**
   * Obtem o conteúdo do arquivo em string binária.
   * @param string $path Caminho do arquivo alocado.
   * @return string Arquivo representado em string binaria.
   */
  public static function fileToString(string $path): string
  {
    if ($path === '') throw new InvalidArgumentException('Caminho vazio fornecido.');
    if (!is_file($path) || !is_readable($path)) throw new InvalidArgumentException(sprintf('Arquivo não encontrado ou não legível: %s', $path));
    return file_get_contents($path) ?: '';
  }

  /**
   * Obtem o conteúdo do arquivo em string formatada em base64.
   * @param string $path Caminho do arquivo alocado.
   * @return string Arquivo representado em string formatada em base64.
   */
  public static function fileToBase64(string $path): string
  {
    $str = self::fileToString($path);
    return self::stringToBase64($str, self::getMimeFromString($str));
  }
}