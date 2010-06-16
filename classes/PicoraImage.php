<?php

//@@PicoraAutoLoader PicoraAutoLoader::addFile('PicoraImage',$path.'/classes/PicoraImage.php');

/**
 * Allows for programatic resizing / cropping and simple manipulations of an image file. Supports JPEG and PNG files.
 * 
 * <pre class="highlighted"><code class="php">$i = new Image('my_picture.jpg');
 * print $i->getSourceWidth().' x '.$i->getSourceHeight(); //600 x 400
 * $i->filter('grayscale',true);
 * $i->resizeToSquareThumbnail(150);
 * print $i->getSourceWidth().' x '.$i->getSourceHeight(); //150 x 150
 * $i->save('my_picture_thumbnail.jpg');</code></pre>
 *
 * @introduction Programatic resizing / cropping and manipulation of images.
 */
class PicoraImage {
	protected $type = false;
	protected $source = false;
	protected $target = false;
	protected $quality = 95; //jpeg only
	
	/**
	 * @param string $src Source image file.
	 * @return object
	 */
	public function __construct($src){
		if(!file_exists($src) || !is_readable($src))
			throw new Exception('"'.$src.'" does not exist or is not readable.');
		$info = getimagesize($src);
		$this->type = ($info) ? $info[2] : false;
		if(!$this->type || ($this->type != IMAGETYPE_PNG && $this->type != IMAGETYPE_JPEG))
			throw new Exception('"'.$src.'" is not a PNG or JPG file.');
		$this->source = ($this->type == IMAGETYPE_PNG) ? imagecreatefrompng($src) : imagecreatefromjpeg($src);
		$this->target = ($this->type == IMAGETYPE_PNG) ? imagecreatefrompng($src) : imagecreatefromjpeg($src);
	}
	
	/**
	 * @return string Image type (will be constant IMAGETYPE_PNG or IMAGETYPE_JPEG)
	 */
	public function getType(){
		return $this->type;
	}
	
	/**
	 * @return int Height in pixels.
	 */
	public function getSourceHeight(){
		return imagesy($this->source);
	}
	
	/**
	 * @return int Width in pixels.
	 */
	public function getSourceWidth(){
		return imagesx($this->source);
	}
	
	/**
	 * @return int Height in pixels.
	 */
	public function getTargetHeight(){
		return imagesy($this->target);
	}
	
	/**
	 * @return int Width in pixels.
	 */
	public function getTargetWidth(){
		return imagesx($this->target);
	}
	
	/**
	 * Arbitrarily resize or crop the target image.
	 * @param int $top
	 * @param int $left
	 * @param int $width
	 * @param int $height
	 * @param float $zoomx Defaults to 1.00
	 * @param float $zoomy Defaults to 1.00
	 * @return boolean
	 */
	public function resize($top,$left,$width,$height,$zoomx = 1.00,$zoomy = 1.00){
		$destWidth = (int)($width * $zoomx);
		$destHeight = (int)($height * $zoomy);
		$this->target = imagecreatetruecolor($destWidth,$destHeight);
		//imageantialias($dest,true);
		return imagecopyresampled($this->target,$this->source,0,0,$left,$top,$destWidth,$destHeight,$width,$height);
	}
	
	/**
	 * Resizes the target image preserving the aspect ratio.
	 * @param int $max Maximum length of the longest side of the target image.
	 * @return bool
	 */
	public function resizeToThumbnail($max = 100){
		$top = 0;
		$left = 0;
		$height = $this->getSourceHeight();
		$width = $this->getSourceWidth();
		if($this->getSourceWidth() < $this->getSourceHeight())
			$zoomx = $zoomy = $max / $height;
		elseif($this->getSourceWidth() >= $this->getSourceHeight())
			$zoomx = $zoomy = $max / $width;
		return $this->resize($top,$left,$width,$height,$zoomx,$zoomy);
	}
	
	/**
	 * Resizes the target image to a square the size of the given dimension in pixels. If cropping is necessary the image will automatically be centered.
	 * @param int $dimension Defaults to 100.
	 * @return bool
	 */
	public function resizeToSquareThumbnail($dimension = 100){
		if($this->getSourceWidth() < $this->getSourceHeight()){
			$top = floor(($this->getSourceHeight() - $this->getSourceWidth()) / 2);
			$left = 0;
			$height = $this->getSourceWidth();
			$width = $this->getSourceWidth();
			$zoomx = $zoomy = $dimension / $width;
		}elseif($this->getSourceWidth() > $this->getSourceHeight()){
			$top = 0;
			$left = floor(($this->getSourceWidth() - $this->getSourceHeight()) / 2);
			$height = $this->getSourceHeight();
			$width = $this->getSourceHeight();
			$zoomx = $zoomy = $dimension / $height;
		}else{
			$top = 0;
			$left = 0;
			$width = $height = $this->getSourceHeight();
			$zoomx = $zoomy = $dimension / $height;
		}
		return $this->resize($top,$left,$height,$width,$zoomx,$zoomy);
	}
	
	/**
	 * Supports the following filters:
	 * 
	 * - invert (1 or 0)
	 * - grayscale (1 or 0)
	 * - emboxx (1 or 0)
	 * - sketch (1 or 0)
	 * - brightness (0 to 100)
	 * - contrast (0 to 100)
	 * @param string $type
	 * @param mixed $value
	 * @return bool
	 */
	public function filter($type,$value = 1){
		if(function_exists('imagefilter')){
			switch($type){
				case 'invert':
					if($value != '0')
						imagefilter($this->target,IMG_FILTER_NEGATE);
					break;
				case 'grayscale':
				case 'greyscale':
					if($value != '0')
						imagefilter($this->target,IMG_FILTER_GRAYSCALE);
					break;
				case 'emboss':
					if($value != '0')
						imagefilter($this->target,IMG_FILTER_EMBOSS);
					break;
				case 'sketch':
					if($value != '0')
						imagefilter($this->target,IMG_FILTER_MEAN_REMOVAL);
					break;
				case 'brightness':
					imagefilter($this->target,IMG_FILTER_BRIGHTNESS,(($value * 2) - 100));
					break;
				case 'contrast':
					$value = ($value * 2) - 100;
					if($value != 0)
						$value = $value - ($value * 2);
					imagefilter($this->target,IMG_FILTER_CONTRAST,$value);
					break;
				default:
					throw new Exception('Unknown image filter "'.$type.'".');
					break;
			}
			return true;
		}else
			return false;
	}
	
	/**
	 * Reduces the color palette of a PNG image.
	 * @param int $number_of_colors 0 - 255
	 * @return void
	 */
	public function reduceColors($number_of_colors){
		if($this->getType() == IMAGETYPE_PNG)
			return imagetruecolortopalette($dest,false,$number_of_colors);
		else
			throw new Exception('Image::reduceColors can only be called on PNG images.');
	}
	
	/**
	 * Set the quality of a JPG image.
	 * @param int $quality 0 - 100
	 * @return void
	 */
	public function setQuality($quality){
		$this->quality = $quality;
	}
	
	/**
	 * @return void
	 */
	public function getQuality(){
		return $this->quality;
	}
	
	/**
	 * Outputs the image to the browser. Any output sent before or after this call will result in malformed data being sent to the browser, and the image will not display properly.
	 * @return bool
	 */
	public function display(){
		header('Content-type: '.image_type_to_mime_type($this->getType()));
		return ($this->getType() == IMAGETYPE_JPEG) ? imagejpeg($this->target,false,$this->getQuality()) : imagepng($this->target);
	}
	
	/**
	 * Saves the target image to a given file name.
	 * @param string $file_name
	 * @return bool
	 */
	public function save($file_name){
		return ($this->getType() == IMAGETYPE_JPEG) ? imagejpeg($this->target,$file_name,$this->getQuality()) : imagepng($this->target);
	}
}

?>