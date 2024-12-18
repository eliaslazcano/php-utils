<?php
/**
 * Utilizando a extensão GD essa classe oferece funções para converter imagens, redimensionar, limitar largura ou altura
 * cortar a imagem, pintar o fundo de outra cor e remover as bordas desnecessárias que só possuem a cor de fundo.
 *
 * @author Elias Lazcano Castro Neto
 * @version 2024-11-25
 * @since 7.1
 */

namespace Eliaslazcano\Helpers\Image;

use Exception;

class GdUtils
{
  /**
   * Emite um erro por Exception caso a extensão GD não esteja disponível no escopo atual.
   * @return void
   * @throws Exception
   */
  private static function validarExtensaoGd()
  {
    if (!extension_loaded('gd')) throw new Exception('A extensão GD é necessária para a manipulação de imagens.');
  }

  /**
   * Informa se o PHP é compatível com o formato WEBP.
   * @return bool
   */
  public static function compatibilidadeWebp(): bool
  {
    return function_exists('imagewebp');
  }

  /**
   * Calcula a quantidade de memória necessária para manipular a imagem com GD.
   * @param string $path - Caminho para o arquivo da imagem.
   * @return float - Memória estimada em bytes.
   * @throws Exception - Se o arquivo não for uma imagem válida.
   */
  public static function calcularMemoriaNecessariaEdicao(string $path): float
  {
    if (!file_exists($path)) { // Verifica se o arquivo existe
      throw new Exception("Arquivo não encontrado: $path");
    }

    $aImageInfo = getimagesize($path); // Obtém informações da imagem
    if ($aImageInfo === false) {
      throw new Exception("Não foi possível obter informações da imagem.");
    }

    // Largura e altura
    $largura = $aImageInfo[0];
    $altura = $aImageInfo[1];

    // Verifica e define valores padrão para bits e canais
    $bits = $aImageInfo['bits'] ?? 8; // Padrão: 8 bits por canal
    $canais = $aImageInfo['channels'] ?? 3; // Padrão: 3 canais (RGB)

    // Calcula a memória necessária
    $memoria = ($largura * $altura * $bits * $canais / 8) + pow(2, 16);

    // Aplica o fator de segurança (1.65)
    return round($memoria * 1.65);
  }

  //Conversões

  /**
   * Cria uma instancia de imagem GD a partir do arquivo.
   * @param string $path - Caminho até o arquivo.
   * @return resource - Imagem GD. Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function fromFile(string $path)
  {
    self::validarExtensaoGd();
    if (!file_exists($path)) throw new Exception('O arquivo não existe.');
    $imagemInfo = getimagesize($path);
    if (!$imagemInfo) throw new Exception('O arquivo não é uma imagem válida.');
    switch ($imagemInfo['mime']) {
      case 'image/jpeg':
        $resource = imagecreatefromjpeg($path);
        break;
      case 'image/png':
        $resource = imagecreatefrompng($path);
        imagealphablending($resource, false);
        imagesavealpha($resource, true);
        break;
      case 'image/gif':
        $resource = imagecreatefromgif($path);
        imagealphablending($resource, false);
        imagesavealpha($resource, true);
        break;
      case 'image/webp':
        $resource = imagecreatefromwebp($path);
        imagealphablending($resource, false);
        imagesavealpha($resource, true);
        break;
      default:
        throw new Exception('Formato de imagem não suportado.');
    }
    if (!$resource) throw new Exception('Não foi possível inspecionar a imagem.');
    return $resource;
  }

  /**
   * Cria uma instancia de imagem GD a partir da string binária.
   * @param string $conteudo - String binária da imagem.
   * @return resource - Imagem GD. Use imagedestroy() para liberar memória.
   * @throws Exception
   */
  public static function fromString(string $conteudo)
  {
    self::validarExtensaoGd();
    $imagemInfo = getimagesizefromstring($conteudo);
    if (!$imagemInfo) throw new Exception('O conteúdo não é uma imagem válida.');
    $mimeType = $imagemInfo['mime'];
    switch ($mimeType) {
      case 'image/jpeg':
        $imagemGd = imagecreatefromstring($conteudo);
        break;
      case 'image/png':
      case 'image/gif':
      case 'image/webp':
        $imagemGd = imagecreatefromstring($conteudo);
        imagealphablending($imagemGd, false);
        imagesavealpha($imagemGd, true);
        break;
      default:
        throw new Exception('Formato de imagem não suportado.');
    }
    if (!$imagemGd) throw new Exception('Não foi possível inspecionar a imagem.');
    return $imagemGd;
  }

  /**
   * Gera a imagem em instancia GD a partir da sua string codificada em base64.
   * O prefixo 'data:image/...;base64,' é opcional.
   * @param string $imagem64 - Imagem em codificação base64, não faz diferença ter o prefixo 'data:image/...;base64,'.
   * @return resource - Imagem em instancia GD.
   * @throws Exception
   */
  public static function fromBase64(string $imagem64)
  {
    // Remover o prefixo 'data:image/...;base64,' se presente
    if (strpos($imagem64, 'base64,') !== false) $imagem64 = explode(',', $imagem64)[1];
    $imagemStr = base64_decode($imagem64);
    if (!$imagemStr) throw new Exception('Erro ao decodificar a imagem.');
    return self::fromString($imagemStr);
  }

  /**
   * Converte uma instância de resource (imagem GD) para um arquivo binário que é gravado no caminho informado.
   * @param $imagemGd - Imagem em formato GD.
   * @param string $destino - [caminho] + nome do arquivo.
   * @param int $type - Constantes como IMAGETYPE_JPEG, IMAGETYPE_PNG ou IMAGETYPE_WEBP.
   * @param int $quality - Qualidade entre 0 a 100.
   * @return void
   * @throws Exception - Dispara se o formato da saída for inválido ou se o arquivo não puder ser gravado localmente.
   */
  public static function toFile($imagemGd, string $destino, int $type = IMAGETYPE_JPEG, int $quality = 90)
  {
    $saidasSuportadas = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
    if (self::compatibilidadeWebp()) $saidasSuportadas[] = IMAGETYPE_WEBP;
    if (!in_array($type, $saidasSuportadas)) throw new Exception('Formato de saída não suportado.');
    $sucesso = false;
    if ($type === IMAGETYPE_JPEG) {
      $imagemComFundoBranco = self::pintarPixelsTransparentes($imagemGd);
      $sucesso = imagejpeg($imagemComFundoBranco, $destino, $quality); //Salva a imagem
      imagedestroy($imagemComFundoBranco);
    }
    elseif ($type === IMAGETYPE_PNG) $sucesso = imagepng($imagemGd, $destino, self::converterEscala($quality));
    elseif ($type === IMAGETYPE_GIF) $sucesso = imagegif($imagemGd, $destino);
    elseif (self::compatibilidadeWebp() && $type === IMAGETYPE_WEBP) $sucesso = imagewebp($imagemGd, $destino, $quality);
    if (!$sucesso) throw new Exception('Não foi possível gravar a imagem localmente.');
  }

  /**
   * Gera um arquivo temporário da imagem informada, retornando o caminho absoluto e outras informações.
   * O arquivo é apagado sozinho quando o script php chegar ao fim ou se fizer fclose() no indice 'file'.
   * @param $imagemGd - Imagem em formato GD.
   * @param int $type - Constantes como IMAGETYPE_JPEG, IMAGETYPE_PNG ou IMAGETYPE_WEBP.
   * @param int $quality - Qualidade entre 0 a 100.
   * @return array ['uri' => String, 'size' => Int, 'file' => resource]
   * @throws Exception
   */
  public static function toFileTmp($imagemGd, int $type = IMAGETYPE_JPEG, int $quality = 90): array
  {
    $imagemStr = self::toString($imagemGd, $type, $quality);
    $tmpFile = tmpfile();
    fwrite($tmpFile, $imagemStr);
    fseek($tmpFile, 0);
    $metadata = stream_get_meta_data($tmpFile);
    return ['file' => $tmpFile, 'uri' => $metadata['uri'], 'size' => filesize($metadata['uri'])];
  }

  /**
   * Converte uma instância de resource (imagem GD) para um resultado em string binária do formato desejado.
   * @param resource $imagemGd - Imagem em formato GD.
   * @param int|null $type - Constantes como IMAGETYPE_JPEG, IMAGETYPE_PNG ou IMAGETYPE_WEBP. Não informar vai tentar usar WEBP e depois JPEG.
   * @param int $quality - Qualidade entre 0 a 100.
   * @return string - String binaria.
   * @throws Exception - Dispara se o formato da saída for inválido.
   */
  public static function toString($imagemGd, ?int $type = null, int $quality = 90): string
  {
    $saidasSuportadas = [IMAGETYPE_JPEG, IMAGETYPE_PNG];
    if (self::compatibilidadeWebp()) $saidasSuportadas[] = IMAGETYPE_WEBP;
    if ($type === null) $type = self::compatibilidadeWebp() ? IMAGETYPE_WEBP : IMAGETYPE_JPEG;
    if (!in_array($type, $saidasSuportadas)) throw new Exception('Formato de saída não suportado.');
    ob_start();
    if ($type === IMAGETYPE_JPEG) {
      $imagemComFundoBranco = self::pintarPixelsTransparentes($imagemGd);
      imagejpeg($imagemComFundoBranco, null, $quality); //Salva a imagem
      imagedestroy($imagemComFundoBranco);
    }
    elseif ($type === IMAGETYPE_PNG) imagepng($imagemGd, null, self::converterEscala($quality));
    elseif ($type === IMAGETYPE_GIF) imagegif($imagemGd);
    elseif (self::compatibilidadeWebp() && $type === IMAGETYPE_WEBP) imagewebp($imagemGd, null, $quality);
    $saida = ob_get_clean();
    if ($saida === false) throw new Exception('O output buffer não está ativado.');
    return $saida;
  }

  /**
   * A partir da string binária da imagem retorna a sua versão codificada por Base64.
   * @param string $conteudo - Conteúdo do arquivo em string binária.
   * @param string|null $mimeType - Se não informar o algoritmo irá detectar sozinho.
   * @return string - Representação da imagem em codificação base64.
   * @throws Exception - Se não for uma imagem ou se o tipo não puder ser detectado.
   */
  public static function toBase64(string $conteudo, ?string $mimeType = null): string
  {
    if (!$mimeType) {
      $imagemInfo = getimagesizefromstring($conteudo);
      if (!$imagemInfo) throw new Exception('O arquivo não é uma imagem válida.');
      $mimeType = $imagemInfo['mime'];
    }
    if (!$mimeType) throw new Exception('Não foi possível identificar o tipo de imagem.');
    return "data:$mimeType;base64,".base64_encode($conteudo);
  }

  /**
   * Um alias para a função fromFile().
   * @throws Exception
   */
  public static function toResource(string $path)
  {
    return self::fromFile($path);
  }

  //Redimensionamento

  /**
   * Redimensiona a imagem para o limite de largura e altura estabelecido, mantendo a proporção.
   * @param resource $imagemGd - Imagem em formato GD.
   * @param int $maxWidth - Limite de largura em pixels.
   * @param int $maxHeight - Limite de altura em pixels.
   * @param bool $permitirAmpliar - Aumenta a imagem caso ela seja menor que o limite estabelecido.
   * @return resource - Imagem em formato GD.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function resize($imagemGd, int $maxWidth, int $maxHeight, bool $permitirAmpliar = false)
  {
    $originalWidth = imagesx($imagemGd);
    $originalHeight = imagesy($imagemGd);
    if (!$originalWidth || !$originalHeight) throw new Exception('Não foi possível identificar a resolução da imagem.');
    $reduzir = $originalWidth > $maxWidth || $originalHeight > $maxHeight;
    $ampliar = $permitirAmpliar && ($originalWidth < $maxWidth || $originalHeight < $maxHeight);
    if ($reduzir || $ampliar) {
      $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
      $newWidth = (int)($originalWidth * $ratio);
      $newHeight = (int)($originalHeight * $ratio);

      // Criar uma nova imagem com as dimensões redimensionadas
      $imagemNova = imagecreatetruecolor($newWidth, $newHeight);
      if (!$imagemNova) throw new Exception('Não foi possível redimensionar a imagem.');

      //Preserva o fundo transparente
      imagealphablending($imagemNova, false);
      imagesavealpha($imagemNova, true);

      imagecopyresampled($imagemNova, $imagemGd, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
      imagedestroy($imagemGd);
    } else {
      $imagemNova = $imagemGd; //A imagem não precisar ser redimensionada
    }
    return $imagemNova;
  }

  /**
   * Redimensiona a imagem para o limite de largura e altura estabelecido, mantendo a proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $path - Caminho até o arquivo.
   * @param int $maxLargura - Limite de largura em pixels.
   * @param int $maxAltura - Limite de altura em pixels.
   * @param bool $permitirAmpliar - Aumenta a imagem caso ela seja menor que o limite estabelecido.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function resizeArquivo(string $path, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    $imagemGd = self::fromFile($path);
    return self::resize($imagemGd, $maxLargura, $maxAltura, $permitirAmpliar);
  }

  /**
   * Redimensiona a imagem para o limite de largura e altura estabelecido, mantendo a proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $conteudo - Conteúdo do arquivo em string binária.
   * @param int $maxLargura - Limite de largura em pixels.
   * @param int $maxAltura - Limite de altura em pixels.
   * @param bool $permitirAmpliar - Aumenta a imagem caso ela seja menor que o limite estabelecido.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp().
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function resizeString(string $conteudo, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    $imagemGd = self::fromString($conteudo);
    return self::resize($imagemGd, $maxLargura, $maxAltura, $permitirAmpliar);
  }

  //Recorte

  /**
   * Redimensiona a imagem para o tamanho desejado, não preserva a proporção então realiza recorte nas laterais mantendo
   * a imagem centralizada.
   * @param resource $imagemGd - Imagem no formato GD.
   * @param int $width - Largura desejada.
   * @param int $height - Altura desejada.
   * @return resource - Imagem no formato GD.
   * @throws Exception
   */
  public static function crop($imagemGd, int $width, int $height)
  {
    $originalWidth = imagesx($imagemGd);
    $originalHeight = imagesy($imagemGd);
    if (!$originalWidth || !$originalHeight) throw new Exception('Não foi possível identificar a resolução da imagem.');

    $originalAspect = $originalWidth / $originalHeight;
    $targetAspect = $width / $height;

    if ($originalWidth !== $width || $originalHeight !== $height) {
      if ($originalAspect > $targetAspect) {
        // A imagem original é mais larga, então corta as laterais
        $newHeight = $height;
        $newWidth = (int)($originalWidth * ($height / $originalHeight));
      } else {
        // A imagem original é mais alta, então corta a parte de cima e de baixo
        $newWidth = $width;
        $newHeight = (int)($originalHeight * ($width / $originalWidth));
      }

      // Redimensionar a imagem
      $imagemNova = imagecreatetruecolor($newWidth, $newHeight);
      if (!$imagemNova) throw new Exception('Não foi possível redimensionar a imagem.');

      //Preserva a transparencia (bloco de codigo opcional, nao interfere no redimensionamento)
      imagealphablending($imagemNova, false);
      imagesavealpha($imagemNova, true);
      imagecopyresampled($imagemNova, $imagemGd, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

      // Agora, fazer o corte para ajustar exatamente ao tamanho desejado
      $cropX = max(0, ($newWidth - $width) / 2);
      $cropY = max(0, ($newHeight - $height) / 2);

      // Criar a imagem final com o corte
      $imagemNova2 = imagecreatetruecolor($width, $height);
      //Preserva a transparencia (bloco de codigo opcional, nao interfere no redimensionamento)
      imagealphablending($imagemNova2, false);
      imagesavealpha($imagemNova2, true);

      if (!$imagemNova2) throw new Exception('Não foi possível criar a imagem final com corte.');
      imagecopy($imagemNova2, $imagemNova, 0, 0, (int)$cropX, (int)$cropY, $width, $height);
      imagedestroy($imagemNova);
    } else {
      $imagemNova2 = $imagemGd; // A imagem não precisa ser redimensionada
    }

    return $imagemNova2;
  }
  
  /**
   * Redimensiona a imagem para o tamanho desejado, não preserva a proporção então realiza recorte nas laterais mantendo
   * a imagem centralizada.
   * @param string $path - Caminho até o arquivo.
   * @param int $width - Limite de largura em pixels.
   * @param int $height - Limite de altura em pixels.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function cropArquivo(string $path, int $width, int $height)
  {
    $imagemGd = self::fromFile($path);
    if (!$imagemGd) throw new Exception('Não foi possível inspecionar a imagem.');
    return self::crop($imagemGd, $width, $height);
  }

  /**
   * Redimensiona a imagem para o tamanho desejado, não preserva a proporção então realiza recorte nas laterais mantendo
   * a imagem centralizada.
   * @param string $conteudo - Conteúdo do arquivo em string binária.
   * @param int $width - Limite de largura em pixels.
   * @param int $height - Limite de altura em pixels.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function cropString(string $conteudo, int $width, int $height)
  {
    $imagemGd = self::fromString($conteudo);
    return self::crop($imagemGd, $width, $height);
  }

  //Outros

  /**
   * Um alias para a função resizeArquivo().
   * @throws Exception
   */
  public static function limitarDimensoesArquivo(string $path, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    return self::resizeArquivo($path, $maxLargura, $maxAltura, $permitirAmpliar);
  }

  /**
   * Um alias para a função resizeString().
   * @throws Exception
   */
  public static function limitarDimensoesString(string $conteudo, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    return self::resizeString($conteudo, $maxLargura, $maxAltura, $permitirAmpliar);
  }

  /**
   * Substitui pixels transparentes em uma imagem por uma cor RGB sólida.
   * @param resource $imagemGd - Recurso GD da imagem original.
   * @param int $red - Componente vermelho (0 a 255).
   * @param int $green - Componente verde (0 a 255).
   * @param int $blue - Componente azul (0 a 255).
   * @return resource - Imagem GD com fundo alterado.
   */
  public static function pintarPixelsTransparentes($imagemGd, int $red = 255, int $green = 255, int $blue = 255) {
    $largura = imagesx($imagemGd);
    $altura = imagesy($imagemGd);

    $imagemNova = imagecreatetruecolor($largura, $altura); // Cria uma nova imagem com fundo sólido

    // Preenche o fundo com a cor especificada
    $corFundo = imagecolorallocate($imagemNova, $red, $green, $blue);
    imagefill($imagemNova, 0, 0, $corFundo);

    // Habilita a mesclagem de transparência
    imagealphablending($imagemGd, true);
    imagesavealpha($imagemGd, true);

    // Copia a imagem original para a nova, preservando transparência e fundo
    imagecopy($imagemNova, $imagemGd, 0, 0, 0, 0, $largura, $altura);

    return $imagemNova;
  }

  /**
   * Converte pixels próximos de uma cor específica para outra cor, incluindo transparência.
   * @param resource $imagemGd - Instância GD da imagem.
   * @param array $corBase - Cor de referência para comparação ([R, G, B]).
   * @param array|null $corNova - Cor desejada ([R, G, B]) ou null para tornar transparente.
   * @param int $tolerancia - Tolerância para variação de cor (0 a 255).
   * @return resource - Imagem alterada.
   */
  public static function pintarCoresProximas($imagemGd, array $corBase = [255, 255, 255], ?array $corNova = null, int $tolerancia = 10) {
    $largura = imagesx($imagemGd);
    $altura = imagesy($imagemGd);

    // Garante suporte a transparência
    imagealphablending($imagemGd, false);
    imagesavealpha($imagemGd, true);

    // Define a nova cor (transparente ou opaca)
    if (is_null($corNova)) {
      $novaCor = imagecolorallocatealpha($imagemGd, 0, 0, 0, 127); // Totalmente transparente
    } else {
      $novaCor = imagecolorallocate($imagemGd, $corNova[0], $corNova[1], $corNova[2]);
    }

    // Percorre cada pixel da imagem
    for ($x = 0; $x < $largura; $x++) {
      for ($y = 0; $y < $altura; $y++) {
        $rgb = imagecolorat($imagemGd, $x, $y);
        $cores = imagecolorsforindex($imagemGd, $rgb);

        // Calcula a diferença de cor em cada canal
        $difR = abs($cores['red'] - $corBase[0]);
        $difG = abs($cores['green'] - $corBase[1]);
        $difB = abs($cores['blue'] - $corBase[2]);

        // Se a diferença estiver dentro da tolerância, substitui a cor
        if ($difR <= $tolerancia && $difG <= $tolerancia && $difB <= $tolerancia) {
          imagesetpixel($imagemGd, $x, $y, $novaCor);
        }
      }
    }
    return $imagemGd;
  }

  /**
   * Recorta a imagem, removendo as bordas com pixels na cor informada.
   * @param resource $imagemGd - Recurso GD da imagem.
   * @param int $corBorda - Cor da borda em hexadecimal (ex: 0xFFFFFF para branco).
   * @param int $tolerancia - Tolerância para pequenas variações de cor (0 a 255).
   * @return resource - Imagem recortada.
   * @throws Exception
   */
  public static function apararBordas($imagemGd, int $corBorda = 0xFFFFFF, int $tolerancia = 0) {
    $largura = imagesx($imagemGd);
    $altura = imagesy($imagemGd);

    if (!$largura || !$altura) throw new Exception('Não foi possível identificar a resolução da imagem.');

    // Extrai componentes RGB da cor da borda
    $rBorda = ($corBorda >> 16) & 0xFF;
    $gBorda = ($corBorda >> 8) & 0xFF;
    $bBorda = $corBorda & 0xFF;

    // Função auxiliar para verificar se a cor está dentro da tolerância
    $corDentroTolerancia = function($corPixel) use ($rBorda, $gBorda, $bBorda, $tolerancia) {
      $rPixel = ($corPixel >> 16) & 0xFF;
      $gPixel = ($corPixel >> 8) & 0xFF;
      $bPixel = $corPixel & 0xFF;
      return abs($rPixel - $rBorda) <= $tolerancia &&
        abs($gPixel - $gBorda) <= $tolerancia &&
        abs($bPixel - $bBorda) <= $tolerancia;
    };

    // Topo
    for ($top = 0; $top < $altura; $top++) {
      for ($x = 0; $x < $largura; $x++) {
        if (!$corDentroTolerancia(imagecolorat($imagemGd, $x, $top))) break 2;
      }
    }

    // Base
    for ($bottom = 0; $bottom < $altura; $bottom++) {
      for ($x = 0; $x < $largura; $x++) {
        if (!$corDentroTolerancia(imagecolorat($imagemGd, $x, $altura - $bottom - 1))) break 2;
      }
    }

    // Esquerda
    for ($left = 0; $left < $largura; $left++) {
      for ($y = 0; $y < $altura; $y++) {
        if (!$corDentroTolerancia(imagecolorat($imagemGd, $left, $y))) break 2;
      }
    }

    // Direita
    for ($right = 0; $right < $largura; $right++) {
      for ($y = 0; $y < $altura; $y++) {
        if (!$corDentroTolerancia(imagecolorat($imagemGd, $largura - $right - 1, $y))) break 2;
      }
    }

    // Calcula a nova largura e altura
    $novaLargura = $largura - ($left + $right);
    $novaAltura = $altura - ($top + $bottom);

    // Valida se as dimensões são válidas
    if ($novaLargura <= 0 || $novaAltura <= 0) {
      throw new Exception('A imagem não pode ser recortada com essas configurações.');
    }

    // Cria uma nova imagem e copia o conteúdo recortado
    $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
    imagecopy($novaImagem, $imagemGd, 0, 0, $left, $top, $novaLargura, $novaAltura);

    return $novaImagem;
  }

  /**
   * Converte a escala de qualidade de imagem (0-100) para o padrao do PNG (9-0)
   * @param int $numeroOrigem - Entre 0 a 100.
   * @return int - Entre 9 a 0.
   */
  private static function converterEscala(int $numeroOrigem): int
  {
    if ($numeroOrigem < 0 || $numeroOrigem > 100) return 0;
    $novoNumero = (100 - $numeroOrigem) * (9 / 100);
    return intval(ceil($novoNumero));
  }
}