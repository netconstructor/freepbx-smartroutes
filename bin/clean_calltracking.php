#!/usr/bin/php -q
<?php
/**
 * FreePBX SmartRoutes Module
 *
 * Copyright (c) 2011, VoiceNation, LLC.
 *
 * This program is free software, distributed under the terms of
 * the GNU General Public License Version 2.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
*/

// include bootstrap
$bootstrap_settings['freepbx_auth'] = false;
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
  @include_once('/etc/asterisk/freepbx.conf');
}

if(!isset($amp_conf)) {
	// FREEPBX 2.9+ BOOTSTRAP NOT DONE
	// initialize FreePBX in pre-2.9 fashion
	initLegacyFreePBX();
}

// =============================================				
// **** OUR CODE TO CLEAN CALL TRACKING DB) ****
// =============================================
// not sure this is needed but it's a good idea either way
// could get fancy, conect to AMI and get a list of current calls to comprare but for now....
// This will remove any entries that might have been orphaned (calls over an hour old)    
// update freepbx cron job to cleanup the calltracking (as required)
$sql = "DELETE FROM smartroute_currentcalls WHERE calldate < DATE_SUB(NOW(), INTERVAL 1 HOUR)";
$db->query($sql);

exit(0);

// =============================				
// **** DONE WITH CRON TASK ****
// =============================

/********************************************************************************************************************/
/* FREEPBX PRE-2.9 INITIALIZATION/BOOTSTRAP                                                                         */
/********************************************************************************************************************/
function initLegacyFreePBX() {
	global $amportalconf;
	global $db_engine;
	global $amp_conf;
	global $asterisk_conf;
	global $db;
	global $amp_conf_defaults;
	
	// taken from freepbx-cron-scheduler (so we can connect with FreePBX asterisk DB)
	define("AMP_CONF", "/etc/amportal.conf");
	$amportalconf = AMP_CONF;

	// **** Make sure we have STDIN etc

	// from  ben-php dot net at efros dot com   at  php.net/install.unix.commandline
	if (version_compare(phpversion(),'4.3.0','<') || !defined("STDIN")) {
  		define('STDIN',fopen("php://stdin","r"));
  		define('STDOUT',fopen("php://stdout","r"));
  		define('STDERR',fopen("php://stderr","r"));
  		register_shutdown_function( create_function( '' , 'fclose(STDIN); fclose(STDOUT); fclose(STDERR); return true;' ) );
	}

	// **** Make sure we have PEAR's DB.php, and include it

	outn(_("Checking for PEAR DB.."));
	if (! @ include('DB.php')) {
  		out(_("FAILED"));
  		fatal(_("PEAR Missing"),sprintf(_("PEAR must be installed (requires DB.php). Include path: %s "), ini_get("include_path")));
	}
	out(_("OK"));

	// **** Check for amportal.conf

	outn(sprintf(_("Checking for %s "), $amportalconf)._(".."));
	if (!file_exists($amportalconf)) {
  		fatal(_("amportal.conf access problem: "),sprintf(_("The %s file does not exist, or is inaccessible"), $amportalconf));
	}
	out(_("OK"));

	// **** read amportal.conf

	outn(sprintf(_("Bootstrapping %s .."), $amportalconf));
	$amp_conf = parse_amportal_conf_bootstrap($amportalconf);
	if (count($amp_conf) == 0) {
  		fatal(_("amportal.conf parsing failure"),sprintf(_("no entries found in %s"), $amportalconf));
	}
	out(_("OK"));

	outn(sprintf(_("Parsing %s .."), $amportalconf));
	require_once($amp_conf['AMPWEBROOT']."/admin/functions.inc.php");
	$amp_conf = parse_amportal_conf($amportalconf);
	if (count($amp_conf) == 0) {
 	 	fatal(_("amportal.conf parsing failure"),sprintf(_("no entries found in %s"), $amportalconf));
	}
	out(_("OK"));

	$asterisk_conf_file = $amp_conf["ASTETCDIR"]."/asterisk.conf";
	outn(sprintf(_("Parsing %s .."), $asterisk_conf_file));
	$asterisk_conf = parse_asterisk_conf($asterisk_conf_file);
	if (count($asterisk_conf) == 0) {
  		fatal(_("asterisk.conf parsing failure"),sprintf(_("no entries found in %s"), $asterisk_conf_file));
	}
	out(_("OK"));

	// **** Connect to database

	outn(_("Connecting to database.."));

	# the engine to be used for the SQL queries,
	# if none supplied, backfall to mysql
	$db_engine = "mysql";
	if (isset($amp_conf["AMPDBENGINE"])){
  		$db_engine = $amp_conf["AMPDBENGINE"];
	}

	// Define the notification class for logging to the dashboard
	//
	$nt = notifications::create($db);

	switch ($db_engine)
	{
  	case "pgsql":
  	case "mysql":
	    /* datasource in in this style:
    	dbengine://username:password@host/database */

	    $db_user = $amp_conf["AMPDBUSER"];
	    $db_pass = $amp_conf["AMPDBPASS"];
	    $db_host = $amp_conf["AMPDBHOST"];
	    $db_name = $amp_conf["AMPDBNAME"];
	
	    $datasource = $db_engine.'://'.$db_user.':'.$db_pass.'@'.$db_host.'/'.$db_name;
	    $db = DB::connect($datasource); // attempt connection
	    break;
	
	  case "sqlite":
	    require_once('DB/sqlite.php');
	
	    if (!isset($amp_conf["AMPDBFILE"]))
	      fatal(_("AMPDBFILE not setup properly"),sprintf(_("You must setup properly AMPDBFILE in %s "), $amportalconf));
	
	    if (isset($amp_conf["AMPDBFILE"]) == "")
	      fatal(_("AMPDBFILE not setup properly"),sprintf(_("AMPDBFILE in %s cannot be blank"), $amportalconf));
	
	    $DSN = array (
	      "database" => $amp_conf["AMPDBFILE"],
	      "mode" => 0666
	    );
	
	    $db = new DB_sqlite();
	    $db->connect( $DSN );
	    break;
	
	  case "sqlite3":
	    if (!isset($amp_conf["AMPDBFILE"]))
	      fatal("You must setup properly AMPDBFILE in $amportalconf");
	
	    if (isset($amp_conf["AMPDBFILE"]) == "")
	      fatal("AMPDBFILE in $amportalconf cannot be blank");
	
	    require_once('DB/sqlite3.php');
	    $datasource = "sqlite3:///" . $amp_conf["AMPDBFILE"] . "?mode=0666";
	    $db = DB::connect($datasource);
	    break;
	
	  default:
	    fatal( "Unknown SQL engine: [$db_engine]");
	}
	
	if(DB::isError($db)) {
	  out(_("FAILED"));
	  debug($db->userinfo);
	  fatal(_("database connection failure"),("failed trying to connect to the configured database"));
	
	}
	out(_("OK"));	
}


/********************************************************************************************************************/
/* FUNCTIONS USED IN FREEPBX PRE-2.9 INITIALIZATION */
/********************************************************************************************************************/
// Emulate gettext extension functions if gettext is not available
if (!function_exists('_')) {
  function _($str) {
    return $str;
  }
}

if (!function_exists('out')) {
	function out($text) {
	  //echo $text."\n";
	}
}
	
if (!function_exists('outn')) {	
	function outn($text) {
	  //echo $text;
	}
}
	
if (!function_exists('error')) {
	function error($text) {
	  echo "[ERROR] ".$text."\n";
	}
}
	
if (!function_exists('fatal')) {
	function fatal($text, $extended_text="", $type="FATAL") {
	  global $db;
	
	  echo "[$type] ".$text." ".$extended_text."\n";
	
	  if(!DB::isError($db)) {
	    $nt = notifications::create($db);
	    $nt->add_critical('cron_manager', $type, $text, $extended_text);
	  }
	
	  exit(1);
	}
}
	
if (!function_exists('debug')) {
	function debug($text) {
	  global $debug;
	
	  if ($debug) echo "[DEBUG-preDB] ".$text."\n";
	}
}

// bootstrap retrieve_conf by getting the AMPWEBROOT since that is currently where the necessary
// functions.inc.php resides, and then use that parser to properly parse the file and get all
// the defaults as needed.
//
function parse_amportal_conf_bootstrap($filename) {
  $file = file($filename);
  foreach ($file as $line) {
    if (preg_match("/^\s*([\w]+)\s*=\s*\"?([\w\/\:\.\*\%-]*)\"?\s*([;#].*)?/",$line,$matches)) {
      $conf[ $matches[1] ] = $matches[2];
    }
  }
  if ( !isset($conf["AMPWEBROOT"]) || ($conf["AMPWEBROOT"] == "")) {
    $conf["AMPWEBROOT"] = "/var/www/html";
  } else {
    $conf["AMPWEBROOT"] = rtrim($conf["AMPWEBROOT"],'/');
  }

  return $conf;
}

/********************************************************************************************************************/


?>
