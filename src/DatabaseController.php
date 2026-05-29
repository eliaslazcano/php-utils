<?php

namespace Eliaslazcano\Helpers;

use Exception;
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
  protected $persistent = false;

  /** @var PDO */
  private $conexao;

  /** @var bool O resultados das consultas virão forçadamente com nome da coluna em caixa baixa */
  public $forceColunasCaixaBaixa = false;

  /**
   * Cria uma conexão com um banco de dados retornando uma instância do PDO.
   * @param string $host - IP ou nome de Host para estabelecer a conexão de rede com o servidor.
   * @param string $database - Nome da base de dados.
   * @param string $usuario - Login de usuário.
   * @param string $senha - Senha de usuário.
   * @param int $timeout - Tempo limite para expirar a tentativa de iniciar a conexão.
   * @param bool $persistent - Mantém a conexão aberta após o término da requisição.
   * @param bool $exception - Cometer erros irá disparar exception.
   * @param string|null $timezone - Fuso horário usado na conexão, ex: '-03:00'.
   * @param string|null $charset - Conjunto de caracteres reconhecidos, ex: 'utf8mb4'.
   * @param string|null $collate - Regras para comparar e ordenar os caracteres do seu charset. Define se o sistema diferencia letras maiúsculas de minúsculas e como tratar acentos, ex: 'utf8mb4_general_ci'.
   * @return PDO
   */
  public static function criarConexao(string $host, string $database, string $usuario, string $senha, int $timeout = 20, bool $persistent = false, bool $exception = true, ?string $timezone = null, ?string $charset = null, ?string $collate = null): PDO
  {
    $dsn = "mysql:host=$host;dbname=$database";
    if ($charset) $dsn .= ";charset=$charset";
    $options = array(
      PDO::ATTR_TIMEOUT => $timeout,
      PDO::ATTR_PERSISTENT => $persistent,
      PDO::ATTR_ERRMODE => $exception ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT,
    );
    $conn = new PDO($dsn, trim($usuario), trim($senha), $options);
    if ($timezone) $conn->exec("SET time_zone='$timezone';");
    if ($charset && $collate) $conn->exec("SET NAMES $charset COLLATE $collate");
    return $conn;
  }

  /**
   * @param PDO|null $conn Conexão PDO, caso queira aproveitar uma existente.
   * @param bool|null $persistent Abre uma conexão persistente, evitando fechar quando a requisição acabar.
   * @throws Exception
   */
  public function __construct(?PDO $conn = null, ?bool $persistent = null)
  {
    if ($conn) $this->conexao = $conn;
    else {
      try {
        $this->conexao = static::criarConexao(
          $this->host,
          $this->base_de_dados,
          $this->usuario,
          $this->senha,
          $this->timeout,
          is_null($persistent) ? $this->persistent : $persistent,
          true,
          $this->timezone,
          $this->charset,
          $this->collate
        );
      } catch (PDOException $e) {
        $this->aoFalhar($e);
      }
    }
  }

  public function getConexao(): PDO
  {
    return $this->conexao;
  }

  public function getConn(): PDO
  {
    return $this->getConexao();
  }

  /**
   * Possibilita a classe herdeira personalizar o comportamento em caso de falhas.
   * @throws Exception
   */
  protected function aoFalhar(Exception $e)
  {
    throw $e;
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
   * @throws Exception
   */
  public function statementPreparado(string $sql, array $bindParams = array()): PDOStatement
  {
    try {
      $statement = $this->conexao->prepare($sql);
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
    } catch (PDOException $e) {
      $this->aoFalhar($e);
    }
  }

  /**
   * Obtem um statement do PDO, com "bind" e "execute" realizados.
   * @param string $sql
   * @param array<string, mixed> $bindParams
   * @return PDOStatement
   * @throws Exception
   */
  public function statementExecutado(string $sql, array $bindParams = array()): PDOStatement
  {
    $statement = $this->statementPreparado($sql, $bindParams);
    try {
      $statement->execute();
      return $statement;
    } catch (PDOException $e) {
      $this->aoFalhar($e);
    }
  }

  /**
   * Executa uma instrução SQL para consultar dados, retornando o resultado em array[linha]['coluna'].
   * @param string $sql Query SQL.
   * @param array $bindParams Parametros da query que serao substituidos exemplo: [':id' => $id]
   * @param array $colunasNumericas O nome das colunas que devem vir tipadas em numero.
   * @param array $colunasBoleanas O nome das colunas que devem vir tipadas em boleano.
   * @param int $fetchMode Modo FETCH do PDO
   * @return array<int, array<string, mixed>> array[linha]['coluna'].
   * @throws Exception
   */
  public function query(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetchMode = PDO::FETCH_ASSOC): array
  {
    $statement = $this->statementExecutado($sql, $bindParams);
    try {
      $linhas = $statement->fetchAll($fetchMode);

      # Ajusta a tipagem dos dados, pois o PDO retorna tudo em String (em versões anteriores ao PHP 8).
      if ($this->forceColunasCaixaBaixa || !empty($colunasNumericas) || !empty($colunasBoleanas)) {
        $linhas = $this->tiparMatriz($linhas, $colunasNumericas, $colunasBoleanas);
      }

      return $linhas;
    } catch (PDOException $e) {
      $this->aoFalhar($e);
    }
  }

  /**
   * Executa uma instrução SQL para consultar dados, retorna a primeira linha do resultado em array['coluna'].
   * @param string $sql Query SQL.
   * @param array $bindParams Parametros da query que serao substituidos exemplo: [':id' => $id]
   * @param array $colunasNumericas O nome das colunas que devem vir tipadas em numero.
   * @param array $colunasBoleanas O nome das colunas que devem vir tipadas em boleano.
   * @param int $fetchMode Modo FETCH do PDO
   * @return array<string, mixed>|null null caso nao tenha nenhuma linha no resultado.
   * @throws Exception
   */
  public function queryPrimeiraLinha(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetchMode = PDO::FETCH_ASSOC): ?array
  {
    $linhas = $this->query($sql, $bindParams, $colunasNumericas, $colunasBoleanas, $fetchMode);
    if (empty($linhas)) return null;
    else return $linhas[0];
  }

  /**
   * Atalho para função query.
   * @throws Exception
   */
  public function select(string $sql, array $bindParams = array(), array $colunasNumericas = array(), array $colunasBoleanas = array(), int $fetchMode = PDO::FETCH_ASSOC): array
  {
    return $this->query($sql, $bindParams, $colunasNumericas, $colunasBoleanas, $fetchMode);
  }

  /**
   * Executa uma instrução SQL de inserção, tenta retornar o ID (chave primaria) do registro inserido.
   * @param string $sql Instrucao SQL.
   * @param array $bindParams Valores para insercao.
   * @return string Chave primaria.
   * @throws Exception
   */
  public function insert(string $sql, array $bindParams = array()): string
  {
    $this->statementExecutado($sql, $bindParams);
    return $this->conexao->lastInsertId();
  }

  /**
   * Executa uma instrução SQL de atualização (UPDATE), tenta retornar o número de linhas afetadas.
   * @param string $sql Instrução SQL com UPDATE.
   * @param array $bindParams Valores da instrução.
   * @return int Quantidade de linhas afetadas.
   * @throws Exception
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
   * @throws Exception
   */
  public function dateAtual(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT CURRENT_DATE() AS date');
    return $resultado ? $resultado['date'] : null;
  }

  /**
   * Obtem a data-hora atual do banco em formato "AAAA-MM-DD hh:mm:ss".
   * @return string|null
   * @throws Exception
   */
  public function datetimeAtual(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT CURRENT_TIMESTAMP() AS datetime');
    return $resultado ? $resultado['datetime'] : null;
  }

  /**
   * Obtem o timestamp atual do banco em formato "AAAA-MM-DD hh:mm:ss".
   * @return string|null
   * @throws Exception
   */
  public function currentTimestamp(): ?string
  {
    $resultado = $this->queryPrimeiraLinha('SELECT current_timestamp() AS datetime');
    return $resultado ? $resultado['datetime'] : null;
  }

  /**
   * Obtem o numero correspondente ao dia da semana, 1 = domingo ate 7 = sabado.
   * @return int|null
   * @throws Exception
   */
  public function diaDaSemanaAtual(): ?int
  {
    $resultado = $this->queryPrimeiraLinha('SELECT dayofweek(current_date()) AS numero');
    return $resultado ? intval($resultado['numero']) : null;
  }

  /**
   * Retorna a lista de tabelas existentes no banco de dados selecionado.
   * @return array<int, string> Lista contendo os nomes das tabelas no banco de dados.
   * @throws Exception
   */
  public function tabelas(): array
  {
    $statement = $this->statementExecutado('SHOW TABLES FROM ' . $this->base_de_dados);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
  }

  /**
   * Exporta um array[][] para arquivo CSV
   * @param array<int, array<string, mixed>> $array
   * @param bool $incluirCabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo Nome do arquivo que será sugerido para download ou gravado no disco.
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   * @param string $separador Caractere que separa as colunas.
   */
  public static function exportarCsv_deArray(array $array, bool $incluirCabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null, string $separador = ';')
  {
    if (!$diretorio) {
      header('Content-Type: text/csv');
      header('Content-Disposition: attachment; filename="'.$nome_do_arquivo.'"');
      header('Pragma: no-cache');
      header('Expires: 0');
    }
    $resource = fopen($diretorio ? ($diretorio.'/'.$nome_do_arquivo) : 'php://output', 'w');
    if ($incluirCabecalho) {
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

  /**
   * Exporta uma query para um arquivo CSV.
   * @param string $sql Query de consulta.
   * @param bool $incluirCabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   * @throws Exception
   */
  public function exportarCsv_deQuery(string $sql, bool $incluirCabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null)
  {
    $resultado = $this->query($sql);
    self::exportarCsv_deArray($resultado, $incluirCabecalho, $nome_do_arquivo, $diretorio);
  }

  /**
   * Exporta os dados de uma tabela do banco de dados para um arquivo CSV.
   * @param string $tabela Nome da tabela.
   * @param string[] $colunas Nome das colunas. Se quiser todas, use NULL ou um array vazio.
   * @param boolean $incluirCabecalho O nome das colunas sera inserido na primeira linha do CSV.
   * @param string $nome_do_arquivo
   * @param string|null $diretorio Local para salvar o arquivo, NULL realiza o download no browser do cliente.
   * @throws Exception
   */
  public function exportarCsv_deTabela(string $tabela, array $colunas = array(), bool $incluirCabecalho = true, string $nome_do_arquivo = 'exportar.csv', ?string $diretorio = null)
  {
    $str_colunas = $colunas ? implode(', ', $colunas) : '*';
    $this->exportarCsv_deQuery("SELECT $str_colunas FROM $tabela", $incluirCabecalho, $nome_do_arquivo, $diretorio);
  }
}