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
}