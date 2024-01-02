<?php
/**
 * Simplifica alguns trabalhos com string.
 * @author Elias Lazcano Castro Neto
 * @since 5.3
 */

namespace Eliaslazcano\Helpers;

use DateTime;
use DateInterval;

class StringHelper
{
  /**
   * Obtem o texto convertido em caixa alta
   * @param string $string
   * @param bool $trim
   * @param string $charset
   * @return string
   */
  public static function toUpperCase($string, $trim = true, $charset = 'UTF-8')
  {
    if ($trim) $string = trim($string);
    return mb_strtoupper($string, $charset);
  }

  /**
   * Obtem o texto convertido em caixa baixa
   * @param string $string
   * @param bool $trim
   * @param string $charset
   * @return string
   */
  public static function toLowerCase($string, $trim = true, $charset = 'UTF-8')
  {
    if ($trim) $string = trim($string);
    return mb_strtolower($string, $charset);
  }

  /**
   * Remove espacos sequencialmente repetidos da string.
   * @param $string
   * @return string
   */
  public static function removeDuplicatedSpaces($string)
  {
    return preg_replace('/\s+/', ' ', $string);
  }

  /**
   * Remove caracteres acentuados.
   * @param string $string A string original.
   * @param string|null $replacer String que ira substituir os caracteres acentuados, null = troca pelo mesmo caractere sem acento.
   * @return string
   */
  public static function removeAccents($string, $replacer = null)
  {
    if ($replacer === null) $replacer = explode(' ', "a A e E i I o O u U n N c C");
    return preg_replace(
      array("/(á|à|ã|â|ä)/", "/(Á|À|Ã|Â|Ä)/", "/(é|è|ê|ë)/", "/(É|È|Ê|Ë)/",
        "/(í|ì|î|ï)/", "/(Í|Ì|Î|Ï)/", "/(ó|ò|õ|ô|ö)/", "/(Ó|Ò|Õ|Ô|Ö)/",
        "/(ú|ù|û|ü)/", "/(Ú|Ù|Û|Ü)/", "/(ñ)/", "/(Ñ)/", "/(ç)/", "/(Ç)/"),
      $replacer,
      $string
    );
  }

  /**
   * Remove caracteres numericos da string.
   * @param $string
   * @param $separadosEmArray
   * @return string|string[]
   */
  public static function removeNumbers($string, $separadosEmArray = false)
  {
    $array = array();
    preg_match_all('/[^0-9]+/', $string, $array);
    return ($separadosEmArray) ? $array[0] : array_reduce($array[0], function ($carry, $item) {
      return $carry . $item;
    });
  }

  /**
   * Mantem na string apenas os numeros, letras (com acentos tambem) e espacos.
   * @param $string
   * @return string
   */
  public static function removeSimbolos($string)
  {
    return preg_replace("/[^a-zA-Z0-9\p{L}\s]/u", "", $string);
  }

  /**
   * Extraia os numeros presentes na string
   * @param string $string
   * @param bool $separadosEmArray
   * @return string|string[]
   */
  public static function extractNumbers($string, $separadosEmArray = false)
  {
    $array = array();
    preg_match_all('/[0-9]+/', $string, $array);
    return ($separadosEmArray) ? $array[0] : array_reduce($array[0], function ($carry, $item) {
      return $carry . $item;
    });
  }

  /**
   * Extraia as letras da string. Letras com acento sao convertidas para letras sem acento.
   * @param string $string
   * @param bool $separadosEmArray
   * @return string|string[]
   */
  public static function extractLetters($string, $separadosEmArray = false)
  {
    $string = self::removeAccents($string);
    $array = array();
    preg_match_all('/[a-zA-Z]+/', $string, $array);
    return ($separadosEmArray) ? $array[0] : array_reduce($array[0], function ($carry, $item) {
      return $carry . $item;
    });
  }

  /**
   * Confere se o texto inicia com os caracteres indicados
   * @param $fullString string Texto completo
   * @param $startString string Texto inicial
   * @param bool $caseSensitive Comparacao sensivel a diferenca de caixa alta/baixa
   * @return bool
   */
  public static function startsWith($fullString, $startString, $caseSensitive = true)
  {
    if (!$caseSensitive) {
      $fullString = self::toLowerCase($fullString, false);
      $startString = self::toLowerCase($startString, false);
    }
    $len = strlen($startString);
    return substr($fullString, 0, $len) === $startString;
  }

  /**
   * Confere se o texto encerra com os caracteres indicados
   * @param $fullString string Texto completo
   * @param $endString string Texto final
   * @param bool $caseSensitive Comparacao insensivel a diferenca de caixa alta/baixa
   * @return bool
   */
  public static function endsWith($fullString, $endString, $caseSensitive = true)
  {
    if (!$caseSensitive) {
      $fullString = self::toLowerCase($fullString, false);
      $endString = self::toLowerCase($endString, false);
    }
    $len = strlen($endString);
    if ($len == 0) return true;
    return (substr($fullString, -$len) === $endString);
  }

  /**
   * Formata um CPF ou CNPJ para a mascara ideal.
   * @param string $cpf_cnpj
   * @return string
   */
  public static function formatCpfCnpj($cpf_cnpj)
  {
    $digitos = self::extractNumbers($cpf_cnpj);
    $tamanho = strlen($digitos);
    if ($tamanho !== 11 && $tamanho !== 14) return $cpf_cnpj;
    $mascara = $tamanho === 11 ? '###.###.###-##' : '##.###.###/####-##';
    $mascara_size = strlen($mascara);
    $indice = -1;
    for ($i = 0; $i < $mascara_size; $i++) if ($mascara[$i] === '#') $mascara[$i] = $digitos[++$indice];
    return $mascara;
  }

  /**
   * Esconde uma parte do CPF/CNPJ
   * @param string $cpfCnpj
   * @param bool $formatado Retorna com a mascara.
   * @return string
   */
  public static function camuflarCpfCnpj($cpfCnpj, $formatado = true) {
    if (!$cpfCnpj) return $cpfCnpj;
    $x = self::extractNumbers($cpfCnpj);
    $tamanho = strlen($x);
    if ($tamanho !== 11 && $tamanho !== 14) return $cpfCnpj;
    $x = ($tamanho === 11) ?
      substr($x,0,3).'XXXXX'.substr($x,8):
      substr($x,0,2).'XXXXXX'.substr($x,8);
    if (!$formatado) return $x;

    $mascara = $tamanho === 11 ? '###.###.###-##' : '##.###.###/####-##';
    $mascara_size = strlen($mascara);
    $indice = -1;
    for ($i = 0; $i < $mascara_size; $i++) if ($mascara[$i] === '#') $mascara[$i] = $x[++$indice];
    return $mascara;
  }

  /**
   * Converte uma data/datetime para SQL ou BR, invertendo a posicao do DIA com o ANO.
   * @param string $data Detecta automaticamente o caractere separador. Aceita ANO com 2 ou 4 digitos. Pode conter horas.
   * @param string|null $novo_separador Novo caractere que ira separar DIA, MES e ANO. Use null para manter o atual.
   * @return string|false Data invertida. Em caso de falha retorna false.
   */
  public static function formatDate($data, $novo_separador = null)
  {
    if (strlen($data) < 8) return false; //Tamanho minimo, para datas abreviadas como AA-MM-DD

    //Tenta descobrir qual eh o caractere separador da data atualmente, pegando o primeiro caractere nao-numerico
    $separador = self::removeNumbers($data);
    if (strlen($separador) === 0) return false;
    $separador = $separador[0]; //pega o primeiro caractere

    $data_explodida = explode($separador, $data);
    if (count($data_explodida) !== 3) return false;
    if (!$novo_separador) $novo_separador = $separador;

    //Se contiver horas, devemos posiciona-la sempre no final da string
    if (strpos($data_explodida[2], ' ') !== false && strpos($data_explodida[2], ':') !== false) {
      $data_explodida[0] .= substr($data_explodida[2], strpos($data_explodida[2], ' '));
      $data_explodida[2] = substr($data_explodida[2], 0, strpos($data_explodida[2], ' '));
    }

    return $data_explodida[2] . $novo_separador . $data_explodida[1] . $novo_separador . $data_explodida[0];
  }

  /**
   * Corrige a ausencia de digito zero nas datas, exemplo 9 para 09.
   * @param string $data Data em formato 00/00/00
   * @return string|false Em caso de erro retorna false.
   */
  public static function fixDate($data)
  {
    if (strlen($data) < 6) return false;

    $separador = self::removeNumbers($data);
    if (strlen($separador) === 0) return false;
    $separador = $separador[0];

    $data_explodida = explode($separador, $data);
    if (count($data_explodida) !== 3) return false;
    return (strlen($data_explodida[0]) === 1 ? '0' . $data_explodida[0] : $data_explodida[0]) . $separador . (strlen($data_explodida[1]) === 1 ? '0' . $data_explodida[1] : $data_explodida[1]) . $separador . (strlen($data_explodida[2]) === 1 ? '0' . $data_explodida[2] : $data_explodida[2]);
  }

  /**
   * Formata um numero de telefone aplicando a mascara ideal de acordo com a quantidade de digitos.
   * @param string $num Somente digitos numericos serao mantidos.
   * @return string Numero de telefone formatado (com mascara).
   */
  public static function formatPhone($num)
  {
    $num = self::extractNumbers($num);
    $tamanho = strlen($num);
    if ($tamanho === 8) return substr_replace($num, '-', 4, 0);
    elseif ($tamanho === 9) return substr_replace($num, '-', 5, 0);
    elseif ($tamanho === 10) {
      $novo = substr_replace($num, '(', 0, 0);
      $novo = substr_replace($novo, ') ', 3, 0);
      return substr_replace($novo, '-', 9, 0);
    } elseif ($tamanho === 11) {
      $novo = substr_replace($num, '(', 0, 0);
      $novo = substr_replace($novo, ') ', 3, 0);
      return substr_replace($novo, '-', 10, 0);
    }
    return $num;
  }

  /**
   * Formata um numero de CEP, adicionando a mascara ideal na string.
   * @param string $cep Somente digitos numericos serao considerados no algoritmo, o resto sera removido.
   * @return string CEP com mascara.
   */
  public static function formatCep($cep)
  {
    $num = self::extractNumbers($cep);
    if (!$num || strlen($num) !== 8) return $cep;
    return substr($num, 0, 2) . '.' . substr($num, 2, 3) . '-' . substr($num, 5);
  }

  /**
   * Obtem o primeiro nome de uma pessoa ao fornecer o nome completo.
   * @param string $nome_completo Nome completo.
   * @return string Primeiro nome.
   */
  public static function primeiroNome($nome_completo)
  {
    $nomes = explode(' ', trim($nome_completo));
    return $nomes ? $nomes[0] : '';
  }

  /**
   * Obtem o nome do dia da semana em portugues.
   * @param int|null $dia Opcional. Numero do dia da semana, 0 para Domingo ate 6 para Sábado. null para o dia atual.
   * @return string
   */
  public static function diaDaSemana($dia = null)
  {
    $dia_da_semana = intval($dia !== null ? $dia : date('w'));
    switch ($dia_da_semana) {
      case 0:
        return 'Domingo';
      case 1:
        return 'Segunda-Feira';
      case 2:
        return 'Terça-Feira';
      case 3:
        return 'Quarta-Feira';
      case 4:
        return 'Quinta-Feira';
      case 5:
        return 'Sexta-Feira';
      case 6:
        return 'Sábado';
      default:
        return '';
    }
  }

  /**
   * Descobre o dia da semana a partir da data informada no parametro.
   * @param string $data Data no formato AAAA-MM-DD, se nao fornecer considera o dia de hoje.
   * @return int 0 = Domingo, 6 = Sexta-Feira
   */
  public static function obterDiaDaSemana($data = null) {
    if ($data === null) $date = date('Y-m-d');
    $timestamp = strtotime($data); // Converte a data para timestamp
    return (int)date('w', $timestamp); // Obtem o numero do dia da semana (0 para domingo, 6 para sexta-feira)
  }

  /**
   * Obtem o nome do mes de acordo com seu numero no calendario.
   * @param int|null $numero_do_mes Opcional. 1 para Janeiro ate 12 para Dezembro. null para o mes atual.
   * @return string
   */
  public static function mesDoAno($numero_do_mes = null)
  {
    $numero_do_mes = $numero_do_mes ? intval($numero_do_mes) : intval(date('m'));
    switch ($numero_do_mes) {
      case 1:
        return 'Janeiro';
      case 2:
        return 'Fevereiro';
      case 3:
        return 'Março';
      case 4:
        return 'Abril';
      case 5:
        return "Maio";
      case 6:
        return 'Junho';
      case 7:
        return 'Julho';
      case 8:
        return 'Agosto';
      case 9:
        return 'Setembro';
      case 10:
        return 'Outubro';
      case 11:
        return 'Novembro';
      case 12:
        return 'Dezembro';
      default:
        return '';
    }
  }

  /**
   * Converte uma string para UTF-8 mas SOMENTE se detectar que ela esta utilizando charset ISO-8859-1.
   * @param string $string A string original.
   * @return string A string nova.
   */
  public static function utf8Encode($string)
  {
    return mb_detect_encoding($string, array('UTF-8', 'ISO-8859-1')) !== 'UTF-8' ? utf8_encode($string) : $string;
  }

  /**
   * Converte uma string para ISO-8859-1 mas SOMENTE se detectar que ela esta utilizando charset UTF-8.
   * @param string $string A string original.
   * @return string A string nova.
   */
  public static function utf8Decode($string)
  {
    return mb_detect_encoding($string, array('UTF-8', 'ISO-8859-1')) !== 'UTF-8' ? $string : utf8_decode($string);
  }

  /**
   * @param string $search A substring que sera buscada dentro do texto.
   * @param string $replace O novo valor que ira substituir.
   * @param string $subject O texto recipiente.
   * @return string
   */
  public static function replaceLastOccurrence($search, $replace, $subject)
  {
    $pos = strrpos($subject, $search);
    if($pos !== false) $subject = substr_replace($subject, $replace, $pos, strlen($search));
    return $subject;
  }

  /**
   * Verifica se a data informada representa dia util (segunda a sexta-feira).
   * @param string|null $date - Data no formato AAAA-MM-DD. Se for null, assume o valor de date('Y-m-d') (data atual).
   * @return bool
   */
  public static function ehDiaUtil($date = null)
  {
    if ($date === null) $date = date('Y-m-d');
    $dayOfWeek = date('N', strtotime($date));
    return ($dayOfWeek >= 1 && $dayOfWeek <= 5);
  }

  /**
   * Retorna a data do próximo dia util a partir da data informada.
   * @param string|null $dataInicial - Data no formato AAAA-MM-DD. Se for null, assume o valor de date('Y-m-d') (data atual).
   * @return string|null - Resultado no formato AAAA-MM-DD. Em caso de erro retorna null.
   */
  public static function proximoDiaUtil($dataInicial = null)
  {
    if ($dataInicial === null) $dataInicial = date('Y-m-d');
    $nextDay = date('Y-m-d', strtotime($dataInicial . ' +1 day'));
    while (!self::ehDiaUtil($nextDay)) {
      $nextDay = date('Y-m-d', strtotime($nextDay . ' +1 day'));
    }
    return $nextDay ?: null;
  }

  /**
   * Obtenha uma data resultado da soma de dias corridos em cima de uma data inicial.
   * @param int $dias - Quantidade de dias corridos para somar na data.
   * @param string|null $dataInicial - Data no formato AAAA-MM-DD. Se for null, assume o valor de date('Y-m-d') (data atual).
   * @return string|null - Resultado no formato AAAA-MM-DD. Em caso de erro retorna null.
   */
  public static function somarDiasCorridos($dias, $dataInicial = null)
  {
    if ($dataInicial === null) $dataInicial = date('Y-m-d');
    try {
      $datetime = new DateTime($dataInicial);
      $datetime->add(new DateInterval('P'.$dias.'D'));
      return $datetime->format('Y-m-d');
    } catch (\Exception $e) {
      return null;
    }
  }
}
