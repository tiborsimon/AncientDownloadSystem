<?php
echo "START<br />";
if (file_exists($_FILES['file']['tmp_name'])) {
	$file = $_FILES['file'];
	echo "<pre>";
	print_r($file);
	echo "</pre>";

	$filename = $file['tmp_name'];
	$handle = fopen($filename, "rb");
	$contents = fread($handle, filesize($filename));
	fclose($handle);

	echo $contents;

} else {
	echo "file not set..<br />";
}

?>

<form action="test.php" method="POST" enctype="multipart/form-data" autocomplete="off">
	<input type="file" name="file" />
	<input type="submit" class="button-short" name="upload" value="Upload">
</form>