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
  S T A T S . P H P
=============================================================================

  This file is used for the statistics. It can plot two type of statistics:

  Statstics based on downloads:
  	It will list all of the downloads ordered by the download server time.

  Statistics based on files:
  	It will show how many times was each file downloaded.

  The state machine of the file:
  	State 0: 
  		Initial authorizing state.
  	State 1:
  		List statstics based on downloads.
  	State 2:
  		List statstics based on files.
  		
=============================================================================
*/

include_once 'core/global.php';
define(CURRENT_FILE, 'stats');

$state = 0;

// Munkamenet rendszere
if (validateSession(false)) {
	validateSession(true);
	if (isset($_POST['download'])) {
		$state = 1;
	} else if (isset($_POST['file'])) {
		$state = 2;
	} else {
		$state = 1;
	}
} else {
	$state = 0;
}

$list;

// Védelmi állapot
if ($state == 0) {
	if (isset($_POST['log_in'])) {
		// Jelszó letöltése az adatbázisból
		$saved_password = "";
		include '.adatbazisadatok.hozzaadas';
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
			$query = "SELECT password FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
			if ($result = $mysqli->query($query)) {
				while ($row = $result->fetch_row()) {
					$saved_password = $row[0];
				}
				$result->close();
			}
		}
		$mysqli->close();

		$entered_password = md5($_POST['password']);

		if ($entered_password !== $saved_password) {
			$errors[] = "Wrong password!";
		} else {
			$state = 1;
			createSession();
		}
	}
// Egyes állapot. Itt lehet kérni konzisztencia ellenőrzést.
} else if ($state == 1) {
	$list = getDownloadStats();
} else if ($state == 2) {
	$list = getFileStats();
}
?>

<?php 
	getHeader("Statistics - Acient Download System");
?>

<script type="text/javascript">
var a="hello";
</script>
<body>
	<?php 
	if (validateSession(false) && !$no_navi) {
		?>
		<div id="navi">
			<a class="bright-link" <?php if (CURRENT_FILE === 'upload') {echo "style='position:relative; left:5px;'";} ?> href="<?php echo getBaseLink().'upload.php'; ?>">Upload</a><br />
			<a class="bright-link" <?php if (CURRENT_FILE === 'files') {echo "style='position:relative; left:5px;'";} ?> href="<?php echo getBaseLink().'files.php'; ?>">Files</a><br />
			<a class="bright-link" <?php if (CURRENT_FILE === 'stats') {echo "style='position:relative; left:5px;'";} ?> href="<?php echo getBaseLink().'stats.php'; ?>">Stats</a><br />
			<a class="bright-link" <?php if (CURRENT_FILE === 'setup') {echo "style='position:relative; left:5px;'";} ?> href="<?php echo getBaseLink().'setup.php'; ?>">Settings</a>
		</div>
		<?php
	}
	?>	
	<div id="bg">&nbsp;</div>
	<div id="wrapper">
		<div id="content">
			<form action="stats.php" method="POST" enctype="multipart/form-data" autocomplete="off">
				<?php
				if ($state == 0) {
					?>
					<fieldset>
						<legend>Protected area</legend>

						<p class="form-label">Password</p>
						<input type="password" class="custom-input" name="password" /><br />
						<a href="<?php echo $forgotten_password_link; ?>">Forgot your password?</a><br />
					</fieldset>
					<input type="submit" name="log_in" class="button" value="Log in">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else {
					?>
					<input type="submit" class="button" name="download" value="Download statistics">
					<input type="submit" class="button" name="file" value="File statistics">
					<input type="hidden" value="<?php echo $state; ?>" name="state">
					<?php
					if ($state == 1) {
						?>
						<fieldset>
							<legend>Download statistics</legend>
							<table>
								<thead>
									<tr>
										<th>#</th>
										<th>Filename</th>
										<th>Download date</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($list as $row) : ?>
									<tr>
										<td><? echo $row['id']; ?></td>
										<td><? echo $row['filename']; ?></td>
										<td><? echo $row['date']; ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>

					</fieldset>
					<?php
				} else if ($state == 2) {
					?>
					<fieldset>
						<legend>File statistics</legend>
						<table>
								<thead>
									<tr>
										<th>Filename</th>
										<th>Count</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($list as $row) : ?>
									<tr>
										<td><? echo $row['filename']; ?></td>
										<td><? echo $row['count']; ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
					</fieldset>
					<?php
				}
				?>
				<input type="submit" class="button" name="download" value="Download statistics">
				<input type="submit" class="button" name="file" value="File statistics">
				<?php
			}
			?>

		</form>
	</div>
</div>
<?php
	getFooter();
?>

