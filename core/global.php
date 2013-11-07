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
  G L O B A L . P H P
=============================================================================

  This file contains all of the shared functions for the system:

  Constants
  	VERSION
  	SESSION_TIMEOUT
  	SESSION_FILE

  Functions
  	getNow()
  	getHeader($tite)
  	getFooter()

=============================================================================
*/

include_once 'ancient_session.php';

define(VERSION, '1.1');

// Aktuális időpontot adja vissza szép formában
function getNow() {
	return date("Y-m-d H:i:s");
}

function getHeader($title) {
	echo '<!DOCTYPE html>
	<html>
	<head>
	<meta charset="utf-8"> 
	<head profile="http://www.w3.org/2005/10/profile">
	<link rel="icon" type="image/png" href="core/favicon.png" />
	<title>' . $title . '</title>
	<link rel="stylesheet" type="text/css" href="core/style.css">
	</head>';
}

function getFooter() {
	echo '<div id="footer"><a href="http://tibor-simon.com/portfolio/ancient-download-system/" target="_blank">Ancient Download System</a> ' . VERSION . ' - Copyright © ' . date("Y") .'</div>
		</body>
		</html>';
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
