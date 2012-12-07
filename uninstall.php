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

$sql = "DROP TABLE IF EXISTS smartroute, smartroute_query, smartroute_dest, smartroute_currentcalls";
$db->query($sql);

// remove freepbx cron job to cleanup the calltracking 
$sql = "DELETE FROM cronmanager WHERE module = 'smartroute'";
$db->query($sql);

?>
