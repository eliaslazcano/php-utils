<?php
/**
 * Simplifica modificacoes ou buscas em arrays.
 * @author Elias Lazcano Castro Neto
 * @version 2023-12-20
 * @since 5.3
 */
namespace Eliaslazcano\Helpers;

class ArrayHelper
{
  /**
   * Filtra os itens do array, retornando apenas aqueles que retornam true na funcao de filtro.
   * @param array $array O array que será filtrado.
   * @param callback $function Funcao de filtro, percorre o array fornecendo o item no primeiro parametro e espera um retorno boleano.
   * @param bool $resetIndexes Refaz os indices do array (com array_values). False = mantem os indices.
   * @return array
   */
  public static function filter($array, $function, $resetIndexes = false)
  {
    $filtered = array_filter($array, $function);
    return $resetIndexes ? array_values($filtered) : $filtered;
  }

  /**
   * Modifica o array (mapeia), retornando um novo array construido a partir dele.
   * @param array $array O array de origem.
   * @param callback $function Funcao que percorre o array, fornece o item no primeiro parametro e espera um retorno do novo valor substituto.
   * @return array O novo array.
   */
  public static function map($array, $function)
  {
    return array_map($function, $array);
  }

  /**
   * Procura um valor dentro do array que passe na filtragem da funcao callback.
   * @param array $array - O array de origem.
   * @param callback $function - Funcao de filtro, percorre o array fornecendo o item no primeiro parametro e espera um retorno boleano.
   * @return mixed|null - Valor encontrado dentro do array, null caso nao tenha encontrado.
   */
  public static function find($array, $function)
  {
    $filtered = self::filter($array, $function, true);
    return count($filtered) > 0 ? $filtered[0] : null;
  }

  /**
   * Retorna true ou false se algum elemento satisfaz o filtro
   * @param array $array - O array de origem.
   * @param callback $function - Funcao de filtro, percorre o array fornecendo o item no primeiro parametro e espera um retorno boleano.
   * @return bool - true quando algum elemento satisfaz o filtro, do contrario false.
   */
  public static function some($array, $function)
  {
    $filtered = self::filter($array, $function, true);
    return count($filtered) > 0;
  }

  /**
   * Remove de um array os itens vazios (de acordo com a funcao nativa empty().
   * @param array $array O array de origem.
   * @param bool $preserveIndex Mantem os indices do array.
   * @return array O novo array.
   */
  public static function removeEmptyItems($array, $preserveIndex = false) {
    $resultado = array_filter($array, function ($item) { return !empty($item); });
    return $preserveIndex ? $resultado : array_values($resultado);
  }

  /**
   * Remove de um array os itens com valor null.
   * @param array $array O array de origem.
   * @param bool $preserveIndex Mantem os indices do array.
   * @return array O novo array.
   */
  public static function removeNullItems($array, $preserveIndex = false) {
    $resultado = array_filter($array, function ($item) { return $item !== null; });
    return $preserveIndex ? $resultado : array_values($resultado);
  }

  /**
   * Remove de um array os itens com valores duplicados, use apenas para valores de tipo primitivo.
   * @param array<string|int|float> $array O array de origem.
   * @param bool $preserveIndex Mantem os indices do array.
   * @return array O novo array.
   */
  public static function removeDuplicateItems($array, $preserveIndex = false) {
    $resultado = array_unique($array);
    return $preserveIndex ? $resultado : array_values($resultado);
  }

  /**
   * Em um array multidimensional (duas dimensoes), todas as colunas de valor string serao convertidas para numerico, ou apenas as colunas especificadas.
   * @param array<array<string,string>> $array O array de origem.
   * @param array<string>|null $columns Nome das colunas que serao convertidas (index do segundo array dimensional).
   * @return array<array<string,float>> O novo array.
   */
  public static function columnsStringToNumber($array, $columns = null)
  {
    foreach ($array as $line => $lineValue) {
      foreach ($lineValue as $column => $columnValue) {
        if ($columns === null || (in_array($column, $columns))) {
          if (is_numeric($columnValue)) $array[$line][$column] = $columnValue + 0;
        }
      }
    }
    return $array;
  }

  /**
   * Em um array multidimensional (duas dimensoes), todas as colunas de valor string serao convertidas para boleano, ou apenas as colunas especificadas.
   * @param array<array<string,string>> $array O array de origem.
   * @param array<string>|null $columns Nome das colunas que serao convertidas (index do segundo array dimensional).
   * @return array<array<string,bool>> O novo array.
   */
  public static function columnsStringToBool($array, $columns = null)
  {
    foreach ($array as $line => $lineValue) {
      foreach ($lineValue as $column => $columnValue) {
        if ($columns === null || (in_array($column, $columns))) {
          $array[$line][$column] = boolval($columnValue);
        }
      }
    }
    return $array;
  }
}