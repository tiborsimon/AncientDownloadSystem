<?php

/*
=============================================================================
#  Ancient Download System - The Lightweight PHP Sharing System             #
=============================================================================
#  Copyright © 2013  Tibor Simon  <contact[_aT_]tibor-simon[_dOt_]com>      #
#                                                                           #
#  This program is free software; you can redistribute it and/or modify     #
#  it under the terms of the GNU General Public License	Version 2 as        #
#  published by the Free Software Foundation.                               #
#                                                                           #
#  This program is distributed in the hope that it will be useful, but      #
#  WITHOUT ANY WARRANTY; without even the implied warranty of               #
#  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU        #
#  General Public License for more details.                                 #
#                                                                           #
#  You should have received a copy of the GNU General Public License v2.0   #
#  along with this program in the root directory; if not, write to the      #
#  Free Software Foundation, Inc., 51 Franklin Street, Fifth Floor,         #
#  Boston, MA 02110-1301, USA.                                              #
=============================================================================
#  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS  #
#  OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF               #
#  MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.   #
#  IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY     #
#  CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,     #
#  TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE        #
#  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.                   #
=============================================================================


=============================================================================
  I N D E X . P H P
=============================================================================

  This is the main entry point for the download system. It expects an URL
  parameter called 'code', which identifies the downloadable zip package.

  If there is no parameter it promts an ad like screen.
  
=============================================================================
*/

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
	<div id="footer-frontend"><a href="http://tibor-simon.com/portfolio/ancient-download-system/" target="_blank" style="text-decoration: none;">Ancient Download System</a> <?php echo VERSION; ?> - Copyright © <?php echo date("Y"); ?></div>
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
