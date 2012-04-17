<?php
/**********************************************

	Image resize script
		v 2.0
		By Jon Schwartz
		4/16/2012

	This version of the image resizer script 
	has been completely rewritten to use the
	imagemagick library for better scalability
	and it now will resize all of the images
	in a batch instead of one by one to keep
	from having the script hit the php engine
	every time an image is called.
	
	This script assumes we want to regenerate
	the file regardless of whether or not it
	already exists.
	
***********************************************/
$www = dirname(__FILE__);
$no_image = $www.'/images/parts_images/unavailable.jpg'; // for parts which have no image
$sizes = array(95, 200, 65, 104); // Array of each size image we wish to generate and cache.

$link = mysql_connect('localhost', 'uername', 'password') or die('Could not connect: ' . mysql_error());
mysql_select_db('product') or die('Could not select database');

if (isset($_GET['imgfile']))
{
	$large = 0; // Do we want to generate a large watermarked image?  Set by get parameter.
	if (isset($_GET['large']))
	{
		$large = $_GET['large'];
	}
	$original_image = $_GET['imgfile'];
	$final_width = $_GET['max_width'];
	$final_height = $_GET['max_width'];
	
	$original_image_path = $www.'/images/parts_images/'.$original_image.'.jpg';
	$final_image_path = $www.'/cache/'.$final_width.'-'.$original_image.'.jpg';
	
	if (file_exists($original_image_path)) 
	{
	}
	else
	{
		$original_image = $no_image;
	}
	
	if ($large > 0)
	{
		$final_image = add_watermark($original_image_path);
		$final_width = 700;
	}
	else
	{
		$final_image = resize_original($final_width, $final_height, $original_image_path);
	}
	$final_image -> writeImage($final_image_path);  // write final image to cache so we don't call again for the same image
	
	header('Content-type: image/jpeg');
	echo $final_image;
}
else
{
	// Get all Parts numbers and  Get each PN's Image
	$query = 'SELECT `part_number`.`part_number`,
	(SELECT `image` FROM `products`.`part_image` WHERE `part_image`.`part_number` = `part_number`.`part_number` ORDER BY `part_images`.`version` DESC LIMIT 1) as image
	FROM `products`.`part_number`
	NATURAL JOIN `products`.`part_deleted`
	WHERE `part_deleted`.`deleted` = 0';

	$result = mysql_query($query) or die('Query failed: '.mysql_error()."\n");

	while ($line = mysql_fetch_array($result))
	{
		$lines[] = $line; // This is done to save access time on the db
	}

	foreach ($lines as $line)
	{
		$original_image = $line['image'];
		$original_image_path = $www.'/images/parts_images/'.$original_image.'.jpg';
		
		if (file_exists($original_image_path)) 
		{
		}
		else
		{
			$original_image_path = $no_image;
		}
		
		// Convert for all sizes
		foreach ($sizes as $size)
		{
			$final_image = resize_original($size, $size, $original_image_path);
			$final_image->writeImage($www.'/cache/'.$size.'-'.$original_image.'.jpg');
		}
		$final_image = add_watermark($original_image_path);
		$final_image -> writeImage($www.'/cache/700-'.$original_image.'.jpg'); // fullsized watermark image is stored as "700"
	}
}

function resize_original($final_width, $final_height, $original_image)
{

	$final_image = new Imagick();
	$final_image -> newImage($final_width, $final_height, '#FFFFFF');  // Create a width x height image and fill with bg color
	$final_image -> setImageFormat('jpeg');
	$final_image->setImageCompressionQuality(90);

	$start_image = new Imagick($original_image); // Load original image
	$start_image_size = $start_image->getImageGeometry(); 

	$center_offset_x = 0; // we need to offset the center to know where to start printing out the resized image
	$center_offset_y = 0;

	if ($start_image_size['width'] > $start_image_size['height'])
	{
		$start_image->thumbnailImage($final_width, 0);
		$start_image_size_adj = $start_image->getImageGeometry(); 
		$center_offset_y = $final_height / 2 - $start_image_size_adj['height'] / 2;
	}
	else
	{
		$start_image->thumbnailImage(0, $final_height);
		$start_image_size_adj = $start_image->getImageGeometry(); 
		$center_offset_x = ($final_width / 2) - ($start_image_size_adj['width'] / 2);	
	}

	$final_image -> compositeImage($start_image, imagick::COMPOSITE_ATOP, $center_offset_x, $center_offset_y);
	$final_image -> flattenImages();
	return $final_image;
}

function add_watermark($input_image)
{
	$www = dirname(__FILE__);
	$start_image = new Imagick($input_image);
	$watermark = new Imagick($www.'/images/watermark.png');
	
	$input_image_size = $start_image->getImageGeometry();
	$center_offset_x = 0; // we need to offset the center to know where to start printing out the resized image
	$center_offset_y = 0;
	
	if ($input_image_size['width'] > $input_image_size['height'])
	{
		$watermark->thumbnailImage($input_image_size['width'], 0);
		$watermark_image_size_adj = $watermark->getImageGeometry(); 
		$center_offset_y = $input_image_size['height'] / 2 - $watermark_image_size_adj['height'] / 2;
	}
	else
	{
		$watermark->thumbnailImage(0, $input_image_size['height']);
		$watermark_image_size_adj = $watermark->getImageGeometry(); 
		$center_offset_x = ($input_image_size['width'] / 2) - ($watermark_image_size_adj['width'] / 2);	
	}

	$start_image -> compositeImage($watermark, imagick::COMPOSITE_ATOP, $center_offset_x, $center_offset_y);
	$start_image -> flattenImages();
	return $start_image;
}

?>