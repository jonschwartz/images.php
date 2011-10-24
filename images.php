<?php
/* ----------------------------------------------------------------
DYNAMIC IMAGE RESIZING SCRIPT - V2
The following script will take an existing JPG image, and resize it
using set options defined in your .htaccess file (while also providing
a nice clean URL to use when referencing the images)
Images will be cached, to reduce overhead, and will be updated only if
the image is newer than it's cached version.

The original script is from Timothy Crowe's 'veryraw' website, with
caching additions added by Trent Davies:
http://veryraw.com/history/2005/03/image-resizing-with-php/

Further modifications to include antialiasing, sharpening, gif & png 
support, plus folder structues for image paths, added by Mike Harding
http://sneak.co.nz

For instructions on use, head to http://sneak.co.nz

Edited by JHS to create square images for thumbnail and medium 
sized images and to add the watermark on large images

-1/18/2010 changes made to eliminate blank black image
---------------------------------------------------------------- */

/*
Images.php is used to dynamically resize images.

If the largest size is requested, a watermark is overlaid.  Otherwise, the image is shrunk to the appropriate size and the sides or top & bottom are padded to make the image square.

Using this script makes it so you only need to store one version of an image (the largest) and it will fill a cached folder with the smaller sizes to allow site loading to still be speedy.
*/


// max_width and image variables are sent by htaccess

$max_height = 2000;
$no_image = 'unavailable.jpg';
$large = 0;
if (isset($_GET['large']))
{
	$large = $_GET['large']; // is this request for the "large" sized image
}
$image = $_GET['imgfile'];
$max_width = $_GET['max_width'];
if (strrchr($image, '/')) {
	$filename = substr(strrchr($image, '/'), 1); // remove folder references
} else {
	$filename = $image;
}

if (fopen($image, "r")) 
{
}
else
{
	$image = $no_image;
}

$size = getimagesize($image);
$width = $size[0];
$height = $size[1];

$diff_width = 0;
$diff_height = 0;

// get the ratio needed
$x_ratio = $max_width / $width;
$y_ratio = $max_height / $height;

// if image already meets criteria, load current values in
// if not, use ratios to load new size info
if (($width <= $max_width) && ($height <= $max_height) ) {
	$tn_width = $width;
	$tn_height = $height;
} else if (($x_ratio * $height) < $max_height) {
	$tn_height = ceil($x_ratio * $height);
	$tn_width = $max_width;
} else {
	$tn_width = ceil($y_ratio * $width);
	$tn_height = $max_height;
}

if ($width > $height)
{
	$diff_height = ceil(($width-$height)/2);
}
if ($height > $width)
{
	$diff_width = ceil(($height-$width)/2);
}

if ($large == 1)
{
	$diff_width = 0;
	$diff_height = 0;
}

/* Caching additions by Trent Davies */
// first check cache
// cache must be world-readable

if (isset($_GET['disable']))
{
	$resized = 'cache/'.$max_width.$_GET['disable'].'-'.$filename;
}
else
{
	$resized = 'cache/'.$max_width.'-'.$filename;
}
$imageModified = @filemtime($image);
$thumbModified = @filemtime($resized);

header("Content-type: image/jpeg");

// if thumbnail is newer than image then output cached thumbnail and exit

if(($imageModified<$thumbModified) && ($_GET['recache'] != 1) && ($image != $no_image))
{
	header("Last-Modified: ".gmdate("D, d M Y H:i:s",$thumbModified)." GMT");
	readfile($resized);
	exit;
}

if ($large == 1)
{
	$new_width = $width;
	$new_height = $height;
}
else
{
	$new_width = $width+($diff_width*2);
	$new_height = $height+($diff_height*2);
}

// read image
$ext = substr(strrchr($image, '.'), 1); // get the file extension
switch ($ext) { 
	case 'jpg':     // jpg
		$src = imagecreatefromjpeg($image) or imagecreatefromjpeg($no_image);
		break;
	case 'png':     // png
		$src = imagecreatefrompng($image) or imagecreatefromjpeg($no_image);
		break;
	case 'gif':     // gif
		$src = imagecreatefromgif($image) or imagecreatefromjpeg($no_image);
		break;
	default:
		notfound();
}

// set up canvas
$dst_big = imagecreatetruecolor($new_width,$new_height);

if ($large == 0)
{
	if ($diff_width > 0) // height > width
	{
		if ($diff_width < 1)
		{
			$diff_width = 1;
		}
		$blankbox = imagecreatetruecolor($diff_width, $height);
		$bb_height = $height;
		$bb_width = $diff_width;
	}
	else if ($diff_height > 0) // width > height
	{
		if ($diff_height < 1)
		{
			$diff_height = 1;
		}
		$blankbox = imagecreatetruecolor($width, $diff_height);
		$bb_height = $diff_height;
		$bb_width = $width;
	}

	$background_color = imagecolorallocate($blankbox, 255, 255, 255);
	imagefill($blankbox, 0, 0, hexdec("FFFFFF"));

	imagecopyresampled($dst_big, $blankbox, 0, 0, 0, 0, $bb_width, $bb_height, $bb_width, $bb_height);
}
// copy resized image to new canvas

imagecopyresampled ($dst_big, $src, $diff_width, $diff_height, 0, 0, $width, $height, $width, $height);

if ($large == 0)
{
	if ($diff_width > 0) // height > width
	{
		imagecopyresampled($dst_big, $blankbox, $bb_width+$width, 0, 0, 0, $new_width, $new_height, $bb_width, $bb_height);
	}
	else if ($diff_height > 0) // width > height
	{
		imagecopyresampled($dst_big, $blankbox, 0, $bb_height+$height, 0, 0, $new_width, $new_height, $bb_width, $bb_height);
	}
}

/* Add in Watermark */

$wmimage = 'watermark.png';

$watermark = imagecreatefrompng($wmimage) or notfound();

if (($large == 1) && ($_GET['disable'] != 1))
{
	// get the ratio needed
	$x_wm_ratio = $width / 700;
	$y_wm_ratio = $height / 700;

	// if image already meets criteria, load current values in
	// if not, use ratios to load new size info
	if ((700 <= $width) && (700 <= $height) ) {
		$wm_width = 700;
		$wm_height = 700;
	} else if (($x_wm_ratio * 700) < $height) {
		$wm_height = ceil($x_wm_ratio * 700);
		$wm_width = $width;
	} else {
		$wm_width = ceil($y_wm_ratio * 700);
		$wm_height = $height;
	}

	$start_y = ceil($height/2) - ceil($wm_height/2);
	$start_x = ceil($width/2) - ceil($wm_width/2);
	imagecopyresampled($dst_big, $watermark, $start_x, $start_y, 0, 0, $wm_width, $wm_height, 700, 700);
}

if ($large == 0)
{
	$dst = imagecreatetruecolor($max_width,$max_width);
	imagecopyresampled ($dst, $dst_big, 0, 0, 0, 0, $max_width, $max_width, $new_width, $new_height);
}
else if ($large == 1)
{
	$dst = imagecreatetruecolor($width,$height);
	imagecopyresampled ($dst, $dst_big, 0, 0, 0, 0, $width, $height, $width, $height);
}


// send the header and new image
imagejpeg($dst, null, -1);
imagejpeg($dst, $resized, -1); // write the thumbnail to cache as well...

// clear out the resources
imagedestroy($src);
imagedestroy($dst);
imagedestroy($dst_big);
imagedestroy($watermark);
imagedestroy($blankbox);

?>