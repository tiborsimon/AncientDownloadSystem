<?php

include_once 'core/global.php';

$error = '';
$filepath;
$file;
$hash = '';

if (isset($_GET['code'])) {
	$hash = $_GET['code'];

	$file = getFileWithHash($hash);
	$filepath = 'FILES/'.$file['filename'];
	

	if ($file !== false) {
		if (isset($_POST['download'])) {

			// Csatlakozás az adatbázishoz
			include '.adatbazisadatok.hozzaadas';
			$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
			if ($mysqli->connect_errno) {
				echo "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
				$sql = "INSERT INTO " . TABLE_NAME_STATS . " (hash, date) VALUES (?,?)";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("ss", $hash, getNow());
				if (!$stmt->execute()) {
					echo "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				$stmt->close();
			}
			$mysqli->close();
			
			header("Content-Disposition: attachment; filename=\"".$file['filename']."\"");
			header("Content-Type: application/octet-stream");
			readfile($filepath);
		}
	} else {
		$error = 'The file was not found. It was deleted by the author, or the link is broken.';
	}
} else {
	$error = '1';
}


?>

<?php 
	getHeader("Download - Acient Download System");
?>

<script type="text/javascript">
var a="hello";
</script>
<body>
	<div id="fb-root"></div>
	<script>(function(d, s, id) {
		var js, fjs = d.getElementsByTagName(s)[0];
		if (d.getElementById(id)) return;
		js = d.createElement(s); js.id = id;
		js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
		fjs.parentNode.insertBefore(js, fjs);
	}(document, 'script', 'facebook-jssdk'));</script>
	<div id="bg">&nbsp;</div>
	<div id="logo">&nbsp;</div>
	<div id="wrapper-frontend">
		<div id="content">
			<form action="index.php?code=<?php echo $hash; ?>" method="POST" enctype="multipart/form-data" autocomplete="off">
				<?php 
				if ($error === '') {
					?>
					<fieldset>
						<legend><?php echo $file['name']; ?></legend>
						<div class='form-info'><?php echo $file['description']; ?></div>
						<?php $filesize = filesize($filepath); if ($filesize>1024*1024) {$filesize = round($filesize/(1024*1024)).' MB';} else {$filesize = round($filesize/1024).' KB';} ?>
						<div class='form-info' style="font-style: italic;"><?php echo $file['filename']. ': ' .$filesize; ?></div>
						<input type="submit" class="button-short" name="download" value="Download">
						<input type="hidden" value="<?php echo $hash; ?>" name="hash">
					</fieldset>
					<?php
				} else {
					?>
					<fieldset>
						<legend>Ancient Download System</legend>
						<p class="form-title" style="text-align:center">Upload and share your files easily.</p>
					</fieldset>
					<?php
					if ($error !== '1') {
						?>
						<fieldset>
							<legend>Error</legend>
							<p class='error'><?php echo $error; ?></p>
						</fieldset>
						<?php
					}
				}
				?>
			</form>
		</div>
	</div>
	<div id="footer-frontend">Ancient Download System <?php echo VERSION; ?> - Copyright © <?php echo date("Y"); ?></div>
	<div class='social'>
		<div class='social-container'>
			<div class="fb-like" data-href="<?php echo curPageURL(); ?>" data-send="false" data-layout="button_count" data-width="100" data-show-faces="false"></div>
		</div>
		<div class='social-container'>
			<a href="https://twitter.com/share" class="twitter-share-button" data-text="<?php 
			if ($hash !== '') { 
				echo 'I am about to download a file called '.$file['name'].'.'; 
			} else { 
				echo 'I have just visited an Ancient Download System!';
			} ?>" data-hashtags="download">Tweet</a>
			<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
		</div>
		<div class='social-container'>
			<!-- Place this tag where you want the +1 button to render. -->
			<div class="g-plusone" data-size="medium"></div>

			<!-- Place this tag after the last +1 button tag. -->
			<script type="text/javascript">
			(function() {
				var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;
				po.src = 'https://apis.google.com/js/plusone.js';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);
			})();
			</script>
		</div>

	</div>
</body>
</html>