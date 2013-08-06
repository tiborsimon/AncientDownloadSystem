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
  U P L O A D . P H P
=============================================================================

  In this file you can upload your files via browser or via FTP. You can
  choose our upload method. After the file is uploaded you can give its
  name and description, and you are ready to go.

  The state machine of the file:
  	State 0: 
  		Initial authorizing state.
  	State 1:
  		Upload from browser.
  	State 2:
  		Upload via FTP.
  	State 3:
  		Upload done. File info form.
  	State 4:
  		Registration done. Link generation.
  		
=============================================================================
*/

include_once 'core/global.php';
define(CURRENT_FILE, 'upload');

// Alapértelmezésben az állapot 0, majd az FTP és a PHP feltöltési mód választja szét
$state = 0;
$state_overrided = false;

// Át lehet kapcsolni a két üzemmód között
// PHP -> FTP
if (isset($_POST['switch_to_FTP']) && validateSession(false)) {
	// Kapcsolódási adatok hozzáadása
	include '.adatbazisadatok.hozzaadas';

	// Kapcsolódás az adatbázishoz
	$con = mysqli_connect(LOCATION, USERNAME, PSW, DB_NAME);

	// Kapcsolat ellenőrzése
	if (mysqli_connect_errno($con)) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
	// Kapcsolat létrejött.
		// Új adat feltöltése
		$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET uploadMethod = 'ftp' WHERE  id = 1";
		if (!mysqli_query($con,$sql)) {
			$errorMessage = "Internal MySQL error: " . mysqli_error($con);
		}
	}
	mysqli_close($con);
	$state = 2;
} else
// FTP -> PHP
if (isset($_POST['switch_to_browser']) && validateSession(false)) {
	// Kapcsolódási adatok hozzáadása
	include '.adatbazisadatok.hozzaadas';

	// Kapcsolódás az adatbázishoz
	$con = mysqli_connect(LOCATION, USERNAME, PSW, DB_NAME);

	// Kapcsolat ellenőrzése
	if (mysqli_connect_errno($con)) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
	// Kapcsolat létrejött.
		// Új adat feltöltése
		$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET uploadMethod = 'php' WHERE  id = 1";
		if (!mysqli_query($con,$sql)) {
			$errorMessage = "Internal MySQL error: " . mysqli_error($con);
		}
	}
	mysqli_close($con);
	$state = 1;
} else {
	if (validateSession(false)) {
		validateSession(true);
		
		if (isset($_POST['state']) && !empty($_POST['state'])) {
			$state = $_POST['state'];
		} else {
			// Üzemmód beolvasása
			include '.adatbazisadatok.hozzaadas';
			$con = mysqli_connect(LOCATION, USERNAME, PSW, DB_NAME);
			if (mysqli_connect_errno($con)) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
				$sql = "SELECT uploadMethod FROM " . TABLE_NAME_SETTINGS . " WHERE  id = 1";
				$result = mysqli_query($con,$sql);
				while($row = mysqli_fetch_array($result)) {
					if ($row['uploadMethod'] === "php") {
						$state = 1;
					} else {
						$state = 2;
					}
					createSession();
				}
			}
			mysqli_close($con);
		}
	}
}


// Elfelejtett jelszó likn előállítása
$forgotten_password_link = "";
include '.adatbazisadatok.hozzaadas';
$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
if ($mysqli->connect_errno) {
	$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
} else {
	$query = "SELECT baseUrl FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
	if ($result = $mysqli->query($query)) {
		while ($row = $result->fetch_row()) {
			$forgotten_password_link = $row[0]."setup.php?forgot_password";
		}
		$result->close();
	}
}
$mysqli->close();


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
			// Üzemmód beolvasása
			include '.adatbazisadatok.hozzaadas';
			$con = mysqli_connect(LOCATION, USERNAME, PSW, DB_NAME);
			if (mysqli_connect_errno($con)) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
				$sql = "SELECT uploadMethod FROM " . TABLE_NAME_SETTINGS . " WHERE  id = 1";
				$result = mysqli_query($con,$sql);
				while($row = mysqli_fetch_array($result)) {
					if ($row['uploadMethod'] === "php") {
						$state = 1;
					} else {
						$state = 2;
					}
					createSession();
				}
			}
			mysqli_close($con);
		}
	}
} else
// Feldolgozás üzemmódok szerint
// Fájl feltöltése és előkészítése böngészőn keresztül való feltöltéskor.
if ($state == 1) {
	// Ha megérkezik a feltöltött fájl, megkezdődik az ellenőrzés
	if (isset($_POST['upload']) && file_exists($_FILES['zip']['tmp_name'])) {
		$errors = array();
		$zip = $_FILES['zip'];
		$name = $zip['name'];
		$extension = strtolower(end(explode('.', $name)));
		$location = $zip['tmp_name'];

		if ($extension !== 'zip') {
			$errors[] = 'Only zip files are acceptable.';
		}

		if (file_exists('FILES/'.$name)) {
			$errors[] = 'File already exists.';
		}

		if (empty($errors)) {
			if (move_uploaded_file($location, 'FILES/'.$name)) {
				// Fájl átmásolva a helyére, mehet az adatbázisba való feltöltés.
				$state = 3;
			} else {
				$errors[] = 'Something went wrong.. Try again. If it\'s still not working, try FTP upload.';
			}
		}
	} else if (isset($_POST['upload']) && !file_exists($_FILES['name_of_field']['tmp_name'])) {
		$errors[] = "Select a file first.";
	}

} else if ($state == 2) {
	// FTP-n feltöltve a lettek fájlok (elvileg)
	if (isset($_POST['FTP_done'])) {
		// Megkeressük az új fájlokat

		$counter = 0;
		$newFiles = getNewFiles();
		if (empty($newFiles)) {
			$errors[] = "There are no new file(s) to register. Upload some firts.";
		} else {
			foreach ($newFiles as $newFile) {
				$extension = strtolower(end(explode('.', $newFile)));
				if ($extension !== "zip") {
					$errors[] = "Invalid extension: ".$newFile;
					$counter++;
				}
			}

			if (!empty($errors)) {
				if ($counter>1) {
					$errors[] = 'Only zip files are acceptable. Compress (zip) the files or delete them.';
				} else {
					$errors[] = 'Only zip files are acceptable. Compress (zip) the file or delete it.';
				}

			} else {
			// Ha nincs hiba, mehet az adatbázisba való feltöltés.
				$state = 3;
			}
		}
	}
}

$newFiles;
$saved_data[$counter];
$saved_data;
$state3errors;

// Közvetlen harmadik állapotba való lépés
if ($state == 3) {
	// Új fájlok listázása a FILES mappából
	$newFiles = getNewFiles();

	// Módosítandó adatok lehívása az adatbázisból
	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT sharingMethod,countdown FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_row()) {
				// Adatok betétele egy lebontott tömbbe
				$counter = 0;
				foreach ($newFiles as $newFile) {
					$saved_data[$counter]['sharingMethod'] = $row[0];
					$saved_data[$counter]['countdown'] = $row[1];
					$counter++;
				}
			}
			$result->close();
		}
	}
	$mysqli->close();

	if (isset($_POST['save'])) {

		$counter = 0;
		foreach ($newFiles as $newFile) {
			if ($_POST['name'.$counter] === "") {
				$state3errors[$counter][] = "Please type in a name!";
			} else {
				$saved_data[$counter]['name'] = $_POST['name'.$counter];
			}

			$saved_data[$counter]['description'] = $_POST['description'.$counter];
			/*
			$sharingMethod = 0;
			foreach ($_POST['share'.$counter] as $value) {
				if ($value === "facebook") {
					$sharingMethod += 4;
				}
				if ($value === "twitter") {
					$sharingMethod += 2;
				}
				if ($value === "google") {
					$sharingMethod += 1;
				}
			}
			$saved_data[$counter]['sharingMethod'] = $sharingMethod;
			$saved_data[$counter]['countdown'] = $_POST['countdown'.$counter];
			*/
			$counter++;
		}

		// Ha minden ellenőrzés lefutott és nincs hiba, tovább a feldolgozási fázisba
		if (empty($state3errors)) {
			$state = 4;
		}
	}
}

$new_links;
// Negyedik állapotba lépünk azonnal
if ($state == 4) {
	// Új fájlok listázása a FILES mappából
	$newFiles = getNewFiles();

	// Csatlakozás az adatbázishoz
	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$counter = 0;
		foreach ($newFiles as $newFile) {

			// adatok előkészítése
			$today = getNow();
			$name = $_POST['name'.$counter];
			$fileName = $newFile;
			$hash = md5($today.$name.$fileName);

			$description = $_POST['description'.$counter];

			// Linkek létrehozása
			$link = getBaseLink().$hash;
			$new_links[$counter]['link'] = $link;
			$new_links[$counter]['name'] = $name;

			$sql = "INSERT INTO " . TABLE_NAME_FILES . " (hash, name, fileName, uploadDate, description) VALUES (?,?,?,?,?)";
			$stmt = $mysqli->prepare($sql);
			$stmt->bind_param("sssss", $hash, $name, $fileName, $today, $description);
			if (!$stmt->execute()) {
				$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
			}
			$stmt->close();

			$counter++;
		}
	}
	$mysqli->close();
}


?>

<?php 
	getHeader("Upload - Acient Download System");
?>
<body>
	<?php 
	if (validateSession(false)) {
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
			<form action="upload.php" method="POST" enctype="multipart/form-data" autocomplete="off">
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
				} else if ($state == 1) { 
					?>
					<fieldset>
						<legend>Browser upload</legend>
						<input type="file" name="zip" />
						<input type="submit" class="button-short" name="upload" value="Upload">
					</fieldset>
					<input type="submit" name="switch_to_FTP" class="button" value="Switch to FTP upload">
					<input type="hidden" name="state" value="<?php echo $state; ?>">
					<?php
				} else if ($state == 2) {
					?>
					<fieldset>
						<legend>FTP upload</legend>
						<div class="form-info">First, you should upload your files via FTP to the FILES folder.</div>
						<input type="submit" name="FTP_done" class="button-short" value="Yes, I have uploaded the file(s)">
					</fieldset>
					<input type="submit" name="switch_to_browser" class="button" value="Switch to browser upload">
					<input type="hidden" name="state" value="<?php echo $state; ?>">
					<?php
				} else if ($state == 3) {
					?>
					<?php 
					$counter = 0;
					foreach ($newFiles as $newFile) {
						?>
						<fieldset>
							<legend><?php echo $newFile ?></legend>
							<p class="form-label">Name</p><input type="textfield" class="custom-input" name="<?php echo 'name'.$counter; ?>" value="<?php echo $saved_data[$counter]['name']; ?>" /><br />
							<p class="form-label">Description</p><textarea rows="3" class="custom-input" name="<?php echo 'description'.$counter; ?>"><?php echo $saved_data[$counter]['description']; ?></textarea><br />
						</fieldset>
						<?php
						if (!empty($state3errors[$counter])) {
							?>
							<fieldset>
								<legend><?php if(count($errors)>1) echo "Errors"; else echo "Error"; ?></legend>
								<?php 
								foreach ($state3errors[$counter] as $error) {
									echo "<p class='error'>".$error."</p>";
								}
								?>
							</fieldset>
							<?php
						}
						?>
						<?php
						$counter++;
					}
					?>

					<input type="submit" name="save" class="button" value="Save">
					<input type="hidden" name="state" value="<?php echo $state; ?>">
					<?php
				}
				?>
			</form>
			<?php
			if (!empty($errors)) {
				?>
				<fieldset>
					<legend><?php if(count($errors)>1) echo "Errors"; else echo "Error"; ?></legend>
					<?php 
					foreach ($errors as $error) {
						echo "<p class='error'>".$error."</p>";
					}
					?>
				</fieldset>
				<?php
			} else if ($state == 4) {
				?>
				<fieldset>
					<legend>Uploaded files</legend>
					<p class="form-info"><?php if (count($new_links) > 1) echo 'Your files are ready to download. Copy the links, and share them.'; else echo 'Your file is ready to download. Copy the link, and share it.'; ?></p>
					<?php 
					$counter = 0;
					foreach ($new_links as $new_link) {
						?>
						<p class="form-label"><?php echo $new_links[$counter]['name']; ?></p><input type="textfield" class="custom-input" value="<?php echo $new_links[$counter]['link']; ?>" /><br />
						<?php
						$counter++;
					}
					?>
				</fieldset>
				<div style="text-align: center; "><a href="<?php echo getBaseLink().'upload.php'; ?>">Upload new files</a></div>
				<?php
			}
			?>
		</div>
	</div>
<?php
	getFooter();
?>

