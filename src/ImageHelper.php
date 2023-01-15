<?php

namespace Eliaslazcano\Helpers;

use Gumlet\ImageResize;
use Gumlet\ImageResizeException;

class ImageHelper
{
  /**
   * Converte a string binaria da imagem para string base64.
   * @param string $string String binaria.
   * @return string String base64.
   */
  static function convertStringToBase64($string, $mime)
  {
    return "data:$mime;base64,".base64_encode($string);
  }

  /**
   * Converte a string base64 da imagem para string binaria.
   * @param string $base64 String base64.
   * @return array Array no formato ['mime' => String, 'content' => String].
   */
  static function convertBase64ToString($base64)
  {
    $pos_x = strpos($base64, 'data:') + 5;
    $pos_y = strpos($base64, ';base64');
    $mimeType = substr($base64, $pos_x, $pos_y - $pos_x);
    $string = base64_decode(substr($base64, strpos($base64, ';base64,') + 8));
    return array('mime' => $mimeType, 'content' => $string);
  }

  /**
   * Converte uma imagem em GD para string binaria.
   * @param resource $gdResource Imagem em GD.
   * @param int $imageType Constante IMAGETYPE_JPEG ou IMAGETYPE_PNG
   * @param int $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return false|string String binaria. Em caso de erro sera false.
   */
  static function convertGdToString($gdResource, $imageType = IMAGETYPE_JPEG, $quality = null)
  {
    ob_start();
    if ($imageType === IMAGETYPE_JPEG) imagejpeg($gdResource, null, $quality ?: 90);
    elseif ($imageType === IMAGETYPE_PNG) imagepng($gdResource, null, $quality ?: -1);
    return ob_get_clean();
  }

  /**
   * Converte uma imagem em formato PNG para o formato JPEG. Ambas em representacao de string binaria.
   * @param string $string String binaria da imagem PNG.
   * @param int $quality 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande). Default 90.
   * @param int[] $alphaColor Cor RBG que ira substituir a cor alpha (transparente). Default white (branco).
   * @return string String binaria da imagem JPEG.
   */
  static function convertFormatPngToJpeg($string, $quality = 90, $alphaColor = array(255,255,255))
  {
    $image = imagecreatefromstring($string);
    $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
    imagefill($bg, 0, 0, imagecolorallocate($bg, $alphaColor[0], $alphaColor[1], $alphaColor[2]));
    imagealphablending($bg, true);
    imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
    imagedestroy($image);

    ob_start();
    imagejpeg($bg, null, $quality);
    $outputString = ob_get_clean();
    imagedestroy($bg);
    return $outputString;
  }

  /**
   * Grava no disco um arquivo de imagem atraves de uma string base64.
   * @param string $base64 Imagem em string base64.
   * @param string $filename Caminho da imagem incluindo seu nome e extensao.
   * @return bool Indica se a operacao foi bem sucedida.
   */
  static function createFileFromBase64($base64, $filename)
  {
    $resource = fopen($filename,'wb');
    $image = self::convertBase64ToString($base64);
    $success = fwrite($resource, $image['content']);
    fclose($resource);
    return !!$success;
  }

  /**
   * Grava no disco um arquivo de imagem atraves de uma string binaria.
   * @param string $string Imagem em string binaria.
   * @param string $filename Caminho da imagem incluindo seu nome e extensao.
   * @return bool Indica se a operacao foi bem sucedida.
   */
  static function createFileFromString($string, $filename)
  {
    $resource = fopen($filename,'wb');
    $success = fwrite($resource, $string);
    fclose($resource);
    return !!$success;
  }

  /**
   * Bordas brancas desnecessarias? Recorta a imagem, ate as laterais chegarem em um pixel colorido (!= branco).
   * @param resource $image Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @return resource Imagem em GD.
   */
  static function trimWhiteBorders ($image)
  {
    $b_top = $b_btm = $b_lft = $b_rt =  0;

    //Top
    for(; $b_top < imagesy($image); ++$b_top) {
      for($x = 0; $x < imagesx($image); ++$x) {
        if(imagecolorat($image, $x, $b_top) != 0xFFFFFF) break 2;
      }
    }

    //Bottom
    for(; $b_btm < imagesy($image); ++$b_btm) {
      for($x = 0; $x < imagesx($image); ++$x) {
        if(imagecolorat($image, $x, imagesy($image) - $b_btm-1) != 0xFFFFFF) break 2;
      }
    }

    //Left
    for(; $b_lft < imagesx($image); ++$b_lft) {
      for($y = 0; $y < imagesy($image); ++$y) {
        if(imagecolorat($image, $b_lft, $y) != 0xFFFFFF) break 2;
      }
    }

    //Right
    for(; $b_rt < imagesx($image); ++$b_rt) {
      for($y = 0; $y < imagesy($image); ++$y) {
        if(imagecolorat($image, imagesx($image) - $b_rt-1, $y) != 0xFFFFFF) break 2;
      }
    }

    $newimg = imagecreatetruecolor(imagesx($image) - ($b_lft + $b_rt), imagesy($image) - ($b_top + $b_btm));
    imagecopy($newimg, $image, 0, 0, $b_lft, $b_top, imagesx($newimg), imagesy($newimg));
    return $newimg;
  }

  /**
   * Altera os pixels com cores >= o valor RGB informado para cor alpha (transparente).
   * @param resource $image Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $rgb Pixels com nivel de RGB (claridade) >= a este serao convertidos em cor alpha, aceita valores entre 1 e 255.
   * @return resource Imagem em GD.
   */
  static function fillLightColorToAlpha($image, $rgb = 240)
  {
    if (imageistruecolor($image)) imagetruecolortopalette($image, false, 256);
    for ($i = $rgb; $i < 255; $i++) {
      $colorIndex = imagecolorclosest($image, $i, $i, $i);
      imagecolorset($image, $colorIndex, 255, 255, 255, 127);
    }
    imagepalettetotruecolor($image);
    imagecolortransparent($image, imagecolorallocate($image, 255, 255, 255));
    return $image;
  }

  /**
   * Altera os pixels com cores >= o valor RGB informado para a cor branco absoluto (255).
   * @param resource $image Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $rgb Pixels com nivel de RGB (claridade) >= a este serao convertidos em cor branco, aceita valores entre 1 e 255.
   * @return resource Imagem em GD.
   */
  static function fillLightColorToWhite($image, $rgb = 240)
  {
    if (imageistruecolor($image)) imagetruecolortopalette($image, false, 256);
    for ($i = $rgb; $i < 255; $i++) {
      $colorIndex = imagecolorclosest($image, $i, $i, $i);
      imagecolorset($image, $colorIndex, 255, 255, 255);
    }
    imagepalettetotruecolor($image);
    return $image;
  }

  /**
   * Altera os pixels de cor alpha (transparente) para a cor informada.
   * @param resource $gdImage Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $red 0 a 255, intensidade da cor vermelho do RGB.
   * @param int $green 0 a 255, intensidade da cor verde do RGB.
   * @param int $blue 0 a 255, intensidade da cor azul do RGB.
   * @return resource|false Imagem em GD. false em caso de erro.
   */
  static function fillColorAlphaToRgb($gdImage, $red = 255, $green = 255, $blue = 255)
  {
    $width = imagesx($gdImage);
    $height = imagesy($gdImage);
    $output = imagecreatetruecolor($width, $height);
    $color = imagecolorallocate($output,  $red, $green, $blue);
    imagefilledrectangle($output, 0, 0, $width, $height, $color);
    imagecopy($output, $gdImage, 0, 0, 0, 0, $width, $height);
    return $output;
  }

  /**
   * Altera os pixels de cor alpha (transparente) para a cor branco.
   * @param resource $gdImage Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @return resource|false Imagem em GD. false em caso de erro.
   */
  static function fillColorAlphaToWhite($gdImage)
  {
    return self::fillColorAlphaToRgb($gdImage);
  }

  /**
   * Faz a limpeza inteligente de imagens que representam assinaturas (rubricas), removendo fundo branco e recortando as bordas desnecessarias.
   * @param resource $gdImage Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $lightTolerance Pixels com nivel de RGB (claridade) >= a este serao convertidos em cor alpha (transparente), aceita valores entre 1 e 255.
   * @return resource
   */
  static function smartSignatureCleanup($gdImage, $lightTolerance = 240)
  {
    $gdImage = self::fillColorAlphaToWhite($gdImage); //remove qualquer existencia de cor alpha
    $gdImage = self::fillLightColorToWhite($gdImage, $lightTolerance); //cores proximas ao branco se tornam branco absoluto
    $gdImage = self::trimWhiteBorders($gdImage); //remove bordas desnecessarias da imagem ate tocar na assinatura
    return self::fillLightColorToAlpha($gdImage, $lightTolerance); //cores claras sao convertidas para alpha (transparente)
  }

  /**
   * Redimensiona a imagem ate o limite de largura ou limite de altura especificados, aquele que for atingido primeiro, sem causar recorte.
   * @param string $filename Caminho do arquivo incluindo nome e extensao.
   * @param int $maxWidth Largura maxima em pixels.
   * @param int $maxHeight Altura maxima em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG. Para usar o tipo original da imagem deixe null.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function resizeToBestFit($filename, $maxWidth, $maxHeight, $imageType = null, $allowEnlarge = false, $quality = null)
  {
    try {
      $image = new ImageResize($filename);
      $image->resizeToBestFit($maxWidth, $maxHeight, $allowEnlarge);
      if ($quality === null && $imageType === IMAGETYPE_JPEG) $quality = 90;
      $string = $image->getImageAsString($imageType, $quality);
      return ['data' => $string, 'width' => $image->getDestWidth(), 'height' => $image->getDestHeight()];
    } catch (ImageResizeException $e) {
      return null;
    }
  }

  /**
   * Redimensiona a imagem ate o limite de largura ou limite de altura especificados, aquele que for atingido primeiro, sem causar recorte.
   * @param string $string Imagem em string binaria.
   * @param int $maxWidth Largura maxima em pixels.
   * @param int $maxHeight Altura maxima em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG. Para usar o tipo original da imagem deixe null.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function resizeToBestFitString($string, $maxWidth, $maxHeight, $imageType = null, $allowEnlarge = false, $quality = null)
  {
    $tmpFile = tmpfile();
    if (!$tmpFile) return null;
    fwrite($tmpFile, $string);
    $tmpFileMetaData = stream_get_meta_data($tmpFile);
    $uri = $tmpFileMetaData['uri'];
    $result = self::resizeToBestFit($uri, $maxWidth, $maxHeight, $imageType, $allowEnlarge, $quality);
    fclose($tmpFile);
    return $result;
  }

  /**
   * Redimensiona a imagem ate o limite de largura ou limite de altura especificados, aquele que for atingido primeiro, sem causar recorte.
   * @param resource $gdImage Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $maxWidth Largura maxima em pixels.
   * @param int $maxHeight Altura maxima em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function resizeToBestFitGd($gdImage, $maxWidth, $maxHeight, $imageType, $allowEnlarge = false, $quality = null)
  {
    $tmpFile = tmpfile();
    if (!$tmpFile) return null;
    fwrite($tmpFile, self::convertGdToString($gdImage, $imageType, $quality));
    $tmpFileMetaData = stream_get_meta_data($tmpFile);
    $uri = $tmpFileMetaData['uri'];
    $result = self::resizeToBestFit($uri, $maxWidth, $maxHeight, $imageType, $allowEnlarge, $quality);
    fclose($tmpFile);
    return $result;
  }

  /**
   * Faz um recorte na imagem para a largura e altura especificados, a imagem permanece centralizada pois as bordas de cada eixo sao cortadas por igual em ambos os lados.
   * @param string $filename Caminho do arquivo incluindo nome e extensao.
   * @param int $width Largura em pixels.
   * @param int $height Altura em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG. Para usar o tipo original da imagem deixe null.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function cropFile($filename, $width, $height, $imageType = null, $allowEnlarge = false, $quality = null)
  {
    try {
      $image = new ImageResize($filename);
      $image->crop($width, $height, $allowEnlarge);
      if ($quality === null && $imageType === IMAGETYPE_JPEG) $quality = 90;
      $string = $image->getImageAsString($imageType, $quality);
      return ['data' => $string, 'width' => $image->getDestWidth(), 'height' => $image->getDestHeight()];
    } catch (ImageResizeException $e) {
      return null;
    }
  }

  /**
   * Faz um recorte na imagem para a largura e altura especificados, a imagem permanece centralizada pois as bordas de cada eixo sao cortadas por igual em ambos os lados.
   * @param string $string Imagem em string binaria.
   * @param int $width Largura em pixels.
   * @param int $height Altura em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG. Para usar o tipo original da imagem deixe null.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function cropString($string, $width, $height, $imageType = null, $allowEnlarge = false, $quality = null)
  {
    $tmpFile = tmpfile();
    if (!$tmpFile) return null;
    fwrite($tmpFile, $string);
    $tmpFileMetaData = stream_get_meta_data($tmpFile);
    $uri = $tmpFileMetaData['uri'];
    $result = self::cropFile($uri, $width, $height, $imageType, $allowEnlarge, $quality);
    fclose($tmpFile);
    return $result;
  }

  /**
   * Faz um recorte na imagem para a largura e altura especificados, a imagem permanece centralizada pois as bordas de cada eixo sao cortadas por igual em ambos os lados.
   * @param resource $gdImage Imagem em GD [criado por funcoes como imagecreatefromjpeg()].
   * @param int $width Largura em pixels.
   * @param int $height Altura em pixels.
   * @param int|null $imageType Uma das constantes IMAGETYPE_JPEG ou IMAGETYPE_PNG. Para usar o tipo original da imagem deixe null.
   * @param bool $allowEnlarge Permite ampliar a imagem se for necessario, para um tamanho maior que a origem.
   * @param null $quality Para JPEG: 0 (pior qualidade, arquivo pequeno) ate 100 (melhor qualidade, arquivo grande), padrao 90. Para PNG: 0 (sem compressao) ate 9 (muita compressao).
   * @return array<string, mixed>|null Array no formato ['data' => String, 'width' => Int, 'height' => Int].
   */
  static function cropGd($gdImage, $width, $height, $imageType, $allowEnlarge = false, $quality = null)
  {
    $tmpFile = tmpfile();
    if (!$tmpFile) return null;
    fwrite($tmpFile, self::convertGdToString($gdImage, $imageType, $quality));
    $tmpFileMetaData = stream_get_meta_data($tmpFile);
    $uri = $tmpFileMetaData['uri'];
    $result = self::cropFile($uri, $width, $height, $imageType, $allowEnlarge, $quality);
    fclose($tmpFile);
    return $result;
  }
}