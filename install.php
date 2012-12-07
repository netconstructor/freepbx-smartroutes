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

global $db;
global $amp_conf;
global $asterisk_conf;


$sql = "CREATE TABLE IF NOT EXISTS `smartroute` (
  `id` int unsigned NOT NULL PRIMARY KEY auto_increment,
  `name` varchar(40) NOT NULL,  
  `trunkdefault` tinyint(1) default '0',  
  `destination` varchar(50) default NULL,  
  `faxenabled` tinyint(1) default NULL,  
  `faxdetection` varchar(20) default NULL,
  `faxdetectionwait` varchar(5) default NULL,
  `faxdestination` varchar(50) default NULL,
  `legacy_email` varchar(50) default NULL,
  `privacyman` tinyint(1) default NULL,
  `alertinfo` varchar(255) default NULL,
  `ringing` varchar(20) default NULL,
  `mohclass` varchar(80) NOT NULL default 'default',
  `description` varchar(80) default NULL,
  `grppre` varchar(80) default NULL,
  `delay_answer` int(2) default NULL,
  `pricid` varchar(20) default NULL,
  `pmmaxretries` varchar(2) default NULL,
  `pmminlength` varchar(2) default NULL,
  `limitciddigits` int(2) default NULL,
  `limitdiddigits` int(2) default NULL,
  `dbengine` varchar(20) default NULL,
  `odbc-dsn` varchar(60) default NULL,
  `mysql-host` varchar(60) default NULL,
  `mysql-database` varchar(64) default NULL,
  `mysql-username` varchar(16) default NULL,
  `mysql-password` varchar(60) default NULL,
  `search-type` varchar(20) default 'EXACT'
);";

$result = $db->query($sql);
if (DB::IsError($result)) {
	die_freepbx($result->getDebugInfo());
}
unset($result);



$sql = "CREATE TABLE IF NOT EXISTS `smartroute_query` (
  `id` INTEGER NOT NULL,
  `mainquery` tinyint(1) default NULL,
  `use_wizard` tinyint(1) default '1',
  `wiz_table` varchar(64) default NULL,
  `wiz_findcolumn` varchar(64) default NULL,
  `wiz_retcolumn` varchar(64) default NULL,
  `wiz_matchvar` varchar(64) default NULL,
  `adv_query` text,
  `adv_varname1` varchar(20) NOT NULL,
  `adv_varname2` varchar(20) NOT NULL,
  `adv_varname3` varchar(20) NOT NULL,
  `adv_varname4` varchar(20) NOT NULL,
  `adv_varname5` varchar(20) NOT NULL
);";

$result = $db->query($sql);
if (DB::IsError($result)) {
	die_freepbx($result->getDebugInfo());
}
unset($result);



$sql = "CREATE TABLE IF NOT EXISTS `smartroute_dest` (
  `id` INTEGER NOT NULL, 
  `matchkey` varchar(255) default NULL,
  `extvar` varchar(50) default NULL,
  `destination` varchar(50) default NULL,
  `failover_dest` varchar(50) default NULL  
);";

$result = $db->query($sql);
if (DB::IsError($result)) {
	die_freepbx($result->getDebugInfo());
}
unset($result);



$sql = "CREATE TABLE IF NOT EXISTS `smartroute_currentcalls` (
  `calldate` datetime NOT NULL default '0000-00-00 00:00:00',
  `clid` varchar(80) NOT NULL default '',
  `src` varchar(80) NOT NULL default '',
  `dst` varchar(80) NOT NULL default '',
  `channel` varchar(80) NOT NULL default '',
  `uniqueid` varchar(32) NOT NULL default ''
);";

$result = $db->query($sql);
if (DB::IsError($result)) {
	die_freepbx($result->getDebugInfo());
}
unset($result);



// upgrades for version 1.1
$sql = "ALTER IGNORE TABLE `smartroute` ADD `trunkdefault` TINYINT(1) default '0';";
$db->query($sql);

// upgrades for version 1.2
$sql = "ALTER IGNORE TABLE `smartroute` ADD `trackcurrentcalls` TINYINT(1) default '0';";
$db->query($sql);



// update freepbx cron job to cleanup the calltracking (as required)
$sql = "DELETE FROM cronmanager WHERE module = 'smartroute' AND id = 'CLEANCALLTRAK';";
$db->query($sql);

$sql = "INSERT INTO cronmanager (module, id, time, freq, lasttime, command) VALUES ('smartroute', 'CLEANCALLTRAK', '0', '1', 0, '".$amp_conf['AMPBIN']."/clean_calltracking.php' )";
$db->query($sql);

// end of file
?>
