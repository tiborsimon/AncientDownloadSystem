<?php

define(VERSION, '1.0');
define(SESSION_TIMEOUT, 3*60);
define(SESSION_FILE, '.nyilvantartas');

// Aktuális időpontot adja vissza szép formában
function getNow() {
	return date("Y-m-d H:i:s");
}

// Ha egy látogatónak nincs engedélye az oldal látogatására, akkor ez a függvény adhat neki.
function createSession() {
	$timeout = time()+SESSION_TIMEOUT;
	$ip = md5(strrev($_SERVER['REMOTE_ADDR']));
	$current = file_get_contents(SESSION_FILE);
	// echo "Beolvasott fájl: <br />";
	if ($current === false) {
		$current = '';
	}

	if ($current === '') {
		$current .= $ip.'$'.$timeout;
	} else {
		$current .= ':'.$ip.'$'.$timeout;
	}
	// var_dump($current); echo "<br />";
	file_put_contents(SESSION_FILE, $current);
}

// Beléptetési rendszerfüggvény
// Elenőrzi, hogy az adott IP című felhasználónak van-e joga folytatni a munkamenetét
// Paraméterrel állítható, hogy az ellenőrzés sikeressége után frissítse-e a visszaszámlálóját
// $refresh = false : csak ellenőrzés
// $refresh = true : ellenőrzés majd frissítés, ha kell
function validateSession($refresh) {
	$ip = md5(strrev($_SERVER['REMOTE_ADDR']));
	// Fájl tartalmának beolvasása
	$str = file_get_contents(SESSION_FILE);
	$arr = explode(':', $str);
	$return_str = '';
	$timed_out = true;
	$found = false;
	// Soronként ellenőrzés, keresünk azonos ip címet
	$counter = 0;
	foreach ($arr as $row) {
		$r = explode('$', $row);

		// Lejárat ellenőrzése: ha még nem járt le az idő
		if (intval($r[1]) >= time()) {
			// Ha pont a mi ip-nkhez tartozót fogtuk ki, akkor a frissítés függvényében tesszük be
			if ($r[0] === $ip) {
				$found = true;
				$timed_out = false;
				$timeout = ($refresh===true) ? time()+SESSION_TIMEOUT : intval($r[1]);
				$ret = $ip.'$'.$timeout;
				$return_str .= ($counter == 0)? $ret : ':'.$ret;
				continue;
			}
			$return_str .= ($counter == 0)? $row : ':'.$row;
		}
		$counter++;
	}
	// Ha időtúllépés volt, töröljük a sort és visszaírjuk
	if ($refresh === true || $timed_out === true) file_put_contents(SESSION_FILE, $return_str);

	// Ha volt egyezés és nem járt le, akkor helyesel térünk vissza
	return (!$timed_out && $found);
}

// Beléptetési rendszerfüggvény
// A nyilvántartó fájl meglétét ellenőrzi
function checkSession() {
	return file_exists(SESSION_FILE);
}

// Rendszerre mutató URL lekérése
function curPageURL() {
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

// Adatbázisból kiolvassa a mentett linket, ami a letöltési rendszerre mutat
function getBaseLink()
{
	$l = "";
	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT baseUrl FROM ". TABLE_NAME_SETTINGS ." WHERE id=1";
		if ($result = $mysqli->query($query)) {
			while ($row = $result->fetch_row()) {
				$l = $row[0];
			}
			$result->close();
		}
	}
	$mysqli->close();
	return $l;
}

// Visszaad egy listát, amiben az újonnan feltöltött fájlok neve szerepel.
function getNewFiles() {
	$newFiles;

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT fileName FROM " . TABLE_NAME_FILES;
		if ($result = $mysqli->query($query)) {
			// FILES mappa tartalmalmának listázása
			$folderContent = array_diff(scandir('FILES/'),array(".",".."));
			// Adatbázisban is regisztrált fájlok
			$registeredFiles = array();
			while ($row = $result->fetch_row()) {
				$registeredFiles[] = $row[0];
			}
			$result->close();

			// Új fájlok listáját tartalmazó lista
			$newFiles = array_diff($folderContent, $registeredFiles);
		}
	}
	$mysqli->close();
	return $newFiles;
}

// Összes regisztrált fájlról ad listát.
// Visszaadott lista tartalma:
// 		Fájl neve
//		Fájl regisztrált neve
//		Fájl leírása
// 		Elérési link
// 		Feltöltés dátuma
function getRegisteredFilesWithData() {
	$registeredFilesData;

	$link = getBaseLink();

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT fileName,name,description,hash,uploadDate FROM " . TABLE_NAME_FILES;
		if ($result = $mysqli->query($query)) {

			$registeredFilesData = array();

			$counter = 0;
			while ($row = $result->fetch_row()) {
				$registeredFilesData[$counter]['filename'] = $row[0];
				$registeredFilesData[$counter]['name'] = $row[1];
				$registeredFilesData[$counter]['description'] = $row[2];
				$registeredFilesData[$counter]['link'] = $link.$row[3];
				$registeredFilesData[$counter]['uploadDate'] = $row[4];
				$counter++;
			}
			$result->close();
		}
	}
	$mysqli->close();
	return $registeredFilesData;
}

// Letöltésekről ad egy összegzett listát.
// Visszaadott lista tartalma:
// 		Letöltés sorszáma
// 		Letöltött fájl neve
//		Letöltés dátuma
function getDownloadStats() {
	$stats;

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT ".TABLE_NAME_STATS.".id,".TABLE_NAME_FILES.".fileName,".TABLE_NAME_STATS.".date FROM ".TABLE_NAME_STATS." LEFT OUTER JOIN ".TABLE_NAME_FILES." ON ".TABLE_NAME_STATS.".hash = ".TABLE_NAME_FILES.".hash ORDER BY id DESC";
		if ($result = $mysqli->query($query)) {

			$stats = array();

			$counter = 0;
			while ($row = $result->fetch_row()) {
				$stats[$counter]['id'] = $row[0];
				$stats[$counter]['filename'] = $row[1];
				$stats[$counter]['date'] = $row[2];
				$counter++;
			}
			$result->close();
		}
	}
	$mysqli->close();
	return $stats;
}

// Fájlok letöltéséről ad egy összegzett listát.
// Visszaadott lista tartalma:
// 		Fájl neve
//		Hányszor töltötték le
function getFileStats() {
	$stats;

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$query = "SELECT ".TABLE_NAME_FILES.".fileName,COUNT(*) AS count FROM ".TABLE_NAME_STATS." LEFT OUTER JOIN ".TABLE_NAME_FILES." ON ".TABLE_NAME_STATS.".hash = ".TABLE_NAME_FILES.".hash GROUP BY ".TABLE_NAME_STATS.".hash ORDER BY count DESC";
		if ($result = $mysqli->query($query)) {

			$stats = array();

			$counter = 0;
			while ($row = $result->fetch_row()) {
				$stats[$counter]['filename'] = $row[0];
				$stats[$counter]['count'] = $row[1];
				$counter++;
			}
			$result->close();
		}
	}
	$mysqli->close();
	return $stats;
}

// Megkeres egy fájlt a kódja alapján, majd visszaadja annak adatait:
// 		Fájl neve
//		Fájl regisztrált neve
//		Fájl leírása	
function getFileWithHash($hash) {
	$ret;
	$found = false;

	include '.adatbazisadatok.hozzaadas';
	$mysqli = new mysqli(LOCATION, USERNAME, PSW, DB_NAME);
	if ($mysqli->connect_errno) {
		$errorMessage = "Failed to connect to database. Make sure you typed correctly and try again.";
	} else {
		$sql = "SELECT fileName,name,description FROM " . TABLE_NAME_FILES ." WHERE hash = ?";
		$stmt = $mysqli->prepare($sql);
		$stmt->bind_param("s", $hash);
		$stmt->execute();
		$stmt->bind_result($ret['filename'], $ret['name'], $ret['description']);
		while ($stmt->fetch()) {
			$found = true;
		}
		$stmt->close();
	}
	$mysqli->close();
	if ($found) {
		return $ret;
	} else {
		return false;
	}
}


?>