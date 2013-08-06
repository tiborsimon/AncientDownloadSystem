<?php

/* ************************************************************************ *
 *																		   	*
 *  Első lépésként kapcsolódunk az  adatbázishoz és lekérdezzük, milyen     *
 *  a feltöltési módszer, mert ennek feltételeként kettéágazik a végre-     *
 *  hajtási szál. Egyik irány az FTP feltöltés, itt kiiratjuk, hogy ha      *
 *  még nem tette meg a fájlok feltöltését, akkor most tegye meg, mielőtt   *
 *  továbblépne. PHP feltöltésnél erre nincs szükség. A fájl feltöltése     *
 *  itt történik meg.													   	*
 *																		   	*
 *  Mindkét feltöltési módszernek tartalmaznia kell típusellenőrzést, ami   *
 *  hiba esetén megszakítja a folyamatot. Csak és kizárólag zip fájlok      *
 *  tölthetőek fel. Egységesség mindenek felelett.                          * 
 *																		   	*
 *  PHP feltöltésnek ezen kívül még tartalmaznia kell egy volt-e már ilyen  *
 *  vizsgálatot is. Ha volt, a feltöltési folyamat hibával megszakad.       *
 *																		   	*
 *  Ha minden stimmel, és PHP esetben a fájl helyére mozgatása is megtör-   *
 *  tént akkor elkezdődhet az egységes algoritmus, ami feltérképezi a FILES * 
 *  mappát, és ha olyan fájlt talál benne, ami még nem szerepel az adatbá-  *
 *  zisban, akkor megkezdi a betételi algoritmust.							*
 *																			*
 *  Feltöltött fájl adatai:													*
 * 		• id 																*
 *		• név																*
 * 		• feltöltés dátuma													*
 *		• fájl pontos elérési útvonala										*
 *		• megosztási módszer (facebook, twitter vagy google+)				*
 * 		• visszaszámláló													*
 *																			*
 *	Fájl állapotai:															*
 *		• 0: inicilizálási állapot 											*
 *		• 1: PHP feltöltési mód, feltöltési fázis							*
 *		• 2: FTP feltöltés, feltöltési fázis								*
 *		• 3: fájlok hiba nélkül feltöltve, jöhet az adatok feltöltésnek 	*
 *		• 4: minden adat feltöltve, linkek kiosztása						*
 *																		   	*
 * ************************************************************************ */

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

<!DOCTYPE html>
<html>
<head>
	<title>Upload - Acient Download System</title>
	<head profile="http://www.w3.org/2005/10/profile">
	<link rel="icon" type="image/png" href="core/favicon.png" />
	<link rel="stylesheet" type="text/css" href="core/style.css">
	<meta charset='utf-8'> 
</head>
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
	<div id="footer">Ancient Download System <?php echo VERSION; ?> - Copyright © <?php echo date("Y"); ?></div>
</body>
</html>