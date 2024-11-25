<?php
/**
 * Utilizando a extensão GD essa classe oferece funções para converter imagens, redimensionar, limitar largura ou altura
 * cortar a imagem, pintar o fundo de outra cor e remover as bordas desnecessárias que só possuem a cor de fundo.
 *
 * @author Elias Lazcano Castro Neto
 * @version 2024-11-13
 * @since 7.1
 */

namespace Eliaslazcano\Helpers\Image;

use Exception;

class GdUtils
{
  /**
   * Informa se o PHP é compatível com o formato WEBP.
   * @return bool
   */
  public static function compatibilidadeWebp(): bool
  {
    return function_exists('imagewebp');
  }

  /**
   * Cria uma instancia de imagem GD a partir do arquivo.
   * @param string $path - Caminho até o arquivo.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function toResource(string $path)
  {
    if (!extension_loaded('gd')) throw new Exception('A extensão GD é necessária para a manipulação de imagens.');
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
   * Redimensiona a imagem para o limite de largura e altura estabelecido, reduzindo sem perder a proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $path - Caminho até o arquivo.
   * @param int $maxLargura - Limite de largura em pixels.
   * @param int $maxAltura - Limite de altura em pixels.
   * @param bool $permitirAmpliar - Aumenta a imagem caso ela seja menor que o limite estabelecido.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function limitarDimensoesArquivo(string $path, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    $imagemOriginal = self::toResource($path);

    $imagemInfo = getimagesize($path);
    $originalWidth = $imagemInfo[0];
    $originalHeight = $imagemInfo[1];
    $mimeType = $imagemInfo['mime'];

    // Verificar se a imagem precisa ser redimensionada
    $reduzir = $originalWidth > $maxLargura || $originalHeight > $maxAltura;
    $ampliar = $permitirAmpliar && ($originalWidth < $maxLargura || $originalHeight < $maxAltura);
    if ($reduzir || $ampliar) {
      $ratio = min($maxLargura / $originalWidth, $maxAltura / $originalHeight);
      $newWidth = (int)($originalWidth * $ratio);
      $newHeight = (int)($originalHeight * $ratio);

      // Criar uma nova imagem com as dimensões redimensionadas
      $imagemNova = imagecreatetruecolor($newWidth, $newHeight);
      if (!$imagemNova) throw new Exception('Não foi possível redimensionar a imagem.');

      //Preserva a transparencia (bloco de codigo opcional, nao interfere no redimensionamento)
      if ($mimeType == 'image/png' || $mimeType == 'image/gif' || $mimeType == 'image/webp') {
        imagealphablending($imagemNova, false);
        imagesavealpha($imagemNova, true);
      }

      imagecopyresampled($imagemNova, $imagemOriginal, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
      imagedestroy($imagemOriginal);
    } else {
      $imagemNova = $imagemOriginal; //A imagem não precisar ser redimensionada
    }
    return $imagemNova;
  }

  /**
   * Redimensiona a imagem para o limite de largura e altura estabelecido, reduzindo sem perder a proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $conteudo - Conteúdo do arquivo em string binária.
   * @param int $maxLargura - Limite de largura em pixels.
   * @param int $maxAltura - Limite de altura em pixels.
   * @param bool $permitirAmpliar - Aumenta a imagem caso ela seja menor que o limite estabelecido.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp().
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function limitarDimensoesString(string $conteudo, int $maxLargura, int $maxAltura, bool $permitirAmpliar = false)
  {
    if (!extension_loaded('gd')) throw new Exception('A extensão GD é necessária para a manipulação de imagens.');
    $imagemInfo = getimagesizefromstring($conteudo);
    if (!$imagemInfo) throw new Exception('O arquivo não é uma imagem válida.');

    $originalWidth = $imagemInfo[0];
    $originalHeight = $imagemInfo[1];
    $mimeType = $imagemInfo['mime'];

    switch ($mimeType) {
      case 'image/jpeg':
        $imagemOriginal = imagecreatefromstring($conteudo);
        break;
      case 'image/png':
      case 'image/gif':
      case 'image/webp':
        $imagemOriginal = imagecreatefromstring($conteudo);
        imagealphablending($imagemOriginal, false);
        imagesavealpha($imagemOriginal, true);
        break;
      default:
        throw new Exception('Formato de imagem não suportado.');
    }
    if (!$imagemOriginal) throw new Exception('Não foi possível inspecionar a imagem.');

    // Verificar se a imagem precisa ser redimensionada
    $reduzir = $originalWidth > $maxLargura || $originalHeight > $maxAltura;
    $ampliar = $permitirAmpliar && ($originalWidth < $maxLargura || $originalHeight < $maxAltura);
    if ($reduzir || $ampliar) {
      $ratio = min($maxLargura / $originalWidth, $maxAltura / $originalHeight);
      $newWidth = (int)($originalWidth * $ratio);
      $newHeight = (int)($originalHeight * $ratio);

      // Criar uma nova imagem com as dimensões redimensionadas
      $imagemNova = imagecreatetruecolor($newWidth, $newHeight);
      if (!$imagemNova) throw new Exception('Não foi possível redimensionar a imagem.');

      //Preserva a transparencia (bloco de codigo opcional, nao interfere no redimensionamento)
      if ($mimeType == 'image/png' || $mimeType == 'image/gif' || $mimeType == 'image/webp') {
        imagealphablending($imagemNova, false);
        imagesavealpha($imagemNova, true);
      }

      imagecopyresampled($imagemNova, $imagemOriginal, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
      imagedestroy($imagemOriginal);
    } else {
      $imagemNova = $imagemOriginal; //A imagem não precisar ser redimensionada
    }
    return $imagemNova;
  }

  /**
   * @param int $originalWidth
   * @param int $originalHeight
   * @param int $width
   * @param int $height
   * @param resource $image
   * @return resource
   * @throws Exception
   */
  private static function cropImage(int $originalWidth, int $originalHeight, int $width, int $height, $image)
  {
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
      imagecopyresampled($imagemNova, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

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
      $imagemNova2 = $image; // A imagem não precisa ser redimensionada
    }

    return $imagemNova2;
  }

  /**
   * Redimensiona a imagem para o tamanho desejado, fazendo um corte centralizado caso ela não corresponda a mesma proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $path - Caminho até o arquivo.
   * @param int $width - Limite de largura em pixels.
   * @param int $height - Limite de altura em pixels.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function cropArquivo(string $path, int $width, int $height)
  {
    $image = self::toResource($path);
    if (!$image) throw new Exception('Não foi possível inspecionar a imagem.');

    $imagemInfo = getimagesize($path);
    $originalWidth = $imagemInfo[0];
    $originalHeight = $imagemInfo[1];

    return self::cropImage($originalWidth, $originalHeight, $width, $height, $image);
  }

  /**
   * Redimensiona a imagem para o tamanho desejado, fazendo um corte centralizado caso ela não corresponda a mesma proporção.
   * Esta função depende da extensão GD para realizar operações de imagem.
   * @param string $conteudo - Conteúdo do arquivo em string binária.
   * @param int $width - Limite de largura em pixels.
   * @param int $height - Limite de altura em pixels.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD, como imagejpeg(), imagepng() e imagewebp(). Use imagedestroy() para liberar memória.
   * @throws Exception - Em caso de falha, a mensagem de erro é incluida na Exception.
   */
  public static function cropString(string $conteudo, int $width, int $height)
  {
    if (!extension_loaded('gd')) throw new Exception('A extensão GD é necessária para a manipulação de imagens.');
    $imagemInfo = getimagesizefromstring($conteudo);
    if (!$imagemInfo) throw new Exception('O arquivo não é uma imagem válida.');

    $originalWidth = $imagemInfo[0];
    $originalHeight = $imagemInfo[1];
    $mimeType = $imagemInfo['mime'];

    switch ($mimeType) {
      case 'image/jpeg':
        $imagem = imagecreatefromstring($conteudo);
        break;
      case 'image/png':
      case 'image/gif':
      case 'image/webp':
        $imagem = imagecreatefromstring($conteudo);
        imagealphablending($imagem, false);
        imagesavealpha($imagem, true);
        break;
      default:
        throw new Exception('Formato de imagem não suportado.');
    }
    if (!$imagem) throw new Exception('Não foi possível inspecionar a imagem.');

    return self::cropImage($originalWidth, $originalHeight, $width, $height, $imagem);
  }

  /**
   * Calcula a quantidade de memória necessária para manipular a imagem com GD.
   * @param string $path
   * @return float
   */
  public static function calcularMemoriaNecessariaEdicao(string $path): float
  {
    $aImageInfo = getimagesize($path);
    return round((($aImageInfo[0] * $aImageInfo[1] * $aImageInfo['bits'] * $aImageInfo['channels'] / 8 + Pow(2, 16)) * 1.65));
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

  /**
   * Pinta o fundo transparente de uma imagem por uma cor RGB.
   * @param resource $imagem - Recurso GD.
   * @param int $red - Vermelho de 0 a 255
   * @param int $green - Verde de 0 a 255
   * @param int $blue - Azul de 0 a 255
   * @return resource
   */
  public static function pintarFundo($imagemGd, int $red = 255, int $green = 255, int $blue = 255)
  {
    // Cria uma nova imagem com as mesmas dimensoes da original
    $largura = imagesx($imagemGd);
    $altura = imagesy($imagemGd);
    $imagemNova = imagecreatetruecolor($largura, $altura);

    //Preenche a nova imagem com branco
    $fundoBranco = imagecolorallocate($imagemNova, $red, $green, $blue);
    imagefill($imagemNova, 0, 0, $fundoBranco);

    //Copia a imagem original para a nova imagem com fundo branco
    imagecopy($imagemNova, $imagemGd, 0, 0, 0, 0, $largura, $altura);
    return $imagemNova;
  }

  /**
   * Converte uma instância de resource (imagem GD) para um resultado em string binária do formato desejado.
   * @param resource $imagemGd - Recurso GD.
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
      $imagemComFundoBranco = self::pintarFundo($imagemGd);
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
   * Converte uma instância de resource (imagem GD) para um arquivo binário que é gravado no caminho informado.
   * @param $imagemGd - Recurso GD.
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
      $imagemComFundoBranco = self::pintarFundo($imagemGd);
      $sucesso = imagejpeg($imagemComFundoBranco, $destino, $quality); //Salva a imagem
      imagedestroy($imagemComFundoBranco);
    }
    elseif ($type === IMAGETYPE_PNG) $sucesso = imagepng($imagemGd, $destino, self::converterEscala($quality));
    elseif ($type === IMAGETYPE_GIF) $sucesso = imagegif($imagemGd, $destino);
    elseif (self::compatibilidadeWebp() && $type === IMAGETYPE_WEBP) $sucesso = imagewebp($imagemGd, $destino, $quality);
    if (!$sucesso) throw new Exception('Não foi possível gravar a imagem localmente.');
  }

  /**
   * Recorta a imagem, removendo as laterais da imagem com pixel na cor informada.
   * @param resource $imagemGd - Recurso GD criado por funcoes como imagecreatefromjpeg().
   * @param int $corBorda - Cor da borda que será removida, em hexadecimal (rgb) com o prefixo '0x'. Padrao: branco.
   * @return resource - Recurso que pode ser utilizado por funções da extensão GD. Use imagedestroy() para liberar memória.
   */
  public static function apararBordas ($imagemGd, int $corBorda = 0xFFFFFF) {
    //Top
    for($b_top = 0; $b_top < imagesy($imagemGd); ++$b_top) {
      for($x = 0; $x < imagesx($imagemGd); ++$x) {
        if(imagecolorat($imagemGd, $x, $b_top) != $corBorda) break 2;
      }
    }

    //Bottom
    for($b_btm = 0; $b_btm < imagesy($imagemGd); ++$b_btm) {
      for($x = 0; $x < imagesx($imagemGd); ++$x) {
        if(imagecolorat($imagemGd, $x, imagesy($imagemGd) - $b_btm-1) != $corBorda) break 2;
      }
    }

    //Left
    for($b_lft = 0; $b_lft < imagesx($imagemGd); ++$b_lft) {
      for($y = 0; $y < imagesy($imagemGd); ++$y) {
        if(imagecolorat($imagemGd, $b_lft, $y) != $corBorda) break 2;
      }
    }

    //Right
    for($b_rt = 0; $b_rt < imagesx($imagemGd); ++$b_rt) {
      for($y = 0; $y < imagesy($imagemGd); ++$y) {
        if(imagecolorat($imagemGd, imagesx($imagemGd) - $b_rt-1, $y) != $corBorda) break 2;
      }
    }

    $newimg = imagecreatetruecolor(imagesx($imagemGd) - ($b_lft + $b_rt), imagesy($imagemGd) - ($b_top + $b_btm));
    imagecopy($newimg, $imagemGd, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));
    return $newimg;
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
}