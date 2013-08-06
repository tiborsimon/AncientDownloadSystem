<?php

/*
=============================================================================
#  Ancient Session v1.0 - The Custom Made PHP Session Handler               #
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
  A N C I E N T _ S E S S I O N . P H P
=============================================================================

  If you encountered the problem, when you set a session variable or a cookie
  and you want to test it a few lines below, you might noticed you can't.

  This code was created for low traffic websites, because it not responsible
  for the race conditions what may occour during simultanious file access.

  For this purpose Ancient Session provide a simple lightweight solution to
  authorize clients based on they IP address.

  Constants
  	SESSION_TIMEOUT
  	SESSION_FILE

  Functions
  	createSession()
  	validateSession($refresh)
  	checkSession()

  Ancient Session will create a file to store the active sessions.

-----------------------------------------------------------------------------
  I M P O R T A N T 
-----------------------------------------------------------------------------

  For security reasons you shold append this to your .htaccess file to prevent
  unauthorized access to the session file.

	###########################
	Options All -Indexes
	<files [YOUR SESSION FILE'S NAME]>
	order allow,deny
	deny from all
	</files>
	###########################

=============================================================================
*/

define(SESSION_TIMEOUT, 3*60);
define(SESSION_FILE, '.nyilvantartas');

/**
 * To create a session for a client call this function. It will log to the session file.
 * @author Tibor Simon
 * @version 1.0
 */
function createSession() {
	$timeout = time()+SESSION_TIMEOUT;
	$ip = md5(strrev($_SERVER['REMOTE_ADDR']));
	$current = file_get_contents(SESSION_FILE);
	if ($current === false) {
		$current = '';
	}

	if ($current === '') {
		$current .= $ip.'$'.$timeout;
	} else {
		$current .= ':'.$ip.'$'.$timeout;
	}
	file_put_contents(SESSION_FILE, $current);
}


/**
 * Check if there is a valid session for the client.
 * @author Tibor Simon
 * @version 1.0
 * @param boolean $refresh  false: just check if there is a session for the user
 *                          true: check and if succeded, refreshes the session	
 * @return boolean	true if there is a valid session for the client identified by it's IP address	
 */
function validateSession($refresh) {
	$ip = md5(strrev($_SERVER['REMOTE_ADDR']));
	// Get session file's content
	$str = file_get_contents(SESSION_FILE);
	$arr = explode(':', $str);
	$return_str = '';
	$timed_out = true;
	$found = false;
	// Looking for the IP address line by line
	$counter = 0;
	foreach ($arr as $row) {
		$r = explode('$', $row);

		// Expiration check: if not expired
		if (intval($r[1]) >= time()) {
			// If IP match was found, refresh the time if necessary
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
	// If expired, delete the row, and write back the remainings
	if ($refresh === true || $timed_out === true) file_put_contents(SESSION_FILE, $return_str);

	// If there was a not expired IP match: return true
	return (!$timed_out && $found);
}

/**
 * Check if there is a session file already.
 * @author Tibor Simon
 * @version 1.0
 * @return boolean	true if there is a session file	
 */
function checkSession() {
	return file_exists(SESSION_FILE);
}

?>