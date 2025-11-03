<?php

namespace Eliaslazcano\Helpers;

use PDO;
use PDOException;
use PDOStatement;

abstract class DatabaseController
{
  protected $host;
  protected $usuario;
  protected $senha;
  protected $base_de_dados;
  protected $charset = 'utf8mb4';
  protected $collate = 'utf8mb4_general_ci';
  protected $timezone = '-03:00';
  protected $timeout = 20;

  /** @var PDO */
  private $conexao;

  /** @var bool O resultados das consultas virão forçadamente com nome da coluna em caixa baixa */
  public $forceColunasCaixaBaixa = false;

  public function __construct(?PDO $conn = null, bool $persistent = true)
  {
    if ($conn) $this->conexao = $conn;
    else {
      $dsn = "mysql:host=$this->host;dbname=$this->base_de_dados";
      if ($this->charset) $dsn .= ";charset=$this->charset";
      $options = array(PDO::ATTR_TIMEOUT => $this->timeout, PDO::ATTR_PERSISTENT => $persistent);
      try {
        $conn = new PDO($dsn, trim($this->usuario), trim($this->senha), $options);
        if ($this->timezone) $conn->exec("SET time_zone='$this->timezone';");
        $this->conexao = $conn;
        if ($this->charset && $this->collate) $conn->exec("SET NAMES $this->charset COLLATE $this->collate");
      } catch (PDOException $e) {
        $exceptionMessage = $e->getMessage();
        $this->aoFalhar($exceptionMessage, "Fracasso em abrir conexao com a base de dados. Exception->getMessage(): ($exceptionMessage)");
      }
    }
  }

  public function getConexao(): PDO
  {
    return $this->conexao;
  }

  /**
   * Comportamento em caso de falha.
   * @param string $mensagem Mensagem para o usuario final.
   * @param string|null $dadosLog Dados para eventualmente armazenar em um arquivo de log
   */
  protected function aoFalhar(string $mensagem, ?string $dadosLog = null)
  {
    trigger_error($mensagem, E_USER_ERROR);
  }

  /**
   * Adapta colunas para a tipagem adequada.
   * @param array $matriz
   * @param string[] $colunasNumericas
   * @param string[] $colunasBoleanas
   * @return array
   */
  private function tiparMatriz(array $matriz = array(), array $colunasNumericas = array(), array $colunasBoleanas = array()): array
  {
    $forceColunasCaixaBaixa = $this->forceColunasCaixaBaixa;
    if ($forceColunasCaixaBaixa) {
      $colunasNumericas = array_map('strtolower', $colunasNumericas);
      $colunasBoleanas = array_map('strtolower', $colunasBoleanas);
    }
    return array_map(function ($linha) use ($forceColunasCaixaBaixa, $colunasNumericas, $colunasBoleanas) {
      if ($forceColunasCaixaBaixa) $linha = array_change_key_case($linha, CASE_LOWER);
      foreach ($linha as $coluna => $valor) {
        if (in_array($coluna, $colunasNumericas)) {
          if (is_numeric($valor)) $linha[$coluna] = $valor + 0;
        }
        if (in_array($coluna, $colunasBoleanas)) {
          if ($valor !== null) $linha[$coluna] = (boolean) $valor;
        }
      }
      return $linha;
    }, $matriz);
  }

  /**
   * Obtem um statement do PDO, com "prepare" e "bind" realizados.
   * @param string $sql
   * @param array<string, mixed> $bindParams
   * @return PDOStatement
   */
  public function statementPreparado(string $sql, array $bindParams = array()): PDOStatement
  {
    $statement = $this->conexao->prepare($sql);
    if (!$statement) $this->aoFalhar(json_encode($this->conexao->errorInfo()), "SQL:$sql|PARAMS:" . json_encode($bindParams));
    if (!empty($bindParams)) {
      foreach ($bindParams as $key => $value) {
        if (is_int($value)) $statement->bindValue($key, $value, PDO::PARAM_INT);
        elseif (is_bool($value)) $statement->bindValue($key, $value, PDO::PARAM_BOOL);
        elseif (is_null($value)) $statement->bindValue($key, $value, PDO::PARAM_NULL);
        elseif (is_string($value)) $statement->bindValue($key, $value);
        else $statement->bindValue($key, strval($value));
      }
    }
    return $statement;
  }

  /**
   * Obtem um statement do PDO, com "bind" e "execute" realizados.
   * @param string $sql
   * @param array<string, mixed> $bindParams
   * @return PDOStatement
   */
  public function statementExecutado(string $sql, array $bindParams = array()): PDOStatement
  {
    $statement = $this->statementPreparado($sql, $bindParams);
    if (!$statement->execute()) $this->aoFalhar(json_encode($statement->errorInfo()), "SQL:$sql|PARAMS:" . json_encode($bindParams));
    return $statement;
  }

  /**
   * Executa uma instrução SQL para consultar dados, retornando o resultado em array[linha]['coluna'].
   * @param string $sql Query SQL.
   * @param array $bindParams Parametros da query que serao substituidos exemplo: [':id' => $id]
   * @param array $colunasNumericas O nome das colunas que devem vir tipadas em numero.
   * @param array $colunasBoleanas O nome das colunas que devem vir tipadas em boleano.
   * @param int $fetch_mode Modo FETCH do PDO
   * @return array<int, array<string, mixed>> array[linha]['coluna'].
   */
  public function query(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetch_mode = PDO::FETCH_ASSOC): array
  {
    $statement = $this->statementExecutado($sql, $bindParams);
    $linhas = $statement->fetchAll($fetch_mode);

    # Ajusta a tipagem dos dados, pois o PDO retorna tudo em String (em versões anteriores ao PHP 8).
    if ($this->forceColunasCaixaBaixa || !empty($colunasNumericas) || !empty($colunasBoleanas)) {
      $linhas = $this->tiparMatriz($linhas, $colunasNumericas, $colunasBoleanas);
    }

    return $linhas;
  }

  /**
   * Executa uma instrução SQL para consultar dados, retorna a primeira linha do resultado em array['coluna'].
   * @param string $sql Query SQL.
   * @param array $bindParams Parametros da query que serao substituidos exemplo: [':id' => $id]
   * @param array $colunasNumericas O nome das colunas que devem vir tipadas em numero.
   * @param array $colunasBoleanas O nome das colunas que devem vir tipadas em boleano.
   * @param int $fetch_mode Modo FETCH do PDO
   * @return array<string, mixed>|null null caso nao tenha nenhuma linha no resultado.
   */
  public function queryPrimeiraLinha(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetch_mode = PDO::FETCH_ASSOC): ?array
  {
    $linhas = $this->query($sql, $bindParams, $colunasNumericas, $colunasBoleanas, $fetch_mode);
    if (empty($linhas)) return null;
    else return $linhas[0];
  }

  public function select(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetch_mode = PDO::FETCH_ASSOC): array
  {
    return $this->query($sql, $bindParams, $colunasNumericas, $colunasBoleanas, $fetch_mode);
  }

  /**
   * Executa uma instrução SQL de inserção, tenta retornar o ID (chave primaria) do registro inserido.
   * @param string $sql Instrucao SQL.
   * @param array $bindParams Valores para insercao.
   * @return string Chave primaria.
   */
  public function insert(string $sql, array $bindParams = array()): string
  {
    if (strpos(strtoupper(substr($sql, 0, 11)), 'INSERT INTO') === false) $this->aoFalhar('Ausencia do comando INSERT', "SQL:$sql");
    $this->query($sql, $bindParams);
    return $this->conexao->lastInsertId();
  }

  /**
   * Executa uma instrução SQL de atualização (UPDATE), tenta retornar o número de linhas afetadas.
   * @param string $sql Instrução SQL com UPDATE.
   * @param array $bindParams Valores da instrução.
   * @return int Quantidade de linhas afetadas.
   */
  public function update(string $sql, array $bindParams = array()): int
  {
    $statement = $this->statementExecutado($sql, $bindParams);
    return $statement->rowCount();
  }

  /**
   * Obtem o ID (Chave primaria) do ultimo INSERT realizado com a conexao atual.
   * @return string
   */
  public function idUltimaInsercao(): string
  {
    return $this->conexao->lastInsertId();
  }

  /**
   * Obtem a data atual do banco em formato "AAAA-MM-DD".
   * @return string|null
   */
  public function dateAtual(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT CURRENT_DATE() AS date');
    return $resultado ? $resultado['date'] : null;
  }

  /**
   * Obtem a data-hora atual do banco em formato "AAAA-MM-DD hh:mm:ss".
   * @return string|null
   */
  public function datetimeAtual(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT CURRENT_TIMESTAMP() AS datetime');
    return $resultado ? $resultado['datetime'] : null;
  }

  /**
   * Obtem o timestamp atual do banco em formato "AAAA-MM-DD hh:mm:ss".
   * @return string|null
   */
  public function currentTimestamp(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT current_timestamp() AS datetime');
    return $resultado ? $resultado['datetime'] : null;
  }

  /**
   * Obtem o numero correspondente ao dia da semana, 1 = domingo ate 7 = sabado.
   * @return int|null
   */
  public function diaDaSemanaAtual(): ?int
  {
    $resultado = $this->queryPrimeiraLinha('SELECT dayofweek(current_date()) AS numero');
    return $resultado ? intval($resultado['numero']) : null;
  }

  /**
   * Retorna a lista de tabelas existentes no banco de dados selecionado.
   * @return array<int, string> Lista contendo os nomes das tabelas no banco de dados.
   */
  public function tabelas(): array
  {
    $statement = $this->statementExecutado('SHOW TABLES FROM ' . $this->base_de_dados);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * Exporta os dados de uma tabela do banco de dados para um arquivo CSV.
   * @param string $tabela Nome da tabela.
   * @param string[] $colunas Nome das colunas. Se quiser todas, use NULL ou um array vazio.
   * @param boolean $incluir_cabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   */
  public function exportarCsv_deTabela(string $tabela, array $colunas = array(), bool $incluir_cabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null)
  {
    $str_colunas = $colunas ? implode(', ', $colunas) : '*';
    $this->exportarCsv_deQuery("SELECT $str_colunas FROM $tabela", $incluir_cabecalho, $nome_do_arquivo, $diretorio);
  }

  /**
   * Exporta uma query para um arquivo CSV.
   * @param string $sql Query de consulta.
   * @param bool $incluir_cabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   */
  public function exportarCsv_deQuery(string $sql, bool $incluir_cabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null)
  {
    $resultado = $this->query($sql);
    self::exportarCsv_deArray($resultado, $incluir_cabecalho, $nome_do_arquivo, $diretorio);
  }

  /**
   * Exporta um array[][] para arquivo CSV
   * @param array<int, array<string, mixed>> $array
   * @param bool $incluir_cabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo Nome do arquivo que será sugerido para download ou gravado no disco.
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   * @param string $separador Caractere que separa as colunas.
   */
  public static function exportarCsv_deArray(array $array, bool $incluir_cabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null, string $separador = ';')
  {
    if (!$diretorio) {
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="'.$nome_do_arquivo.'"');
      header('Pragma: no-cache');
      header('Expires: 0');
    }
    $resource = fopen($diretorio ? ($diretorio.'/'.$nome_do_arquivo) : 'php://output', 'w');
    if ($incluir_cabecalho) {
      if (is_array($array[0])) fputcsv($resource, array_keys($array[0]), $separador);
      else fputcsv($resource, array_keys($array), $separador);
    }
    if (is_array($array[0])) {
      foreach ($array as $linha) fputcsv($resource, $linha, $separador);
    } else {
      fputcsv($resource, $array, $separador);
    }
    fclose($resource);
    if (!$diretorio) die();
  }
}