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
  F I L E S . P H P
=============================================================================

  This file will list all of your files. You can modify the file's data.
  You can also check the system's consistency with this script.

  The state machine of the file:
  	State 0: 
  		Initial authorizing state.
  	State 1:
  		Consistency checking.
  	State 2:
  		List all files.
  		
=============================================================================
*/

include_once 'core/global.php';
define(CURRENT_FILE, 'files');

$checkMessage;
$folder_to_database_errors;
$database_to_folder_errors;
$folder_to_database;
$database_to_folder;

function checkConsistency(&$checkMessage,&$folder_to_database_errors,&$database_to_folder_errors,&$folder_to_database,&$database_to_folder) {

	$checkMessage = array();
	$folder_to_database_errors = array();
	$database_to_folder_errors = array();
	$folder_to_database = array();
	$database_to_folder = array();

	// FILES mappa tartalmalmának listázása
	$folderContent = array_diff(scandir('FILES/'),array(".",".."));
	// Adatbázisban is regisztrált fájlok
	$registeredFiles = array();
	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT fileName FROM " . TABLE_NAME_FILES;
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_row()) {
				$registeredFiles[] = $row[0];
			}
			$result->close();
		}
	}
	$mysqli->close();

	if (empty($registeredFiles) && empty($folderContent)) {
		$checkMessage[] = "The system is empty.";
		return;
	}

	// Új fájlok listáját tartalmazó lista
	$folder_to_database = array_diff($folderContent, $registeredFiles);
	$database_to_folder = array_diff($registeredFiles, $folderContent);

	// Ha üres, akkor minden mappában lévő fájlhoz tartozik bejegyzés
	if (empty($folder_to_database)) {
		$checkMessage[] = "All files are registered.";
	} else {
		if (count($folder_to_database) > 1) {
			$folder_to_database_errors[] = "There are unregistered files in the FILES folder!";
			foreach ($folder_to_database as $missing_file) {
				$folder_to_database_errors[] = $missing_file;
			}
		} else {
			$folder_to_database_errors[] = "There is an unregistered file in the FILES folder!";
			foreach ($folder_to_database as $missing_file) {
				$folder_to_database_errors[] = $missing_file;
			}
		}
	}

	// Ha üres, akkor minden bejegyzéshez megvan a hozzá tartozó fájl
	if (empty($database_to_folder)) {
		$checkMessage[] = "All registered files are in place.";
	} else {
		if (count($database_to_folder) > 1) {
			$database_to_folder_errors[] = "Some registered files are missing from the FILES folder!";
			foreach ($database_to_folder as $missing_file) {
				$database_to_folder_errors[] = $missing_file;
			}
		} else {
			$database_to_folder_errors[] = "A registered file is missing from the FILES folder!";
			foreach ($database_to_folder as $missing_file) {
				$database_to_folder_errors[] = $missing_file;
			}
		}
	}
}

// Alapértelmezésben az állapot 0, majd az FTP és a PHP feltöltési mód választja szét
$state = 0;

// Munkamenet rendszere
if (validateSession(false)) {
	validateSession(true);
	if (isset($_POST['state']) && !empty($_POST['state'])) {
		$state = $_POST['state'];
	} else {
		$state = 1;
	}
}

// Elfelejtett jelszó likn előállítása
$forgotten_password_link = getBaseLink()."setup.php?forgot_password";

// Fájlok listázásához az adatok tárolása
$registeredFilesData;
$state2errors = array();

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
	// Konzisztencia ellenőrzés 
	if (isset($_POST['check'])) {
		checkConsistency($checkMessage,$folder_to_database_errors,$database_to_folder_errors,$folder_to_database,$database_to_folder);
	}
	// Ha a felhasználó törölni akarja a hiányzó fájlokat
	if (isset($_POST['delete_by_checking'])) {
		//Kezdeti vizsgálat
		checkConsistency($checkMessage,$folder_to_database_errors,$database_to_folder_errors,$folder_to_database,$database_to_folder);

		// Törlés előkészítése
		include '.adatbazisadatok.hozzaadas';
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
			foreach ($database_to_folder as $missingName) {
				$sql = "DELETE FROM " . TABLE_NAME_FILES . " WHERE fileName = ?";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("s", $missingName);
				if (!$stmt->execute()) {
					$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				// echo "Deleted: ".$missingName."<br />";
				$stmt->close();
			}
		}
		$mysqli->close();
		checkConsistency($checkMessage,$folder_to_database_errors,$database_to_folder_errors,$folder_to_database,$database_to_folder);
	}
	// Fájlok listázásának előkészítése
	if (isset($_POST['list'])) {
		$registeredFilesData = getRegisteredFilesWithData();
		$state = 2;
	}
// Második állapot, az adatbázisban szereplő fájlok ki vannak listázva, feldolgozásuk zajlik.
} else if ($state == 2) {
	$registeredFilesData = getRegisteredFilesWithData();
	$limit = count($registeredFilesData);
	for ($i=0;$i<$limit;$i++) {
		if (isset($_POST['save'.$i])) {
			if (isset($_POST['name'.$i]) && !empty($_POST['name'.$i])) {
				$name = $_POST['name'.$i];
				$description = $_POST['description'.$i];
				$filename = $registeredFilesData[$i]['filename'];

				include '.adatbazisadatok.hozzaadas';
				$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
				if ($mysqli->connect_errno) {
					echo "Failed to connect to database. Make sure you typed correctly and try again.";
				} else {
					$sql = "UPDATE " . TABLE_NAME_FILES . " SET name = ?, description = ? WHERE fileName = '$filename'";
					$stmt = $mysqli->prepare($sql);
					$stmt->bind_param("ss", $name, $description);

					if (!$stmt->execute()) {
						echo "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
					}
					$stmt->close();
				}
				$mysqli->close();
			} else {
				$state2errors[$i] = 'Name is required.';
			}
		}
	}
	$registeredFilesData = getRegisteredFilesWithData();
	if (isset($_POST['done'])) {
		$state = 1;
	}
}
?>

<?php 
	getHeader("Files - Acient Download System");
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
			<form action="files.php" method="POST" enctype="multipart/form-data" autocomplete="off">
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
						<legend>File operations</legend>
						<input type="submit" name="list" class="button-short" value="List registered files">
						<input type="submit" name="check" class="button-short" value="Check system consistency">
					</fieldset>

					<?php
					if (!empty($checkMessage)) {
						?>
						<fieldset>
							<legend>Checking result</legend>
							<?php foreach ($checkMessage as $message) {
								echo "<span class='form-info'>".$message."</span><br />";
							} ?>
						</fieldset>	
						<?php
					}
					if (!empty($database_to_folder_errors) || !empty($folder_to_database_errors)) {
						?>
						<fieldset>
							<legend>Warning</legend>
							<?php 
							if (!empty($database_to_folder_errors)) {
								$counter = 0;
								foreach ($database_to_folder_errors as $message) {
									if ($counter>0) {
										echo "<span class='form-info'>&nbsp;&nbsp;&bull;&nbsp;</span>";
									}
									echo "<span class='form-info'>".$message."</span><br />";
									$counter++;
								}
								echo "<span class='form-info'>Re-upload via FTP or remove from database.</span><br /><input type='submit' name='delete_by_checking' class='button-short' value='Delete from database.'>";
								echo "<br />";
							}
							if (!empty($folder_to_database_errors)) {
								$counter = 0;
								foreach ($folder_to_database_errors as $message) {
									if ($counter>0) {
										echo "<span class='form-info'>&nbsp;&nbsp;&bull;&nbsp;</span>";
									}
									echo "<span class='form-info'>".$message."</span><br />";
									$counter++;
								}?>
								<div style="text-align: center; "><a href="<?php echo getBaseLink().'upload.php'; ?>">Register via FTP upload</a></div>
								<?php
							}


							?>
						</fieldset>	
						<?php
					}
					?>
					
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 2) {
					$counter = 0;
					if (empty($registeredFilesData)) {
						?>
						<fieldset>
							<legend>Warning</legend>
							<div class='form-info'>The system is empty.</div>
						</fieldset>	
						<?php
					} else {
						foreach ($registeredFilesData as $fileData) {
							?>
							<fieldset>
								<legend><?php echo $fileData['filename'] ?></legend>
								<span class="form-label">Name</span>
								<span class="form-info" style="float:right; padding:0; position:relative; top:3px; right:3px;"><?php echo $fileData['uploadDate']; ?></span><br />
								<input type="textfield" class="custom-input" name="<?php echo 'name'.$counter; ?>" value="<?php echo $fileData['name']; ?>" /><br />
								<p class="form-label">Description</p><textarea rows="3" class="custom-input" name="<?php echo 'description'.$counter; ?>"><?php echo $fileData['description']; ?></textarea><br />
								<p class="form-label">Link</p><input type="textfield" class="custom-input" style="color:#888;" value="<?php echo $fileData['link']; ?>" /><br />
								<input type="submit" class="button-short" name="<?php echo 'save'.$counter; ?>" value="Save changes">

							</fieldset>
							<?php
							if (!empty($state2errors[$counter])) {
								?>
								<fieldset>
									<legend>Error</legend>
									<?php 
									echo "<p class='error'>".$state2errors[$counter]."</p>";
									?>
								</fieldset>
								<?php
							}
							?>
							<input type="hidden" name="state" value="<?php echo $state; ?>" />

							<?php
							$counter++;
						}
					}
					?>
					<input type="submit" name="done" class="button" value="Done">
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
			} 
			?>
		</div>
	</div>
<?php
	getFooter();
?>

