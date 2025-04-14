<?php
/**
 * Funções de ajuda básica.
 *
 * @author Elias Lazcano Castro Neto
 * @version 2024-11-13
 * @since 7.1
 */

namespace Eliaslazcano\Helpers;

use Exception;

class Utils
{
  /**
   * Gera um nome temporario (para ser utilizado para criar arquivo temporario) retornando o caminho absoluto até ele.
   * @param string $sufixo - Para ser incrementado no final do nome.
   * @param string $subdir - Subdiretório que fará parte do caminho até o arquivo.
   * @return string - Caminho absoluto até o arquivo temporário.
   * @throws Exception - Caso não seja possível criar o subdiretório.
   */
  public static function getPathTemporario(string $sufixo = '', string $subdir = ''): string
  {
    if ($subdir) $subdir = trim($subdir, DIRECTORY_SEPARATOR);
    $diretorio = __DIR__ . ($subdir ? DIRECTORY_SEPARATOR . $subdir : '');
    if (!is_dir($diretorio)) if (!mkdir($diretorio, 0777, true)) throw new Exception('Não foi possível criar o diretório temporario.');
    do {
      $nomeTemporario = uniqid() . $sufixo;
      $pathTemporario = $diretorio . DIRECTORY_SEPARATOR . $nomeTemporario;
    } while (file_exists($pathTemporario)); // Verifica se o arquivo já existe
    return $pathTemporario;
  }

  /**
   * Organiza todos os arquivos upados para um único array, ficando iguais mesmo se usar o mesmo nome de parametro.
   * @return array
   */
  public static function getAllUploadedFiles():array
  {
    $arquivos = [];
    foreach ($_FILES as $file) {
      if (is_string($file['tmp_name'])) $arquivos[] = $file;
      else {
        $qtd = count($file['tmp_name']);
        for ($i = 0; $i < $qtd; $i++) {
          $arquivos[] = [
            'name' => $file['name'][$i],
            'type' => $file['type'][$i],
            'tmp_name' => $file['tmp_name'][$i],
            'error' => $file['error'][$i],
            'size' => $file['size'][$i],
          ];
        }
      }
    }
    return $arquivos;
  }

  /**
   * Atraves do texto recebido, qualquer URL mencionada em seu conteúdo é convertida em um link clicável HTML.
   * @param string $texto Conteúdo original.
   * @return string Conteúdo adaptado.
   */
  public static function transformarUrlsEmLinks(string $texto): string
  {
    // Escapar HTML para evitar conflito com tags existentes
    $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

    // Expressão regular aprimorada para capturar URLs sem pontuação final
    $padrao = '/\b(https?:\/\/[^\s<>"\'\])]+[^\s<>"\'\]),.?!])/i';

    // Substituir URLs por links clicáveis
    return preg_replace_callback($padrao, function ($matches) {
      $url = $matches[1];
      return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>';
    }, $texto);
  }

  /**
   * Executa um bloco de código (callback), retornando um valor padrão se ele disparar uma exceção.
   * ```
   * $endereco = Utils::throwSuprimido(fn() => obterEndereco('Elias Neto'));
   * ```
   * @param callable $callback - Função callback que pode lançar exceções.
   * @param $retornoAoFalhar - Valor retornado em caso de exceção. (Padrão: null)
   * @return mixed Resultado do callback ou $retornoAoFalhar em caso de exceção.
   */
  public static function throwSuprimido(callable $callback, $retornoAoFalhar = null)
  {
    try {
      return $callback();
    } catch (Exception $e) {
      return $retornoAoFalhar;
    }
  }

  /**
   * Executa um bloco de código (callback), se ele disparar uma exceção será emitida a resposta HTTP 400 em JSON com a mensagem da falha.
   * ```
   * $endereco = Utils::throwSuprimido(fn() => obterEndereco('Elias Neto'));
   * ```
   * @param callable $callback - Função callback que pode lançar exceções.
   * @param string|null $msg - Mensagem de erro exibida no lugar da padrão (presente no JSON). Se não informar será usada a mensagem da Exception.
   * @return mixed Resultado do callback.
   */
  public static function throwErroJson(callable $callback, string $msg = null)
  {
    try {
      return $callback();
    } catch (Exception $e) {
      HttpHelper::erroJson(400, $msg ?: $e->getMessage());
    }
  }
}