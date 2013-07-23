<?php

/* ************************************************************************ *
 *																		   	*
 *  *
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
 *		• 0: van mentett állapot, jelszó bekérése							*
 *		• 1: nem volt mentett állapot, adatbázis elérhetőségei				*
 *		• 2: védő jelszó													*
 *		• 3: email cím megadása + jelzések									*
 *		• 4: megosztási beállítások: hol, mit, visszaszámlálás				*
 *		• 5: emlékezési rendszer beállítása 								*
 *		• 6: kész állapot - feltöltési link kiírása és küldése 				*
 *		• 7: jelszó bekérése után: új telepítés, vagy meglévő módosítása 	*
 *		• 8: módósító felület												*
 *		• 9: email reset 													*
 *		• 10: email reset kész 												*
 *																		   	*
 * ************************************************************************ */

include_once 'global.php';
define(CURRENT_FILE, 'setup');

// Nulladik állapotba kerülünk alapból. Ha létezik a konfigurációt
// tartalmazó fájl, akkor benne is maradunk, és a rendszer figyelmeztet,
// hogy a régi mentett adatok felülíródnak.
$state = 0;
$forgotten_password_link = "";
if (!file_exists('.adatbazisadatok.hozzaadas')) {
	$state = 1;
} else {
	// Ha van már mentett állapot, legyártjuk a elfelejtett jelszó linket.
	$forgotten_password_link = getBaseLink()."setup.php?forgot_password";
}

// Ha nincs FILES mappa, készítsünk egyet!
if (!file_exists("FILES/")) {
	mkdir("FILES/");
}

// Ha kaptunk paramétert linken keresztül:
// Elfelejtett jelszó kérés
if (isset($_GET['forgot_password']) && !isset($_GET['hash'])) {
	// eamil cím lekérése
	$email = "";
	$forgot_link = "";

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT email,baseUrl FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_row()) {
				$email = $row[0];
				$forgot_link = $row[1]."setup.php?forgot_password";
			}
			$result->close();
		}
		// generate new password
		$passRaw = md5(date("j, n, Y"));
		$passRaw = substr($passRaw, 0, 20);
		$forgot_link = $forgot_link.'&hash='.$passRaw;
		
	}
	$mysqli->close();
	$to      = $email;
	$subject = 'Forgotten Password - Ancient Download System';
	$message = "Hi! You have forgotten your password.. Shame on you..\n".$forgot_link."\n";
	$headers = 'From: ancientsystems@example.com' . "\r\n" .
	'Reply-To: webmaster@example.com' . "\r\n" .
	'X-Mailer: PHP/' . phpversion();

	mail($to, $subject, $message, $headers);

	echo '<!DOCTYPE html>
	<html>
	<head>
	<meta charset="utf-8"> 
	<title>Setup - Acient Download System</title>
	<link rel="stylesheet" type="text/css" href="style.css">
	</head>
	<body>
	<div id="bg">&nbsp;</div>
	<div id="wrapper">
	<div id="content">
	<fieldset>
	<legend>Password reset request</legend>
	<div class="form-info">Resetting link sent to your email address.</div>
	</fieldset>
	</div>
	</div>
	<div id="footer">Ancient Download System '.VERSION. ' - Copyright © <?php echo date("Y"); ?></div>
	</body>
	</html>';
	exit();
}
if (isset($_GET['forgot_password']) && isset($_GET['hash'])) {
	$hash = $_GET['hash'];

	$passRaw = md5(date("j, n, Y"));
	$passRaw = substr($passRaw, 0, 20);

	if ($hash === $passRaw) {
		$state = 9;
	} else {
		echo '<!DOCTYPE html>
		<html>
		<head>
		<meta charset="utf-8"> 
		<title>Setup - Acient Download System</title>
		<link rel="stylesheet" type="text/css" href="style.css">
		</head>
		<body>
		<div id="bg">&nbsp;</div>
		<div id="wrapper">
		<div id="content">
		<fieldset>
		<legend>Error</legend>
		<div class="form-info">It seems you are trying to reset your password, but the resetting link is outdated.</div>
		<div style="text-align:center;"><a href="'.$forgotten_password_link.'">Request a new one</a></div>
		</fieldset>
		</div>
		</div>
		<div id="footer">Ancient Download System '. VERSION .' - Copyright © <?php echo date("Y"); ?></div></body></html>';
		exit();
	}
}

// Ha minden kész ez az üzenet jelenik meg a felhasznlónak, hogy hogyan töltheti
// fel az első letölthető fájlt.
$finalUploadMessage = "";
$link = "";
$edit_content = array();

// állapot betöltése, majd osztályozás, hogy védett helyen vagyunk-e
if (isset($_POST['state']) && !empty($_POST['state'])) {
	$state = $_POST['state'];
}


// Hibaüzenet létrehozása. Ha üres, a képernyőre nem kerül kiírásra semmi.
$errorMessage = "";
$emailError = "";
$passwordError = "";

if ($state == 0) {
	if (isset($_POST['log_in'])) {
		// Jelszó letöltése az adatbázisból
		$saved_password = "";
		// Kapcsolódási adatok hozzáadása
		include '.adatbazisadatok.hozzaadas';

		// Kapcsolódás az adatbázishoz
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);

		// Kapcsolat ellenőrzése
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
		// Kapcsolat létrejött.
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
			$errorMessage = "Wrong password!";
		} else {
			// Minden rendben. Beléptetés.
			$state = 7;
			createSession();
		}
	}
// Első állapot. Itt kell megvizsgálni, hogy a megadott paraméterekkel
// lehet-e csatlakozni a adatbázishoz. Ha nem, akkor hibával visszatérve
// újra be kell kérni az adatokat. Siker esetén a hozzáférési adatokat
// le kell menteni egy fájlba,a mit későbbi csatlakozásokkor fel fogunk
// használni (simán include).
} else if ($state == 1) {
	if (isset($_POST['databaseName']) && !empty($_POST['databaseName'])) {
		
		$dbName = $_POST['databaseName'];
		$dbLocation = $_POST['location'];
		$dbUsername = $_POST['username'];
		$dbPassword = $_POST['password'];

		/*
		// teszteléshez használt adatok
		$dbName = "download_center";
		$dbLocation = "tiborsimoncom.ipagemysql.com";
		$dbUsername = "dl_admin";
		$dbPassword = "123456@@";
		*/

		// Kapcsolódás az adatbázishoz
		$con = mysqli_connect($dbLocation, $dbUsername, $dbPassword, $dbName);

		// Kapcsolat ellenőrzése
		if (mysqli_connect_errno($con)) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
		// Kapcsolat létrejött.
			// Kapcsolodasi adatok fájlba mentése
			$handler = fopen('.adatbazisadatok.hozzaadas', 'w') or die("can't open file");
			fwrite($handler, '<?php define(\'LOCATION\', \''.$dbLocation.'\');define(\'USERNAME\', \''.$dbUsername.'\');define(\'PSW\', \''.$dbPassword.'\');define(\'DB_NAME\', \''.$dbName.'\');define(TABLE_NAME_FILES, \'ancient_download_files\');define(TABLE_NAME_SETTINGS, \'ancient_download_settings\');define(TABLE_NAME_STATS, \'ancient_download_stats\');?>');
			fclose($handler);

			// Listázás kikapcsolása és kapcsolódási fájlok védelme
			$handler = fopen('.htaccess', 'w') or die("can't open file");
			fwrite($handler, "Options All -Indexes\n");
			fwrite($handler, "<files ".SESSION_FILE.">\norder allow,deny\ndeny from all\n</files>\n<files .adatbazisadatok.hozzaadas>\norder allow,deny\ndeny from all\n</files>\n<files .htaccess>\norder allow,deny\ndeny from all\n</files>\n\nOptions +FollowSymLinks\nRewriteEngine On\nRewriteRule ^([a-zA-Z0-9]+)$ /index.php?code=$1\nRewriteRule ^([a-zA-Z0-9]+)/$ /index.php?code=$1");
			fclose($handler);

			// Kapcsolódási adatok hozzáadása
			include '.adatbazisadatok.hozzaadas';

			// VEZÉRLÉSI TÁBLA KÉSZÍTÉSE
			// Ha volt előzőleg vezérlési tábla, töröljük.
			$sql="DROP TABLE IF EXISTS " . TABLE_NAME_SETTINGS;
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}
			// Új vezérlési tábla készítése
			$sql="CREATE TABLE " . TABLE_NAME_SETTINGS . "(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, version CHAR(10), password CHAR(100), baseUrl CHAR(200), uploadMethod CHAR(30), email CHAR(250), notificationCounter INT, notificationThreshold INT, cookieTimeout CHAR(30), sharingMethod INT, countdown INT)";
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}

			// URL lekérése
			$url = str_replace( "setup.php", "", curPageURL() );

			// Vezérlési tábla kezdeti feltöltése
			$sql="INSERT INTO " . TABLE_NAME_SETTINGS . "(version, password, baseUrl, uploadMethod, email, notificationCounter, notificationThreshold, cookieTimeout, sharingMethod, countdown) VALUES ('1.0.0', 'invalid', '$url', 'php', 'none', 0, 0, 0, 0, 0)";
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}

			// FÁJLOKAT TARTALMAZÓ TÁBLA KÉSZÍTÉSE
			// Ha volt előzőleg fájlos tábla, töröljük.
			$sql="DROP TABLE IF EXISTS " . TABLE_NAME_FILES;
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}
			// Új fájlos tábla készítése
			$sql="CREATE TABLE " . TABLE_NAME_FILES . "(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, hash CHAR(32), name CHAR(250), fileName CHAR(250), uploadDate CHAR(20), description TEXT(4096))";
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}

			// STATISZTIKAI TÁBLA KÉSZÍTÉSE
			// Ha volt előzőleg vezérlési tábla, töröljük.
			$sql="DROP TABLE IF EXISTS " . TABLE_NAME_STATS;
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}
			// Új vezérlési tábla készítése
			$sql="CREATE TABLE " . TABLE_NAME_STATS . "(id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, hash CHAR(32), date CHAR(20))";
			if (!mysqli_query($con,$sql)) {
				$errorMessage = "Internal MySQL error: " . mysqli_error($con);
			}
		}

		mysqli_close($con);

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			$state = 2;
		}
	} // isset vége
// Második állapotban adjuk meg a védő jelszót.
} else if ($state == 2) {
	if (isset($_POST['back'])) {
		$state = 1;
	} else if (isset($_POST['next']) && isset($_POST['password1']) && !empty($_POST['password1'])) {

		$password1 = md5($_POST['password1']);
		$password2 = md5($_POST['password2']);

		if ($password1 !== $password2) {
			$errorMessage = "The two passwords must be the same!";
		} else {
			
			// Kapcsolódási adatok hozzáadása
			include '.adatbazisadatok.hozzaadas';

			// Kapcsolódás az adatbázishoz
			$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);

			// Kapcsolat ellenőrzése
			if ($mysqli->connect_errno) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
			// Kapcsolat létrejött.
				// Új adat feltöltése
				$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET password = ? WHERE  id = 1";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("s",$password1);

				if (!$stmt->execute()) {
					$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				$stmt->close();
			}
			$mysqli->close();
		}

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			$state = 3;
		}
	} else {
		$errorMessage = "Fill in all fields!";
	}
// Harmadik állapotban kérjük be az email címet
} else if ($state == 3) {
	if (isset($_POST['back'])) {
		$state = 2;
	} else if (isset($_POST['next']) && isset($_POST['email1']) && !empty($_POST['email1'])) {

		$email1 = $_POST['email1'];
		$email2 = $_POST['email2'];
		
		$notification = $_POST['notification'];
		
		if ($email1 !== $email2) {
			$errorMessage = "The two email addresses must be the same!";
		}

		if ($errorMessage === "" && !filter_var($email1, FILTER_VALIDATE_EMAIL)) {
			$errorMessage = "Invalid email format!";
		} else {
			
			// Kapcsolódási adatok hozzáadása
			include '.adatbazisadatok.hozzaadas';

			// Kapcsolódás az adatbázishoz
			$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);

			// Kapcsolat ellenőrzése
			if ($mysqli->connect_errno) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
			// Kapcsolat létrejött.
				// Új adat feltöltése
				$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET email = ?, notificationThreshold = ? WHERE  id = 1";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("sd", $email1, $notification);

				if (!$stmt->execute()) {
					$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				$stmt->close();
			}
			$mysqli->close();
		}

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			/*$state = 4;
Egyszerűsített első verzió. Nincs megosztás, visszaszámlálás, és felhasználóra emlékezés
			*/
			$state = 6;
			// Feltöltési link összeállítása.
			$link = getBaseLink()."upload.php";
			// Indulhat a session
			createSession();
		}
	} else {
		$errorMessage = "Fill in all fields!";
	}
// Negyedik állapotban kérjük be a megosztási adatokat
} else if ($state == 4) {
	if (isset($_POST['back'])) {
		$state = 3;
	} else if (isset($_POST['next'])) {

		$sharingMethod = 0;
		foreach ($_POST['share'] as $value) {
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

		$countdown = intval($_POST['timer']);

		// Kapcsolódási adatok hozzáadása
		include '.adatbazisadatok.hozzaadas';

		// Kapcsolódás az adatbázishoz
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);

		// Kapcsolat ellenőrzése
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
		// Kapcsolat létrejött.
			// Új adat feltöltése
			$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET sharingMethod = ?, countdown = ? WHERE  id = 1";
			$stmt = $mysqli->prepare($sql);
			$stmt->bind_param("dd", $sharingMethod, $countdown);

			if (!$stmt->execute()) {
				$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
			}
			$stmt->close();
		}
		$mysqli->close();

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			$state = 5;
		}	
	}
// Ötödik állapotban állítjuk be az emlékezés határát
} else if ($state == 5) {
	if (isset($_POST['back'])) {
		$state = 4;
	} else if (isset($_POST['next']) && isset($_POST['cookies']) && !empty($_POST['cookies'])) {

		// Kapcsolódási adatok hozzáadása
		include '.adatbazisadatok.hozzaadas';

		// Kapcsolódás az adatbázishoz
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);

		// Kapcsolat ellenőrzése
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
		// Kapcsolat létrejött.
			// Új adat feltöltése
			$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET cookieTimeout = ? WHERE  id = 1";
			$stmt = $mysqli->prepare($sql);
			$stmt->bind_param("s", $_POST['cookies']);

			if (!$stmt->execute()) {
				$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
			}
			$stmt->close();
		}
		$mysqli->close();

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			$state = 6;
			// Feltöltési link összeállítása.
			$link = getBaseLink()."upload.php";
			// Indulhat a session
			createSession();
			
		}	
	}
// Hetedik állapotban választjuk ki, hogy szerkeszteni akarjuk a mentett beállításokat, vagy törölni
// akarjuk a rendszert, egy újat feltéve.
} else if ($state == 7) {
	if (isset($_POST['edit'])) {
		if (validateSession(false)) {
			$state = 8;
		} else {
			$state = 0;
		}
		// Módosítandó adatok lehívása az adatbázisból
		include '.adatbazisadatok.hozzaadas';
		$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
		if ($mysqli->connect_errno) {
			$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
		} else {
			$query = "SELECT password,email,notificationThreshold,cookieTimeout,sharingMethod,countdown FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
			if ($result = $mysqli->query($query)) {
				while ($row = $result->fetch_row()) {
					$edit_content['password'] = $row[0];
					$edit_content['email'] = $row[1];
					$edit_content['notificationThreshold'] = $row[2];
					$edit_content['cookieTimeout'] = $row[3];
					$edit_content['sharingMethod'] = $row[4];
					$edit_content['countdown'] = $row[5];
				}
				$result->close();
			}
		}
		$mysqli->close();
	} else if (isset($_POST['delete'])) {
		$state = 1;
	}
// Nyolcadik állapotban szerkeszthetjük az aktuális adatokat.
} else if ($state == 8) {

	// Módosítandó adatok lehívása az adatbázisból
	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT password,email,notificationThreshold,cookieTimeout,sharingMethod,countdown FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_row()) {
				$edit_content['password'] = $row[0];
				$edit_content['email'] = $row[1];
				$edit_content['notificationThreshold'] = $row[2];
				$edit_content['cookieTimeout'] = $row[3];
				$edit_content['sharingMethod'] = $row[4];
				$edit_content['countdown'] = $row[5];
			}
			$result->close();
		}
	}
	$mysqli->close();

	if (isset($_POST['save'])) {

		if (!validateSession(false)) {
			$state = 0;
			$emailError[] = "vmi";
		} else {
			$sharingMethod = 0;
			foreach ($_POST['share'] as $value) {
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

			include '.adatbazisadatok.hozzaadas';
			$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
			if ($mysqli->connect_errno) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
				$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET cookieTimeout = ?, notificationThreshold = ?, sharingMethod = ?, countdown = ? WHERE  id = 1";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("sddd", $_POST['cookies'], intval($_POST['notification']), $sharingMethod, intval($_POST['timer']));

				if (!$stmt->execute()) {
					$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				$stmt->close();
			}
			$mysqli->close();


			if (isset($_POST['email2']) && !empty($_POST['email2'])) {
				$email1 = $_POST['email1'];
				$email2 = $_POST['email2'];

				$emailError = "";

				if ($email1 !== $email2) {
					$emailError = "The two email addresses must be the same!";
				} else {
					if (!filter_var($email1, FILTER_VALIDATE_EMAIL)) {
						$emailError = "Invalid email format!";
					} else {
						include '.adatbazisadatok.hozzaadas';
						$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
						if ($mysqli->connect_errno) {
							$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
						} else {
							$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET email = ? WHERE  id = 1";
							$stmt = $mysqli->prepare($sql);
							$stmt->bind_param("s", $email1);

							if (!$stmt->execute()) {
								$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
							}
							$stmt->close();
						}
						$mysqli->close();
					}
				}
			}

			if (isset($_POST['password1']) && !empty($_POST['password1'])) {

				$password1 = md5($_POST['password1']);
				$password2 = md5($_POST['password2']);
				$password_old = md5($_POST['password_old']);

				$passwordError = "";

				if ($edit_content['password'] !== $password_old) {
					$passwordError = "Wrong old password!";
				} else {
					if ($password1 !== $password2) {
						$passwordError = "The two passwords must be the same!";
					} else {
						include '.adatbazisadatok.hozzaadas';
						$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
						if ($mysqli->connect_errno) {
							$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
						} else {
							$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET password = ? WHERE  id = 1";
							$stmt = $mysqli->prepare($sql);
							$stmt->bind_param("s",$password1);

							if (!$stmt->execute()) {
								$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
							}
							$stmt->close();
						}
						$mysqli->close();
					}
				}
			}
		}
	} 

	if ($emailError === "" && $passwordError === "" && $errorMessage === "") {
		$state = 6;
		// Feltöltési link összeállítása.
		$link = getBaseLink()."upload.php";
	}
} else if ($state == 9) {
	if (isset($_POST['reset']) && isset($_POST['password1']) && !empty($_POST['password1'])) {

		$password1 = md5($_POST['password1']);
		$password2 = md5($_POST['password2']);

		if ($password1 !== $password2) {
			$errorMessage = "The two passwords must be the same!";
		} else {
			include '.adatbazisadatok.hozzaadas';
			$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
			if ($mysqli->connect_errno) {
				$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
			} else {
				$sql = "UPDATE " . TABLE_NAME_SETTINGS . " SET password = ? WHERE  id = 1";
				$stmt = $mysqli->prepare($sql);
				$stmt->bind_param("s",$password1);
				if (!$stmt->execute()) {
					$errorMessage = "Internal MySQL error: (" . $stmt->errno . ") " . $stmt->error;
				}
				$stmt->close();
			}
			$mysqli->close();
		}

		// Ha nem történt semmi hiba, akkor továbblépünk a következő állapotba.
		if ($errorMessage === "") {
			$state = 10;
		}
	}
}

$no_navi = false;
// Validáció és navigálás csak abban az esetben tehető meg, ha nem a telepítési ciklusban vagyunk
if ($state != 1 && $state != 2 && $state != 3 && $state != 4 && $state != 5) {
	if (validateSession(false)) {
		validateSession(true);
		if (isset($_POST['state']) && !empty($_POST['state'])) {
			// $state = $_POST['state'];
		} else {
			$state = 7;
		}
	}
} else {
	$no_navi = true;
}

// echo $state."<br />";

?>

<!DOCTYPE html>
<html>
<head>
	<title>Setup - Acient Download System</title>
	<link rel="stylesheet" type="text/css" href="style.css">
	<meta charset='utf-8'> 
</head>
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
			<form action='setup.php' method='POST' enctype='multipart/form-data' autocomplete='off'>
				<?php 
// Elso állapot: Volt már telepítva a rendzert.
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
						<legend>Database connection</legend>
						<div class="form-info">Please provide your MySQL settings. You can get this information from your web host.</div>
						<p class="form-label">Database name</p> <input type="textfield" class="custom-input" name="databaseName" /><br />
						<p class="form-label">Database location</p> <input type="textfield" class="custom-input" name="location" /><br />
						<p class="form-label">Database username</p> <input type="textfield" class="custom-input" name="username" /><br />
						<p class="form-label">Database password</p> <input type="password" class="custom-input" name="password" /><br />
					</fieldset>
					
					<input type="submit" name="next" class="button" value="Next">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 2) {
					?>
					<fieldset>
						<legend>Password</legend>
						<div class="form-info">Type in a password that will protect your upload system.</div>
						<p class="form-label">Password</p><input type="password" class="custom-input" name="password1" /><br />
						<p class="form-label">Retype password</p><input type="password" class="custom-input" name="password2" /><br />
					</fieldset>
					
					<input type="submit" name="next" class="button" value="Next"><input type="submit" name="back" class="button" value="Back">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 3) {
					?>
					<fieldset>
						<legend>Email</legend>
						<div class="form-info">Type in your email address.</div>
						<p class="form-label">Email</p><input type="textfield" class="custom-input" name="email1" /><br />
						<p class="form-label">Retype email</p><input type="textfield" class="custom-input" name="email2" /><br />
						<div class="form-info">Do you want to get notifications about your downloads?</div>
						<select name="notification">
							<option value="0">Do not send notification.</option>
							<option value="1">Send notification after 1 download.</option>
							<option value="5">Send notification after 5 downloads.</option>
							<option value="10">Send notification after 10 downloads.</option>
							<option value="20">Send notification after 20 downloads.</option>
							<option value="50">Send notification after 50 downloads.</option>
							<option value="100">Send notification after 100 downloads.</option>
							<option value="200">Send notification after 200 downloads.</option>
							<option value="500">Send notification after 500 downloads.</option>
							<option value="1000">Send notification after 1000 downloads.</option>
							<option value="2000">Send notification after 2000 downloads.</option>
							<option value="5000">Send notification after 5000 downloads.</option>
							<option value="10000">Send notification after 10000 downloads.</option>
						</select><br />
					</fieldset>
					
					<input type="submit" name="next" class="button" value="Next"><input type="submit" name="back" class="button" value="Back">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 4) {
					?>
					<fieldset>
						<legend>Sharing</legend>
						<div class="form-info">Choose the default sharing methods and the default countdown timer value.</div>
						<div class="form-info">Don't worry. This is just the default setup. You can configure it for each individual uploads.</div>
						<input type="checkbox" name="share[]" value="facebook"><span class="form-info">Facebook</span><br />
						<input type="checkbox" name="share[]" value="twitter"><span class="form-info">Twitter</span><br />
						<input type="checkbox" name="share[]" value="google"><span class="form-info">Google+</span><br />
						<select name="timer">
							<option value="0">No countdown</option>
							<option value="10">10 seconds</option>
							<option value="20">20 seconds</option>
							<option value="30">30 seconds</option>
							<option value="40">40 seconds</option>
							<option value="50">50 seconds</option>
							<option value="60">60 seconds</option>
							<option value="70">70 seconds</option>
							<option value="80">80 seconds</option>
							<option value="90">90 seconds</option>
							<option value="100">100 seconds</option>
							<option value="110">110 seconds</option>
							<option value="120">120 seconds</option>
						</select><br />
					</fieldset>
					
					<input type="submit" name="next" class="button" value="Next"><input type="submit" name="back" class="button" value="Back">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 5) {
					?>
					<fieldset>
						<legend>Remember users</legend>
						<div class="form-info">The system can remember users who shared your site to grant them uninterrupted download for a while.</div>
						<div class="form-info">Remember users for</div>
						<select name="cookies">
							<option value="do not">Don't remember</option>
							<option value="1d">1 day</option>
							<option value="2d">2 days</option>
							<option value="3d">3 days</option>
							<option value="4d">4 days</option>
							<option value="5d">5 days</option>
							<option value="6d">6 days</option>
							<option value="1w">1 week</option>
							<option value="2w">2 weeks</option>
							<option value="3w">3 weeks</option>
							<option value="4w">4 weeks</option>
							<option value="1m">1 month</option>
							<option value="2m">2 months</option>
							<option value="3m">3 months</option>
							<option value="4m">4 months</option>
							<option value="5m">5 months</option>
							<option value="6m">6 months</option>
							<option value="7m">7 months</option>
							<option value="8m">8 months</option>
							<option value="9m">9 months</option>
							<option value="10m">10 months</option>
							<option value="11m">11 months</option>
							<option value="1y">1 year</option>
						</select><br />
					</fieldset>
					
					<input type="submit" name="next" class="button" value="Next"><input type="submit" name="back" class="button" value="Back">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 6) {
					?>
					<fieldset>
						<legend>Finish</legend>
						<div class="form-info">You are ready to go!</div>
						<div style="text-align: center; "><a href="<?php echo $link; ?>">Upload files</a></div>
					</fieldset>
					
					<?php
				} else if ($state == 7) {
					?>
					<fieldset>
						<legend>Old installation was found</legend>
						<div class="form-info">There is an old installation. What do you want to do?</div>
						<input type="submit" name="edit" class="button-short" value="Edit current setup.">
						<input type="submit" name="delete" class="button-short" value="Delete current setup and install a new one."><br />
						<div class="form-info">By deleting the current setup, your uploaded files wont disappear from the disk, but you have to run <i>upload.php</i> in FTP mode to re-register them.</div>
					</fieldset>
					
					
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 8) {
					?>

					<fieldset>
						<legend>Password</legend>
						<p class="form-edit-info">To change your password, type in the new password then the old one.</p>
						<p class="form-label">New password</p><input type="password" class="custom-input-edit" name="password1" /><br />
						<p class="form-label">Retype new password</p><input type="password" class="custom-input-edit" name="password2" /><br />

						<p class="form-label">Old password</p><input type="password" class="custom-input-edit" name="password_old" /><br />
						<p class="error"><?php echo $passwordError; ?></p>
					</fieldset>

					<fieldset>
						<legend>Email</legend>
						<p class="form-edit-info">To change your email, type in the new email.</p>
						<p class="form-label">Email</p><input type="textfield" class="custom-input-edit" name="email1" value="<?php echo $edit_content['email']; ?>" />
						<p class="form-label">Retype email</p><input type="textfield" class="custom-input-edit" name="email2" />
						<div class="error"><?php echo $emailError; ?></div>
						<p class="form-edit-info">Send notification email about downloads.</p>
						<select name="notification">
							<option value="0" <?php if($edit_content['notificationThreshold']==0){echo "selected";}?>>Do not send notification.</option>
							<option value="1" <?php if($edit_content['notificationThreshold']==1){echo "selected";}?>>Send notification after 1 download.</option>
							<option value="5" <?php if($edit_content['notificationThreshold']==5){echo "selected";}?>>Send notification after 5 downloads.</option>
							<option value="10" <?php if($edit_content['notificationThreshold']==10){echo "selected";}?>>Send notification after 10 downloads.</option>
							<option value="20" <?php if($edit_content['notificationThreshold']==20){echo "selected";}?>>Send notification after 20 downloads.</option>
							<option value="50" <?php if($edit_content['notificationThreshold']==50){echo "selected";}?>>Send notification after 50 downloads.</option>
							<option value="100" <?php if($edit_content['notificationThreshold']==100){echo "selected";}?>>Send notification after 100 downloads.</option>
							<option value="200" <?php if($edit_content['notificationThreshold']==200){echo "selected";}?>>Send notification after 200 downloads.</option>
							<option value="500" <?php if($edit_content['notificationThreshold']==500){echo "selected";}?>>Send notification after 500 downloads.</option>
							<option value="1000" <?php if($edit_content['notificationThreshold']==1000){echo "selected";}?>>Send notification after 1000 downloads.</option>
							<option value="2000" <?php if($edit_content['notificationThreshold']==2000){echo "selected";}?>>Send notification after 2000 downloads.</option>
							<option value="5000" <?php if($edit_content['notificationThreshold']==5000){echo "selected";}?>>Send notification after 5000 downloads.</option>
							<option value="10000" <?php if($edit_content['notificationThreshold']==10000){echo "selected";}?>>Send notification after 10000 downloads.</option>
						</select>
					</fieldset>

					<fieldset>
						<legend>Sharing options</legend>
						<input type="checkbox" name="share[]" value="facebook" <?php if($edit_content['sharingMethod']&4) echo "checked"; ?>><span class="form-info">Facebook</span><br />
						<input type="checkbox" name="share[]" value="twitter" <?php if($edit_content['sharingMethod']&2) echo "checked"; ?>><span class="form-info">Twitter</span><br />
						<input type="checkbox" name="share[]" value="google" <?php if($edit_content['sharingMethod']&1) echo "checked"; ?>><span class="form-info">Google+</span><br />
						<div class="form-edit-info">Countdown</div>
						<select name="timer">
							<option value="0" <?php if($edit_content['countdown']==0){echo "selected";}?>>No countdown</option>
							<option value="10" <?php if($edit_content['countdown']==10){echo "selected";}?>>10 seconds</option>
							<option value="20" <?php if($edit_content['countdown']==20){echo "selected";}?>>20 seconds</option>
							<option value="30" <?php if($edit_content['countdown']==30){echo "selected";}?>>30 seconds</option>
							<option value="40" <?php if($edit_content['countdown']==40){echo "selected";}?>>40 seconds</option>
							<option value="50" <?php if($edit_content['countdown']==50){echo "selected";}?>>50 seconds</option>
							<option value="60" <?php if($edit_content['countdown']==60){echo "selected";}?>>60 seconds</option>
							<option value="70" <?php if($edit_content['countdown']==70){echo "selected";}?>>70 seconds</option>
							<option value="80" <?php if($edit_content['countdown']==80){echo "selected";}?>>80 seconds</option>
							<option value="90" <?php if($edit_content['countdown']==90){echo "selected";}?>>90 seconds</option>
							<option value="100" <?php if($edit_content['countdown']==100){echo "selected";}?>>100 seconds</option>
							<option value="110" <?php if($edit_content['countdown']==110){echo "selected";}?>>110 seconds</option>
							<option value="120" <?php if($edit_content['countdown']==120){echo "selected";}?>>120 seconds</option>
						</select>
					</fieldset>

					<fieldset>
						<legend>Remember user</legend>
						<select name="cookies">
							<option value="do not" <?php if($edit_content['cookieTimeout']==="do not"){echo "selected";}?>>Don't remember</option>
							<option value="1d" <?php if($edit_content['cookieTimeout']==="1d"){echo "selected";}?>>1 day</option>
							<option value="2d" <?php if($edit_content['cookieTimeout']==="2d"){echo "selected";}?>>2 days</option>
							<option value="3d" <?php if($edit_content['cookieTimeout']==="3d"){echo "selected";}?>>3 days</option>
							<option value="4d" <?php if($edit_content['cookieTimeout']==="4d"){echo "selected";}?>>4 days</option>
							<option value="5d" <?php if($edit_content['cookieTimeout']==="5d"){echo "selected";}?>>5 days</option>
							<option value="6d" <?php if($edit_content['cookieTimeout']==="6d"){echo "selected";}?>>6 days</option>
							<option value="1w" <?php if($edit_content['cookieTimeout']==="1w"){echo "selected";}?>>1 week</option>
							<option value="2w" <?php if($edit_content['cookieTimeout']==="2w"){echo "selected";}?>>2 weeks</option>
							<option value="3w" <?php if($edit_content['cookieTimeout']==="3w"){echo "selected";}?>>3 weeks</option>
							<option value="4w" <?php if($edit_content['cookieTimeout']==="4w"){echo "selected";}?>>4 weeks</option>
							<option value="1m" <?php if($edit_content['cookieTimeout']==="1m"){echo "selected";}?>>1 month</option>
							<option value="2m" <?php if($edit_content['cookieTimeout']==="2m"){echo "selected";}?>>2 months</option>
							<option value="3m" <?php if($edit_content['cookieTimeout']==="3m"){echo "selected";}?>>3 months</option>
							<option value="4m" <?php if($edit_content['cookieTimeout']==="4m"){echo "selected";}?>>4 months</option>
							<option value="5m" <?php if($edit_content['cookieTimeout']==="5m"){echo "selected";}?>>5 months</option>
							<option value="6m" <?php if($edit_content['cookieTimeout']==="6m"){echo "selected";}?>>6 months</option>
							<option value="7m" <?php if($edit_content['cookieTimeout']==="7m"){echo "selected";}?>>7 months</option>
							<option value="8m" <?php if($edit_content['cookieTimeout']==="8m"){echo "selected";}?>>8 months</option>
							<option value="9m" <?php if($edit_content['cookieTimeout']==="9m"){echo "selected";}?>>9 months</option>
							<option value="10m" <?php if($edit_content['cookieTimeout']==="10m"){echo "selected";}?>>10 months</option>
							<option value="11m" <?php if($edit_content['cookieTimeout']==="11m"){echo "selected";}?>>11 months</option>
							<option value="1y" <?php if($edit_content['cookieTimeout']==="1y"){echo "selected";}?>>1 year</option>
						</select><br />
					</fieldset>


					<input type="submit" name="save" class="button" value="Save">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 9) {
					?>
					<fieldset>
						<legend>Reset password</legend>
						<div class="form-info">Reset your password</div>
						<p class="form-label">Password</p><input type="password" class="custom-input" name="password1" /><br />
						<p class="form-label">Retype password</p><input type="password" class="custom-input" name="password2" /><br />
					</fieldset>
					
					<input type="submit" name="reset" class="button" value="Reset">
					<input type="hidden" name="state" value="<?php echo $state; ?>" />
					<?php
				} else if ($state == 10) {
					?>
					<fieldset>
						<legend>Success</legend>
						<div class="form-info">Your password has been reset.</div>
						<?php $resetLink = getBaseLink()."setup.php"; ?>
						<div class="form-info">Click the link to log in:</div>
						<div style="text-align:center;"><a href="<?php echo $resetLink; ?>">Log in</a></div>
					</fieldset>
					<?php
				}
				?>	
			</form>

			<?php
			if ($errorMessage !== "") {
				?>
				<fieldset>
					<legend>Error</legend>
					<p class="error"><?php echo $errorMessage; ?></p>
				</fieldset>
				<?php
			}
			?>

		</div>
	</div>
	<div id="footer">Ancient Download System <?php echo VERSION; ?> - Copyright © <?php echo date("Y"); ?></div>
</body>
</html>