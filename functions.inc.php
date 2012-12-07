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

include '../fax/functions.inc.php';  // so we can include the same fax hook code that the standard inbound route includes (if available)

// return a list of smartroutes for menu/display
function smartroutes_list() {
	global $db;

	$sql = "SELECT * FROM smartroute ORDER BY name";
	$list = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($list)) return null;

	return $list;	
}


// return the asterisk dialplan goto destination for a specific smartroute
function smartroutes_getdest($id) {
	return array('smartroute-'.$id.',s,1');
	}
	
	
// return the database record for a given smartroute
function smartroutes_get_route($id) {
	global $db;
	
	$route = $db->getAll("SELECT * FROM smartroute where id='$id'", DB_FETCHMODE_ASSOC);
	
	if(DB::IsError($route)) {
		return null;
		}
		
	return $route[0];
	}


// return the queries for a given route	
function smartroutes_get_queries($id) {
	global $db;

	$sql = "SELECT * FROM smartroute_query where id='$id'";
	$queries = $db->getAll($sql, DB_FETCHMODE_ASSOC);	
		
	return $queries;
	}

	
// return the destinations for a given route	
function smartroutes_get_dests($id) {
	global $db;

	$sql = "SELECT * FROM smartroute_dest where id='$id'";
	$dests = $db->getAll($sql, DB_FETCHMODE_ASSOC);	
		
	return $dests;
	}
	
	
// return the name of the smartroute marked as trunk default	
function smartroutes_get_calltrackingstatus_enabled() {
	global $db;
	
	$route = $db->getRow("SELECT name FROM smartroute where trackcurrentcalls=1 LIMIT 1");
	
	if(DB::IsError($route) || count($route) == 0) {
		return "No";
		}
		
	if(!empty($route[0]) && !DB::IsError($route) && isset($route[0])) return "Yes";
	
	return "No";
	}
	
	
	
// return the name of the smartroute marked as trunk default	
function smartroutes_get_trunkdefault() {
	global $db;
	
	$route = $db->getRow("SELECT name FROM smartroute where trunkdefault=1");
	
	if(DB::IsError($route) || count($route) == 0) {
		return null;
		}
		
	if(!empty($route[0]) && !DB::IsError($route) && isset($route[0])) return $route[0];
	
	return null;
	}

	
// return the dialplan destination for the smartroute marked as trunk default	
function smartroutes_get_trunkdefault_gotoroute() {
	global $db;
	
	$route = $db->getRow("SELECT id FROM smartroute where trunkdefault=1");
	
	if(DB::IsError($route) || count($route) == 0) {
		return null;
		}
		
	if(!empty($route[0]) && !DB::IsError($route) && isset($route[0])) return smartroutes_getdest($route[0]);
	
	return null;
	}
	

// return a list of the Asterisk odbc resources defined	
function smartroutes_get_dsns() {
	$ret_dsns = array();
	
	$asterisk_dsns = smartroutes_read_config('/etc/asterisk/res_odbc.conf');	
	if(count($asterisk_dsns)) {
		$ret_dsns = array_keys($asterisk_dsns);
		}
	return $ret_dsns;
	}
	

// add a smartroute entry	
function smartroutes_add_route($name) {
	global $db;
	
	// see if that route already exists
	$route = $db->getRow("SELECT id FROM smartroute WHERE name='$name'"); 
	
	// route doesn't exist so create
	if(DB::IsError($route) || count($route)==0) {
    	sql("INSERT INTO smartroute (name) VALUES ('$name')");
		$route = $db->getRow("SELECT id FROM smartroute WHERE name='$name'");
    	sql("INSERT INTO smartroute_query (id,mainquery,use_wizard) VALUES ('$route[0]','1','1')");
		}	
		
	if(!empty($route[0]) && !DB::IsError($route)) return $route[0];
	
	return null;
	}

	
// delete a smartroute entry	
function smartroutes_del($id) {
	global $db;

	sql("DELETE FROM smartroute WHERE id='$id'");
	sql("DELETE FROM smartroute_query WHERE id='$id'");	
	sql("DELETE FROM smartroute_dest WHERE id='$id'");

	return null;
}


// save a smartroute entry
function smartroutes_save($id) {
	global $db;	

	$id = $db->escapeSimple(isset($_POST['id']) ? $_POST['id'] : '');
	if(empty($id)) return false;  

	// set default destination
	$destination = $_POST[$_POST["goto".$_POST[smartroute_default_destination]].$_POST[smartroute_default_destination]];
	$sql = " `destination` = '".$db->escapeSimple($destination)."',";
	
	// set fax destination	
	if(isset($_POST["gotoFAX"])) {
		$faxdestination = $_POST[$_POST["gotoFAX"]."FAX"];
		$sql .= " `faxdestination` = '".$db->escapeSimple($faxdestination)."',";
		}
	
	foreach ($_POST as $key => $value) {
		if($key == 'trunkdefault' && $value == '1') {
			// only one smartroute can be the trunk default so disable all 
			sql("UPDATE `smartroute` SET `trunkdefault` = '0'");			
			}
		
		switch ($key) {
			case 'faxenabled':
				// FIX true/false
				if($value == 'true') {
					$value = '1';
				}
				else {
					$value = '0';
				}
				
			case 'name':
			case 'search-type':
			case 'limitciddigits':
			case 'limitdiddigits':
			case 'dbengine':
			case 'odbc-dsn':
			case 'mysql-host':
			case 'mysql-database':
			case 'mysql-username':
			case 'mysql-password':			
			case 'privacyman':
			case 'pmmaxretries':
			case 'pmminlength':
			case 'alertinfo':
			case 'ringing':
			case 'mohclass':
			case 'description':
			case 'grppre':
			case 'delay_answer':
			case 'pricid':
			case 'destination':
			case 'faxdetection':
			case 'legacy_email':
			case 'trunkdefault':
			case 'trackcurrentcalls':
			case 'faxdetectionwait':
				$sql_value = $db->escapeSimple($value);
				$sql .= " `$key` = '$sql_value',";
				break;
			default:
			}
		}
		
	if ($sql == '') {
		return false;
		}
		
	$sql = substr($sql,0,(strlen($sql)-1)); //strip off tailing ','
	$sql_update = "UPDATE `smartroute` SET"."$sql WHERE `id` = '$id'";
	
	// UPDATE THE ACTUAL SMARTROUTE 
	sql($sql_update);
	
	// ****** now update the main query - DON'T DELETE BECAUSE WE MAY BE *LIVE* SO ONLY UPDATE MAIN QUERY
	$sql = "UPDATE `smartroute_query` SET ";
	
	if(isset($_POST['smartroute_mainquery_wizard'])) {
		if($_POST['smartroute_mainquery_wizard'] == 'yes') {
			$sql .= " use_wizard='1', ";
			}
		else {
			$sql .= " use_wizard='0', ";
			}
		}
	
	if(isset($_POST['smartroute_query_wiz_table'])) {
		$wiz_table = $db->escapeSimple(isset($_POST['smartroute_query_wiz_table'])?$_POST['smartroute_query_wiz_table'] :'');
		$wiz_findcolumn = $db->escapeSimple(isset($_POST['smartroute_query_wiz_scol'])?$_POST['smartroute_query_wiz_scol'] :'');
		$wiz_matchvar = $db->escapeSimple(isset($_POST['smartroute_query_wiz_mvar'])?$_POST['smartroute_query_wiz_mvar'] :'');
		$wiz_retcolumn = $db->escapeSimple(isset($_POST['smartroute_query_wiz_rcol'])?$_POST['smartroute_query_wiz_rcol'] :'');
		
		$sql .= "
			wiz_table='$wiz_table',
			wiz_findcolumn='$wiz_findcolumn',
			wiz_retcolumn='$wiz_retcolumn',
			wiz_matchvar='$wiz_matchvar',
			";
		}
		
	if(isset($_POST['smartroute_query_adv_sql'])) {
		$adv_query = $db->escapeSimple(isset($_POST['smartroute_query_adv_sql'][0])?$_POST['smartroute_query_adv_sql'][0] : '');
		$adv_varname1 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var1'][0])?$_POST['smartroute_query_adv_var1'][0]: '');
		$adv_varname2 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var2'][0])?$_POST['smartroute_query_adv_var2'][0]: '');
		$adv_varname3 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var3'][0])?$_POST['smartroute_query_adv_var3'][0]: '');
		$adv_varname4 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var4'][0])?$_POST['smartroute_query_adv_var4'][0]: '');
		$adv_varname5 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var5'][0])?$_POST['smartroute_query_adv_var5'][0]: '');
		
		$sql .= "
			adv_query='$adv_query',
			adv_varname1='$adv_varname1',
			adv_varname2='$adv_varname2',
			adv_varname3='$adv_varname3',
			adv_varname4='$adv_varname4',
			adv_varname5='$adv_varname5',
			";
		}	
	$sql = trim($sql); // strip trailing whitespace so we can drop the last ','	
	$sql = substr($sql,0,(strlen($sql)-1)); //strip off tailing ','		
	$sql .= " WHERE (id='$id' AND mainquery='1')";
		
	// UPDATE THE ACTUAL SMARTROUTE MAIN QUERY 
	sql($sql);
	
	// DELETE THE SECONDARY QUERIES USED TO PULL VARS/VALUES (less critical)
	sql("DELETE FROM smartroute_query WHERE id='$id' AND mainquery='0'");

	// RE-ADD THE SECONDARY QUERIES
	if(isset($_POST['smartroute_query_adv_sql'])) {
		$total = count($_POST['smartroute_query_adv_sql']); 
				
		foreach($_POST['smartroute_query_adv_sql'] as $curr_row => $query_adv_sql_row) {
			if($curr_row == 0) {
				continue; // skip main query already handled
				}
			$adv_query = $db->escapeSimple(isset($_POST['smartroute_query_adv_sql'][$curr_row])?$_POST['smartroute_query_adv_sql'][$curr_row] : '');
			$adv_varname1 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var1'][$curr_row])?$_POST['smartroute_query_adv_var1'][$curr_row]: '');
			$adv_varname2 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var2'][$curr_row])?$_POST['smartroute_query_adv_var2'][$curr_row]: '');
			$adv_varname3 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var3'][$curr_row])?$_POST['smartroute_query_adv_var3'][$curr_row]: '');
			$adv_varname4 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var4'][$curr_row])?$_POST['smartroute_query_adv_var4'][$curr_row]: '');
			$adv_varname5 = $db->escapeSimple(isset($_POST['smartroute_query_adv_var5'][$curr_row])?$_POST['smartroute_query_adv_var5'][$curr_row]: '');
			
			$sql = "INSERT INTO smartroute_query 
			(id,mainquery,use_wizard,adv_query,adv_varname1,adv_varname2,adv_varname3,adv_varname4,adv_varname5) 
			VALUES 
			('$id','0','0','$adv_query','$adv_varname1','$adv_varname2','$adv_varname3','$adv_varname4','$adv_varname5')";
		
			// UPDATE THE ACTUAL SMARTROUTE QUERY 
			sql($sql);
			}
		}	
		
	// SAVE THE DESTINATIONS
	if(isset($_POST['smartroute_dest_match'])) {
		$total = count($_POST['smartroute_dest_match']); 
		$used_matchkeys = array();
		
		foreach($_POST['smartroute_dest_match'] as $curr_row => $dest_match_row) {
			$matchkey = $db->escapeSimple(isset($_POST['smartroute_dest_match'][$curr_row])?$_POST['smartroute_dest_match'][$curr_row] : '');
			$extvar = $db->escapeSimple(isset($_POST['smartroute_dest_extvar'][$curr_row])?$_POST['smartroute_dest_extvar'][$curr_row] : '');
			$destination_set = $db->escapeSimple(isset($_POST['smartroute_dest'][$curr_row])?$_POST['smartroute_dest'][$curr_row] : '');
			$failover_set = $db->escapeSimple(isset($_POST['smartroute_faildest'][$curr_row])?$_POST['smartroute_faildest'][$curr_row] : '');
			
			$destination = $_POST[$_POST["goto".$destination_set].$destination_set];
			$failover = $_POST[$_POST["goto".$failover_set].$failover_set];
			
			$matchkey = trim($matchkey);
			if(empty($matchkey)) {
				// skip empty values
				continue;
				} 
		
			// UPDATE THE ACTUAL SMARTROUTE DESTINATION
			$row = $db->getRow("SELECT * FROM smartroute_dest WHERE id='$id' and matchkey='$matchkey'");
			
			if(count($row) == 0) {
				sql("INSERT INTO smartroute_dest (id, matchkey, extvar, destination, failover_dest) VALUES ('$id', '$matchkey', '$extvar', '$destination', '$failover')");
				} 
			else {
				sql("UPDATE smartroute_dest SET extvar='$extvar', destination='$destination', failover_dest='$failover' WHERE id='$id' and matchkey='$matchkey'");
				}
			
			$used_matchkeys[] = $matchkey;
			}
			
		// delete any destinations no longer in use
		$sql = "SELECT * FROM smartroute_dest WHERE id='$id'";
		$rows = sql($sql,"getAll",DB_FETCHMODE_ASSOC);
		
		foreach($rows as $row) {
			if(!in_array($row['matchkey'], $used_matchkeys)) {
				$delkey = $row['matchkey'];
				sql("DELETE FROM smartroute_dest WHERE (id='$id' AND matchkey='$delkey')");
				}			
			}			
		}
	else {
		// no destination matches left
		sql("DELETE FROM smartroute_dest WHERE id='$id'");
		}
	}

	
// return an array of smartroute destinations	
function smartroutes_destinations() {
	$destinations = array();
	$smartroutes = smartroutes_list();

	// first destination is static inbound routes
	// 
	// ** in case we translated a DID and want to process based on static inbound routes)
	// ** or if a smartroute is set for default trunk call processing but we want to hand off to static inbound routes
	$destinations[] = array('destination' => 'from-pstn,${EXTEN},1','description' => '* FreePBX Std Inbound Routes *');
	
	if(isset($smartroutes)) {
		foreach($smartroutes as $route){
			$destinations[] = array('destination' => 'smartroute-'.$route['id'].',${EXTEN},1','description' => $route['name']);
			}
		}
		
	if(count($destinations)) return $destinations;	
	return null;	
}



// freepbx function to check validity of destination
function smartroutes_getdestinfo($dest) {
	if (substr(trim($dest),0,11) == 'smartroute-') {
		$grp = explode(',',$dest);
		$id = substr($grp[0], 11);
		
		$smartroute_route = smartroutes_get_route($id);
		
		if (empty($smartroute_route)) {
			return array();
		} else {
			return array('description' => sprintf(_("Smartroute Name %s: "),$smartroute_route['name']),
			             'edit_url' => 'config.php?display=smartroutes&amp;action=edit&amp;id='.$smartroute_route['id'],
								  );
		}
	} else {
		return false;
	}
}


// for debugging get_config
function ob_file_callback($buffer)
{
  global $ob_file;
  fwrite($ob_file,$buffer);
}


// write the dialplan for smartroutes
function smartroutes_get_config($engine) {
	global $ext;
	global $version;
	$calltracking_enabled = false;
	$replacements = array ('\\' => '\\\\','"' => '\\"','\'' => '\\\'',' ' => '\\ ',',' => '\\,','(' => '\\(',')' => '\\)','.' => '\\.','|' => '\\|' );	
	
	if($engine != 'asterisk') {
		return;		
		}

	$app_set_16 = false;
	
	if(version_compare($version, "1.4", "gt")) {
		$asterisk_config = smartroutes_read_config('/etc/asterisk/asterisk.conf');	
		if(isset($asterisk_config['compat']['app_set'])) {
			if($asterisk_config['compat']['app_set'] == "1.6") {
				// this changes the way we "set" variables 
				// with this setting, we cannot quote variable values
				// without this setting we must quote variable values that have a comma (like ODBC multi-value return)
				$app_set_16 = true;
			
				// we only use this for the database multi-value return
				}
			else if($asterisk_config['compat']['app_set'] == "1.4") {
				$app_set_16 = true;				
				}				
			else if(version_compare($version, "1.6", "gt")) {
				$app_set_16 = true;					
				}
			}
		else if(version_compare($version, "1.6", "gt")) {
			$app_set_16 = true;					
			}		
		}
		
	// for debugging get_config
//	global $ob_file;
//	$ob_file = fopen('/tmp/smartroute.log','w');
//	ob_start('ob_file_callback');

   	// do we need to configure smartroutes to handle all trunk calls (before static inbound routes)   	
   	$trunk_default_gotoroute = smartroutes_get_trunkdefault_gotoroute();
   	if($trunk_default_gotoroute != null) {
   		// smartroutes are taking point on inbound trunk calls
   		// create a new from-trunk context
	   	$ext->add('from-trunk', '_.', '', new ext_setvar('__FROM_DID','${FILTER(0-9,${EXTEN})}'));
	   	$ext->add('from-trunk', '_.', '', new ext_setvar('__CATCHALL_DID','${FILTER(0-9,${EXTEN})}'));
	   	$ext->add('from-trunk', '_.', '', new ext_goto($trunk_default_gotoroute[0]));		
   		$ext->add('from-trunk', 'i', '', new ext_setvar('__CATCHALL_DID','${FILTER(0-9,${INVALID_EXTEN})}'));
	   	$ext->add('from-trunk', 'i', '', new ext_goto($trunk_default_gotoroute[0]));
		$ext->add('from-trunk', 'h', '', new ext_macro('hangupcall',''));
	   	// to get to the original static "inbound routes" we go to "from-pstn" (which is the sole inclusion of the existing from-trunk)		
   		}  	
		

	// *** FIRST FIX CATCHALL TO SET FROM_DID  (we need this when the catch-all is routed to smartroutes for processing - that way we can search on FROM_DID)
	// NEED THE INVALID EXTENSION MATCH FOR DID'S WITH + IN FRONT (like Level3)
	$ext->add('ext-did-catchall', 'i', '', new ext_noop('Catch-All DID Match - Found ${INVALID_EXTEN} - You probably want a DID for this.'));
   	$ext->add('ext-did-catchall', 'i', '', new ext_setvar('__CATCHALL_DID','${FILTER(0-9,${INVALID_EXTEN})}'));
	$ext->add('ext-did-catchall', 'i', '', new ext_goto('ext-did,s,1'));		
		
	// on FreePBX 2.8 ours is the first line for this context
   	$ext->add('ext-did-catchall', '_.', '', new ext_setvar('__FROM_DID','${FILTER(0-9,${EXTEN})}'));
   	// actually, FreePBX is setting FROM_DID in ext-did-001 as 's' so let's keep our own var too *** MAKE SURE TO USE _. so we can get non-numeric DID's too 
   	$ext->add('ext-did-catchall', '_.', '', new ext_setvar('__CATCHALL_DID','${FILTER(0-9,${EXTEN})}'));

	$ext->add('ext-did-catchall', 'h', '', new ext_macro('hangupcall',''));
	
	// get list of routes
	$smartroutes = smartroutes_list();
	$odbc_queries = array();
	
	if(is_array($smartroutes)) {
		foreach($smartroutes as $smartroute) {
			$id = $smartroute['id'];
			$context = "smartroute-".$smartroute['id'];
			$extension = '_X.';
			$smartroute_queries = smartroutes_get_queries($id);
			$smartroute_dests = smartroutes_get_dests($id);

			// PREP QUERIES FIRST
			if(version_compare($version, "1.6", "lt")) {
				$escapeMySQL = true;
				}
			else {
				$escapeMySQL = false;
				}
			
		    // find main query AND prepare query AND escape mysql if necessary 
		    $main_query = 0;
			foreach ($smartroute_queries as $index => &$query) {
				
				if($smartroute_queries[$index]['mainquery'] == 1 && $smartroute_queries[$index]['use_wizard'] == 1) {
					if(!empty($query['wiz_table'])) {
						// we build the query from the wizard fields
						$smartroute_queries[$index]['adv_query'] = "SELECT ".$query['wiz_retcolumn']." FROM ".$query['wiz_table']." WHERE ".$query['wiz_findcolumn']." = '\${".$query['wiz_matchvar']."}'";
						}
					else {
						$smartroute_queries[$index]['adv_query'] = ""; // blank if no query
						}
					}
				
				if($smartroute['dbengine'] == 'mysql' && $escapeMySQL) {
					// version 1.2/1.4 asterisk requires escaping inline mysql searches
					$smartroute_queries[$index]['query'] = str_replace(array_keys($replacements), array_values($replacements), $smartroute_queries[$index]['adv_query']);
					}
				else {
					$smartroute_queries[$index]['query'] = $smartroute_queries[$index]['adv_query'];
					}

				// record index of main query
				if($query['mainquery'] == '1') {
					$main_query = $index;
					}

				// save query index (how we build unique query names)
				$smartroute_queries[$index]['index'] = $index;					
				// BUILD ODBC QUERIES (and assign query names)
				// /etc/asterisk/func_odbc.conf
				if($smartroute['dbengine'] == 'odbc') {
					// create odbc query
					$odbc_query = smartroutes_create_odbc_query($smartroute, $smartroute_queries[$index]);
					// note odbc query name for this query
					$smartroute_queries[$index]['odbc_query'] = $odbc_query;

					// store to write to file when done
					$odbc_queries[] = $odbc_query;
					}
					
				if($smartroute_queries[$index]['use_wizard'] == 1) {
					$smartroute_queries[$index]['return_count'] = 1;
					$smartroute_queries[$index]['adv_varname1'] = "DBRESULT";
					}
				else {
					// count the numbers of returns needed (by counting commas before FROM
					// ------------------------------------------------------------------------------------------------------------
					// NOTE!!!: not precise because complex queries with sub-function returns will increase value (but we cap at 5)
					// ------------------------------------------------------------------------------------------------------------
					$sqlparts = explode(" FROM ", strtoupper($smartroute_queries[$index]['query']), 2);
					$smartroute_queries[$index]['return_count'] = substr_count($sqlparts[0], ",")+1;			
					// we cap at 5 returns tracked
					if($smartroute_queries[$index]['return_count'] > 5) 
						$smartroute_queries[$index]['return_count'] = 5;
						
					// make sure we have varnames for the necessary returns AND BUILD ARRAY var
					$smartroute_queries[$index]['array_var'] = "ARRAY(";
					$smartroute_queries[$index]['mysql_array_var'] = "";
					
					for($currVar = 1; $currVar <= $smartroute_queries[$index]['return_count']; $currVar++) {
						if(empty($smartroute_queries[$index]['adv_varname'.$currVar])) {
							$smartroute_queries[$index]['adv_varname'.$currVar] = "DBQUERY_RET_".$index."_".$currVar;
							}
							
						// assemble array var for assigning result of db query
						if($currVar > 1) {
							$smartroute_queries[$index]['array_var'] .= ",";
							$smartroute_queries[$index]['mysql_array_var'] .= " ";
							}						
						$smartroute_queries[$index]['array_var'] .= $smartroute_queries[$index]['adv_varname'.$currVar];
						$smartroute_queries[$index]['mysql_array_var'] .= $smartroute_queries[$index]['adv_varname'.$currVar];
						}
					$smartroute_queries[$index]['array_var'] .= ")";

					if($smartroute['dbengine'] == 'mysql' && $escapeMySQL) {
						// version 1.2/1.4 asterisk requires escaping inline mysql searches
						$smartroute_queries[$index]['mysql_array_var'] = str_replace(array_keys($replacements), array_values($replacements), $smartroute_queries[$index]['mysql_array_var']);
						}					
					}										
				}

			// =================================				
			// **** START WRITING DIALPLAN) ****
			// =================================
			// **** START WRITING DIALPLAN FOR THIS ROUTE
			// **** first start with the invalid handler (when the sip provider sends non-numeric characters in DID (like '+')
			$ext->add($context, 'i', '', new ext_noop('Smartroute invalid DID handler - FIX EXTENSION VAR'));
			// fix EXTEN and send back through the smartroute
			if($smartroute['limitdiddigits'] != "" && $smartroute['limitdiddigits'] > '0') {
				// only use the last xx digits from did (effectively stripping unnecessary prefixes)
				$ext->add($context, 'i', '', new ext_setvar('FIX_EXTEN','${INVALID_EXTEN:-'.$smartroute['limitdiddigits'].'}'));
				}		
			else {
				$ext->add($context, 'i', '', new ext_setvar('FIX_EXTEN','${INVALID_EXTEN}'));
				}

			$ext->add($context, 'i', '', new ext_setvar('TMP','0'));
			$ext->add($context, 'i', '', new ext_setvar('ANS',''));
			$ext->add($context, 'i', '', new ext_setvar('END','${LEN(${FIX_EXTEN})}'));
			$ext->add($context, 'i', 'clean_ext_loop_top', new ext_gotoif('$["${TMP}" = "${END}"]','clean_ext_done'));
			$ext->add($context, 'i', '', new ext_setvar('TCH','${FIX_EXTEN:${TMP}:1}'));
			$ext->add($context, 'i', '', new ext_gotoif('$["${TCH}" < "0"]','clean_ext_ignore_char'));
			$ext->add($context, 'i', '', new ext_gotoif('$["${TCH}" > "9"]','clean_ext_ignore_char'));
			$ext->add($context, 'i', '', new ext_setvar('ANS','${ANS}${TCH}'));
			$ext->add($context, 'i', 'clean_ext_ignore_char', new ext_setvar('TMP','$[${TMP}+1]'));
			$ext->add($context, 'i', '', new ext_goto('clean_ext_loop_top'));			
			$ext->add($context, 'i', 'clean_ext_done', new ext_setvar('FIX_EXTEN','${ANS}'));		
			$ext->add($context, 'i', '', new ext_goto('smartroute-'.$id.',${FIX_EXTEN},1'));		

			// *** now include handler in case passed extension is 's' - pull EXTEN from CATCHALL_DID
			$ext->add($context, 's', '', new ext_noop('Smartroute passed generic extension s handler - FIX EXTENSION VAR'));
			$ext->add($context, 's', '', new ext_gotoif('$["${CATCHALL_DID}" != "s" & "${CATCHALL_DID}empty" != "empty"]','smartroute-'.$id.',${CATCHALL_DID},1'));
			$ext->add($context, 's', '', new ext_gotoif('$["${FROM_DID}" != "s" & "${FROM_DID}empty" != "empty"]','smartroute-'.$id.',${FROM_DID},1'));
			$ext->add($context, 's', '', new ext_goto('smartroute-'.$id.',${CALLERID(dnid)},1'));			
			
			// proceed with standard dialplan
			$ext->add($context, $extension, '', new ext_noop('Smartroute: Start Standard Processing - DB Routing'));
			
			// if FROM_DID isn't set (OR IS SET AS 's') then set it as the EXTEN passed into this context, or the CATCHALL_DID, or the CALLERID(dnid)
			$ext->add($context, $extension, '', new ext_execif('$[ "${FROM_DID}" = "" | "${FROM_DID}" = "s"]','Set','__FROM_DID=${EXTEN}'));
			$ext->add($context, $extension, '', new ext_execif('$[ "${FROM_DID}" = "" | "${FROM_DID}" = "s"]','Set','__FROM_DID=${CATCHALL_DID}'));
			$ext->add($context, $extension, '', new ext_execif('$[ "${FROM_DID}" = "" | "${FROM_DID}" = "s"]','Set','__FROM_DID=${FILTER(0-9,${CALLERID(dnid)})}')); 
			
			if($smartroute['limitdiddigits'] != "" && $smartroute['limitdiddigits'] > '0') {
				// only use the last xx digits from did (effectively stripping unnecessary prefixes)
				$ext->add($context, $extension, '', new ext_execif('$[ "${FROM_DID}" = "" ] ','Set','__FROM_DID=${EXTEN}'));
				$ext->add($context, $extension, '', new ext_setvar('__FROM_DID','${FROM_DID:-'.$smartroute['limitdiddigits'].'}'));
				}		
			else {
				// no from_did set so use EXTEN as from_did
				$ext->add($context, $extension, '', new ext_execif('$[ "${FROM_DID}" = "" ] ','Set','__FROM_DID=${EXTEN}'));
				}			

			// *** write standard inbound route stuff
			// always set callerID name
			$ext->add($context, $extension, '', new ext_execif('$[ "${CALLERID(name)}" = "" ] ','Set','CALLERID(name)=${CALLERID(num)}'));
			
			// after setting callerID name, strip unnecessary callerID prefix (for routing and accounting purposes)
			if($smartroute['limitciddigits'] != "" && $smartroute['limitciddigits'] > '0') {
				// only use the last xx digits from caller-id (effectively stripping unnecessary prefixes)
		    	$ext->add($context, $extension, '', new ext_setvar('CALLERID(num)','${CALLERID(num):-'.$smartroute['limitciddigits'].'}'));
				}

			// ========================================================================================				
			// **** IF CALL TRACKING ENABLED THEN TRACK THIS CALL (NOW THAT DID AND CID ARE CLEAN) ****
			// ========================================================================================
			if($smartroute['trackcurrentcalls'] == '1') {
				$calltracking_enabled = true;
				$ext->add($context, $extension, '', new ext_noop('Call Tracking Enabled'));

				if($smartroute['dbengine'] == 'mysql') {
					$tracking_query = 'INSERT INTO smartroute_currentcalls (`calldate`,`clid`,`src`,`dst`,`channel`,`uniqueid`) VALUES(\'${CDR(answer)}\',\'${CDR(clid)}\',\'${CDR(src)}\',\'${CDR(dst)}\',\'${CDR(channel)}\',\'${CDR(uniqueid)}\')';
					
					if($escapeMySQL) {
						// version 1.2/1.4 asterisk requires escaping inline mysql searches
						$tracking_query = str_replace(array_keys($replacements), array_values($replacements), $tracking_query);
						}
					
					// write mysql version of query
					$ext->add($context, $extension, '', new ext_mysql_connect('connid', $smartroute['mysql-host'],  $smartroute['mysql-username'],  $smartroute['mysql-password'],  $smartroute['mysql-database']));
					$ext->add($context, $extension, '', new ext_mysql_query('resultid', 'connid', $tracking_query));
					$ext->add($context, $extension, '', new ext_mysql_disconnect('connid'));
					
					// handle cleanup
					$tracking_query = 'DELETE FROM smartroute_currentcalls WHERE `channel`=\'${CDR(channel)}\'';
					
					if($escapeMySQL) {
						// version 1.2/1.4 asterisk requires escaping inline mysql searches
						$tracking_query = str_replace(array_keys($replacements), array_values($replacements), $tracking_query);
						}
					
					// write mysql version of query
					$ext->add('macro-hangupcall', 's', '', new ext_mysql_connect('connid', $smartroute['mysql-host'],  $smartroute['mysql-username'],  $smartroute['mysql-password'],  $smartroute['mysql-database']));
					$ext->add('macro-hangupcall', 's', '', new ext_mysql_query('resultid', 'connid', $tracking_query));
					$ext->add('macro-hangupcall', 's', '', new ext_mysql_disconnect('connid'));					
					}
				else if(!empty($smartroute_queries[$main_query]['odbc_query'])) {
					// create odbc query
					$tracking_odbc = array();
					$tracking_odbc['prefix'] = "SMARTRDB";
					$tracking_odbc['dsn'] = $smartroute['odbc-dsn'];
					$tracking_odbc['label'] = strtoupper('CALLRECEIVED'.$smartroute['id']);
					$tracking_odbc['write'] = 'INSERT INTO smartroute_currentcalls (`calldate`,`clid`,`src`,`dst`,`channel`,`uniqueid`) VALUES(\'${ARG1}\',\'${SQL_ESC(${ARG2})}\',\'${ARG3}\',\'${ARG4}\',\'${SQL_ESC(${ARG5})}\',\'${ARG6}\')';

					// store to write to file when done
					$odbc_queries[] = $tracking_odbc;

					// write odbc version of insert *** NOTE - Any spaces before/after commas will insert a space in the database field				
					$ext->add($context, $extension, '', new ext_setvar('SMARTRDB_CALLRECEIVED'.$smartroute['id'].'(${CDR(start)},${CDR(clid)},${CDR(src)},${CDR(dst)},${CHANNEL},${UNIQUEID})', ''));
					
					// handle cleanup
					$tracking_odbc['prefix'] = "SMARTRDB";
					$tracking_odbc['dsn'] = $smartroute['odbc-dsn'];
					$tracking_odbc['label'] = strtoupper('CALLENDED'.$smartroute['id']);
					$tracking_odbc['write'] = 'DELETE FROM smartroute_currentcalls WHERE `uniqueid`=\'${ARG1}\'';
										
					// store to write to file when done
					$odbc_queries[] = $tracking_odbc;
					
					// write odbc version of remove
					$ext->add('macro-hangupcall', 's', '', new ext_setvar('SMARTRDB_CALLENDED'.$smartroute['id'].'(${UNIQUEID})', ''));
					}				
				}
								
			// ===================================				
			// **** STANDARD ROUTING DIALPLAN ****
			// ===================================				
			if (!empty($item['mohclass']) && trim($smartroute['mohclass']) != 'default') {
				$ext->add($context, $extension, '', new ext_setmusiconhold($smartroute['mohclass']));
				$ext->add($context, $extension, '', new ext_setvar('__MOHCLASS',$smartroute['mohclass']));
				}

			// If we require RINGING, signal it as soon as we enter.
			if ($smartroute['ringing'] === "CHECKED") {
				$ext->add($context, $extension, '', new ext_ringing(''));
				}
			if ($smartroute['delay_answer']) {
				$ext->add($context, $extension, '', new ext_wait($smartroute['delay_answer']));
				}
				
			if ($item['privacyman'] == "1") {
				$ext->add($context, $extension, '', new ext_macro('privacy-mgr',$smartroute['pmmaxretries'].','.$smartroute['pmminlength']));
				} 
			else {
				// if privacymanager is used, this is not necessary as it will not let blocked/anonymous calls through
				// otherwise, we need to save the caller presence to set it properly if we forward the call back out the pbx
				// note - the indirect table could go away as of 1.4.20 where it is fixed so that SetCallerPres can take
				// the raw format.
				//
				if(version_compare($version, "1.6", "lt")) {
					$ext->add($context, $extension, '', new ext_setvar('__CALLINGPRES_SV','${CALLINGPRES_${CALLINGPRES}}'));
					} 
				else {
					$ext->add($context, $extension, '', new ext_setvar('__CALLINGPRES_SV','${CALLERPRES()}'));
					}
				$ext->add($context, $extension, '', new ext_setcallerpres('allowed_not_screened'));
				}
			
			if (!empty($smartroute['alertinfo'])) {
				$ext->add($context, $extension, '', new ext_setvar("__ALERT_INFO", str_replace(';', '\;', $smartroute['alertinfo'])));
				}				
				
			if (!empty($smartroute['grppre'])) {
				$ext->add($context, $extension, '', new ext_setvar('_RGPREFIX', $smartroute['grppre']));
				$ext->add($context, $extension, '', new ext_setvar('CALLERID(name)','${RGPREFIX}${CALLERID(name)}'));
				}
				
			// ========================================				
			// **** STANDARD ROUTING DIALPLAN: FAX ****
			// ========================================				
			// *** FAX DIALPLAN COMPONENTS
			if (function_exists('fax_get_config') && $smartroute['faxenabled'] == 1) {
				$ext->add($context, 'fax', '', new ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));				
				
				$fax=fax_detect($version);
				if ($fax['module']) {
					$fax_settings['force_detection'] = 'yes';
					} 
				else {
					$fax_settings=fax_get_settings();
					}
				if($fax_settings['force_detection'] == 'yes'){ //dont continue unless we have a fax module in asterisk
					if ($smartroute['faxdetection'] == 'nvfax' && !$fax['nvfax']) {
						//TODO: add notificatoin to notification panel that this was skipped because NVFaxdetec not present
						// skip this one if there is no NVFaxdetect installed on this system
      					}
      				else {
						// proceed with forced fax detection
						if(!isset($smartroute['legacy_email'])) $smartroute['legacy_email'] = null;
						if ($smartroute['legacy_email'] === null) {
							$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST',str_replace(',','^',$smartroute['faxdestination'])));
							} 
						else {
							$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST','ext-fax^s^1'));
							if (!empty($smartroute['legacy_email'])) {
								$fax_rx_email = $smartroute['legacy_email'];
								} 
							else {
								if (!isset($default_fax_rx_email)) {
									$default_address = sql('SELECT value FROM fax_details WHERE `key` = \'fax_rx_email\'','getRow');
									$default_fax_rx_email = $default_address[0];
									}
								$fax_rx_email = $default_fax_rx_email;
								}
							$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_RX_EMAIL',$fax_rx_email));
							}
						$ext->splice($context, $extension, 'dest-ext', new ext_answer(''));
						if ($smartroute['faxdetection'] == 'nvfax') {
							$ext->splice($context, $extension, 'dest-ext', new ext_playtones('ring'));
							$ext->splice($context, $extension, 'dest-ext', new ext_nvfaxdetect($smartrouteroute['faxdetectionwait'].",t"));
							} 
						else {
							$ext->splice($context, $extension, 'dest-ext', new ext_wait($smartroute['faxdetectionwait']));
							}      					
      					}
					}        		
				}
			else {
				// blank the fax destination if fax disabled on this route
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST',''));
				}
				
			// ====================				
			// **** MAIN QUERY ****
			// ====================
			// **** DONE WITH STANDARD INBOUND ROUTE DIALPLAN 
			// write the main query in dialplan	
			$ext->add($context, $extension, '', new ext_noop('Smartroute: '.$smartroute['name']));
			
			$mainQueryPresent = false;
				
			if($smartroute['dbengine'] == 'mysql' && !empty($smartroute_queries[$main_query]['query'])) {
				$mainQueryPresent = true;
				
				// write mysql version of query
				$ext->add($context, $extension, '', new ext_mysql_connect('connid', $smartroute['mysql-host'],  $smartroute['mysql-username'],  $smartroute['mysql-password'],  $smartroute['mysql-database']));
				$ext->add($context, $extension, '', new ext_mysql_query('resultid', 'connid', $smartroute_queries[$main_query]['query']));
				if($smartroute_queries[$main_query]['return_count'] == 1) {
					// assign result of query to single var
					
					$ext->add($context, $extension, '', new ext_mysql_fetch('fetchid', 'resultid', $smartroute_queries[$main_query]['adv_varname1']));
					}
				else {
					// assign result of query to array var (multiple results)
					$ext->add($context, $extension, '', new ext_mysql_fetch('fetchid', 'resultid', $smartroute_queries[$main_query]['mysql_array_var']));
					}
				$ext->add($context, $extension, '', new ext_mysql_clear('resultid'));                           
				$ext->add($context, $extension, '', new ext_mysql_disconnect('connid'));
				$ext->add($context, $extension, '', new ext_execif('$[${fetchid} = 0]', 'Set', $smartroute_queries[$main_query]['adv_varname1'].'='));
				$ext->add($context, $extension, '', new ext_gotoif('$[${fetchid} = 0]','no_match_found'));
				}
			else if(!empty($smartroute_queries[$main_query]['odbc_query'])) {
				$mainQueryPresent = true;
				
				// write odbc version of query
				if($smartroute_queries[$main_query]['return_count'] == 1) {
					if(!$app_set_16 && version_compare($version, "1.8", "lt")) {
						// need to put quote around value "just in case" there's a comma
						
						// assign result of query to single var
						$ext->add($context, $extension, '', new ext_setvar($smartroute_queries[$main_query]['adv_varname1'], '"'.$smartroute_queries[$main_query]['odbc_query']['odbc_command'].'"'));
						}
					else {
						// no quotes around value
						
						// assign result of query to single var
						$ext->add($context, $extension, '', new ext_setvar($smartroute_queries[$main_query]['adv_varname1'], $smartroute_queries[$main_query]['odbc_query']['odbc_command']));
						}
					}
				else {
					if(!$app_set_16 && version_compare($version, "1.8", "lt")) {
						// need to put quote around value "just in case" there's a comma
						
						// assign result of query to array var (multiple results)
						$ext->add($context, $extension, '', new ext_setvar($smartroute_queries[$main_query]['array_var'], '"'.$smartroute_queries[$main_query]['odbc_query']['odbc_command'].'"'));
						}
					else {
						// no quotes
						
						// assign result of query to array var (multiple results)
						$ext->add($context, $extension, '', new ext_setvar($smartroute_queries[$main_query]['array_var'], $smartroute_queries[$main_query]['odbc_query']['odbc_command']));
						}
					}
				}
				
			if($mainQueryPresent) {				
				// write destination gotos
				if(!empty($smartroute_dests)) {
					// set match-type
					switch($smartroute['search-type']) {
						case 'LESSER':
							$matchtype = "<";
							break;
							
						case 'GREATER':
							$matchtype = ">";
							break;
							
						case 'EXACT':
						default:
							$matchtype = "=";
							break;
						}
						
					// if main query first var was setting a global var, strip the __ prefix
					$smartroute_queries[$main_query]['adv_varname1'] = trim($smartroute_queries[$main_query]['adv_varname1']);
					if($smartroute_queries[$main_query]['adv_varname1'][0] == '_' && $smartroute_queries[$main_query]['adv_varname1'][0] == '_') {
						$smartroute_queries[$main_query]['adv_varname1'] = substr($smartroute_queries[$main_query]['adv_varname1'], 2);
						}						
				
					// first write the goto statements (redirect to another part of this section - multiline)							
					foreach($smartroute_dests as $index => $dest) {
						$smartroute_dests[$index]['index'] = $index;
							
						if($matchtype == "=" && version_compare($version, "1.8", "lt")) {
							// values as strings
							$ext->add($context, $extension, '', new ext_gotoif('$["${'.$smartroute_queries[$main_query]['adv_varname1'].'}" '.$matchtype.' "'.$dest['matchkey'].'"]',"destination".$index));
							}
						else {
							// values as integers OR in version 1.8 never double quote values
							// asterisk 1.8 doesn't like the null values when no db match found so set var as "(nullstring)" before comparison
							$ext->add($context, $extension, '', new ext_execif('$["${'.$smartroute_queries[$main_query]['adv_varname1'].'}foo"="foo"]','Set',$smartroute_queries[$main_query]['adv_varname1'].'="NULL"'));							
							$ext->add($context, $extension, '', new ext_gotoif('$[${'.$smartroute_queries[$main_query]['adv_varname1'].'}'.$matchtype.$dest['matchkey'].']',"destination".$index));
							}
						}
					}			
				}
				
			// =============================				
			// **** DEFAULT DESTINATION ****
			// =============================				
			// write the default destination goto
			$ext->add($context, $extension, 'no_match_found', new ext_noop('No Smartroute Match: Goto Default Destination'));
			
			if(!empty($smartroute['destination'])) {
				$ext->add($context, $extension, '', new ext_goto($smartroute['destination']));
				}
			$ext->add($context, $extension, '', new ext_hangup(''));
			
			// ==============================				
			// **** MATCHED DESTINATIONS ****
			// ==============================			
			// write the destination sections
			if(!empty($smartroute_dests)) {
				// first write the goto statements (redirect to another part of this section - multiline)
				foreach($smartroute_dests as $dest) {
					$macrodestination = false;					
					
					// change destination trunks from ext-trunk to dialout-trunk
					// NOTE: will require dialout rules to be set appropriately (ex: allow international if attempting international) or call will fail
					if(strstr($dest['destination'], "ext-trunk")) {
						// fix trunk destinations to use override extension and the dialout macro
						$destparts = explode(",", $dest['destination']);
						$dest['destination'] = "Macro(dialout-trunk,".$destparts[1].",".(empty($dest['extvar'])?"${FROM_DID}":$dest['extvar']).",)";
						$macrodestination = true;
						}
						
					// also exchange EXTEN for SR_OR_EXTVAR if set FOR ANY PRIMARY DESTINATION
					// also set the actual context for any destination with an ,s, extension FOR ANY PRIMARY DESTINATION
					$dest['extvar'] = trim($dest['extvar']);
					if(!empty($dest['extvar'])) {
						$dest['destination'] = str_replace('${EXTEN}',$dest['extvar'],$dest['destination']);
						$dest_str_part_pos = strpos($dest['destination'], ',s,');
						if(is_numeric($dest_str_part_pos) ) {
							$dest['destination'] = $dest['extvar'].substr($dest['destination'],$dest_str_part_pos);
							}
						}						
					
					// set processing vars
					$ext->add($context, $extension, 'destination'.$dest['index'], new ext_setvar('SR_PRIMARY_DEST', str_replace(',','^',$dest['destination'])));
					$ext->add($context, $extension, '', new ext_setvar('SR_FAILOVER_DEST', str_replace(',','^',$dest['failover_dest'])));
					$ext->add($context, $extension, '', new ext_setvar('SR_OR_EXTVAR', $dest['extvar']));
					if($macrodestination) {
						// this tells processing section to use a macro and not goto for primary destination (allows failover)
						$ext->add($context, $extension, '', new ext_setvar('SR_MACRO', "YES"));
						$ext->add($context, $extension, '', new ext_setvar('SR_MACRO_TRUNK', $destparts[1]));
						}					
					else {
						// clear these
						$ext->add($context, $extension, '', new ext_setvar('SR_MACRO', ""));
						$ext->add($context, $extension, '', new ext_setvar('SR_MACRO_TRUNK', ""));						
						}
					$ext->add($context, $extension, '', new ext_goto("process_match_found"));
					}
				}			

			// CLEANUP *** SHOULD NOT REACH THIS LINE OF DIALPLAN CODE ***
			$ext->add($context, $extension, '', new ext_noop('Smartroute Error - should never get here.'));
			$ext->add($context, $extension, '', new ext_hangup(''));
							
			// =============================				
			// **** PROCESS MATCH FOUND ****
			// =============================			
			// write the section to process found match"
			// first write the secondary queries to pull data
			// then see if we need to use the extvar
			// finally attempt transfer to primary and if fails then attempt transfer to failover_dest
			$ext->add($context, $extension, 'process_match_found', new ext_noop('Process Match Found'));
			
			if(count($smartroute_queries) > 1) {

				// connect 1 time, run queries and then close connection afterward
				if($smartroute['dbengine'] == 'mysql') {
					$ext->add($context, $extension, '', new ext_mysql_connect('connid', $smartroute['mysql-host'],  $smartroute['mysql-username'],  $smartroute['mysql-password'],  $smartroute['mysql-database']));
					}			
				
				// start by writing secondary queries
				foreach ($smartroute_queries as $index => $query) {
					if($smartroute_queries[$index]['mainquery'] == 1) {
						// skip main query (processed above)
						continue;
						}				
					
					if($smartroute['dbengine'] == 'mysql') {
						// write mysql version of query
						$ext->add($context, $extension, '', new ext_mysql_query('resultid', 'connid', $query['query']));
						if($query['return_count'] == 1) {							
							// assign result of query to single var
							$ext->add($context, $extension, '', new ext_mysql_fetch('fetchid', 'resultid', $query['adv_varname1']));
							}
						else {
							// assign result of query to array var (multiple results)
							$ext->add($context, $extension, '', new ext_mysql_fetch('fetchid', 'resultid', $query['mysql_array_var']));					
							}
						$ext->add($context, $extension, '', new ext_mysql_clear('resultid'));                           
						}
					else {
						// write odbc version of query
						if($query['return_count'] == 1) {
							if(!$app_set_16) {
								// need to put quote around value "just in case" there's a comma
						
								// assign result of query to single var
								$ext->add($context, $extension, '', new ext_setvar($query['adv_varname1'], '"'.$query['odbc_query']['odbc_command'].'"'));
								}
							else {
								// no quotes
								// assign result of query to single var
								$ext->add($context, $extension, '', new ext_setvar($query['adv_varname1'], $query['odbc_query']['odbc_command']));
								}
							}
						else {
							if(!$app_set_16) {
								// need to put quote around value "just in case" there's a comma
								// assign result of query to array var (multiple results)
								$ext->add($context, $extension, '', new ext_setvar($query['array_var'], '"'.$query['odbc_query']['odbc_command'].'"'));
								}
							else {
								// no quotes
								// assign result of query to array var (multiple results)
								$ext->add($context, $extension, '', new ext_setvar($query['array_var'], $query['odbc_query']['odbc_command']));
								}
							}
						}
					}
	
				// connect 1 time, run queries and then close connection afterward
				if($smartroute['dbengine'] == 'mysql') {
					$ext->add($context, $extension, '', new ext_mysql_disconnect('connid'));
					}
				}			
				
			// if extvar not blank, set FROM_DID=extvar (ext-trunk will dial whatever is in FROM_DID)
			// not needed for dialout-trunk: $ext->add($context, $extension, '', new ext_execif('$["${SR_OR_EXTVAR}empty" != "empty"]', new ext_setvar('FROM_DID', '${SR_OR_EXTVAR}'));

			// when doing the actual dialing, we use the dial-out macros dialout-trunk instead of the normal ext-trunk (that doesn't allow failover)
			// if we used the normal ext-trunk, we could set FROM_DID=SR_OR_EXTVAR in order to translate to a new extension on a destination trunk
			$ext->add($context, $extension, '', new ext_gotoif('$["${SR_MACRO}empty" != "empty"]',"process_macrotrunk"));
			
			// standard goto on primary (won't allow failover
			$ext->add($context, $extension, '', new ext_goto('${CUT(SR_PRIMARY_DEST,^,1)},${CUT(SR_PRIMARY_DEST,^,2)},${CUT(SR_PRIMARY_DEST,^,3)}'));
			$ext->add($context, $extension, '', new ext_goto('${CUT(SR_FAILOVER_DEST,^,1)},${CUT(SR_FAILOVER_DEST,^,2)},${CUT(SR_FAILOVER_DEST,^,3)}'));
			$ext->add($context, $extension, '', new ext_hangup(''));			
			
			// primary trunk goto allows macro and failover
			$ext->add($context, $extension, 'process_macrotrunk', new ext_noop('Process Trunk Destination with Failover'));
			$ext->add($context, $extension, '', new ext_setvar('INTRACOMPANYROUTE', 'YES')); // necessary so macro-dialout-trunk won't set callerid to trunk default - this one is overkill but what the heck
			$ext->add($context, $extension, '', new ext_setvar('KEEPCID', 'TRUE')); // necessary so macro-dialout-trunk won't set callerid to trunk default
			$ext->add($context, $extension, '', new ext_setvar('OUTDISABLE_${SR_MACRO_TRUNK}', 'off')); // necessary so we can override disabled outbound dialing for trunk
			
			// if we failover, macro-dialout-trunk resets the callerid so we have to save it here
			$ext->add($context, $extension, '', new ext_setvar('SAVECID', '${CALLERID(number)}')); // necessary so macro-dialout-trunk won't set callerid to trunk default			
				
			$ext->add($context, $extension, '', new ext_execif('$["${SR_OR_EXTVAR}empty" = "empty"]', 'Set', 'SR_OR_EXTVAR=${FROM_DID}'));
			$ext->add($context, $extension, '', new ext_macro('dialout-trunk','${SR_MACRO_TRUNK},${SR_OR_EXTVAR},'));
			// if primary fails then go to failover destination
			// if we failover, macro-dialout-trunk resets the callerid so we have to RESTORE it here
			$ext->add($context, $extension, '', new ext_setvar('CALLERID(number)', '${SAVECID}')); // necessary so macro-dialout-trunk won't set callerid to trunk default
			
			$ext->add($context, $extension, '', new ext_goto('${CUT(SR_FAILOVER_DEST,^,1)},${CUT(SR_FAILOVER_DEST,^,2)},${CUT(SR_FAILOVER_DEST,^,3)}'));		
          	$ext->add($context, $extension, '', new ext_hangup(''));			
          	
          	// add hangup extension/state
			$ext->add($context, 'h', '', new ext_macro('hangupcall',''));          		
			}
		}
		
	if($calltracking_enabled) {
		// fix app-blackhole (and others) that skips macro-hangup (FreePBX version 2.8) 
		// - important to have disconnected calls reach hangup macro to clear call tracking
		// - first time tested call tracking, caller hungup during queue announcement and call tracking not cleared
		// announcements, ivrs, etc. fixed with hooks
		$ext->add('app-blackhole', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-blacklist', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-blacklist-add', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-blacklist-add-invalid', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-blacklist-last', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-blacklist-remove', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-pbdirectory', 'h', '', new ext_macro('hangupcall',''));	
		$ext->add('app-announcement', 'h', '', new ext_macro('hangupcall','')); // hangup during these and no macro execution	
		$ext->add('app-directory', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-echo-test', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-speakextennum', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-speakingclock', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-fmf-toggle', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-findmefollow', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('fmgrps', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('sub-fmsetcid', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-recordings', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-recordings', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-dialvm', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-vmmain', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('disa', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('disa-dial', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('sub-rgsetcid', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-speeddial', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-speeddial-set', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-queue-toggle', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('from-queue', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-dnd-off', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-dnd-on', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-dnd-toggle', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-dnd-hints', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-paging', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('cidlookup', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-callwaiting-cwoff', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-callwaiting-cwon', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-busy-off', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-busy-any', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-busy-on', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-off', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-any', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-on', 'h', '', new ext_macro('hangupcall',''));
		$ext->add('app-cf-unavailable-off', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-unavailable-on', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-cf-toggle', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-cf-hints', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-blacklist-check', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-userlogonoff', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('app-pickup', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-did', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('from-did-direct-ivr', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('ext-trunk', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('bad-number', 'h', '', new ext_macro('hangupcall',''));	
		$ext->add('sub-pincheck', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('vm-callme', 'h', '', new ext_macro('hangupcall','')); 	
		$ext->add('from-internal-additional', 'h', '', new ext_macro('hangupcall',''));	
		}		
		
	// NOW WRITE ODBC QUERY FILE
	if(count($odbc_queries) > 0) {
		smartroutes_save_odbc_funcs($odbc_queries);
		}

	// for debugging get_config
//	ob_end_flush();
//	fclose($ob_file);		
	}


// helper function for the dialplan generation code - process an individual odbc query		
function smartroutes_create_odbc_query($smartroute, $query) {
	$odbc_query = array();
	$clean_name = preg_replace("/[^a-zA-Z0-9\s]/", "_", $smartroute['name']);
	
	// build odbc query vals
	$odbc_query['prefix'] = "SMARTRDB";
	$odbc_query['dsn'] = $smartroute['odbc-dsn'];
	$odbc_query['label'] = strtoupper($clean_name.$query['index']);
	$odbc_query['orig_query'] = $query['query'];
	$odbc_query['query'] = $query['query'];	
	$odbc_query['args'] = array();
	// build the odbc command
	$odbc_query['odbc_command'] = '${'.$odbc_query['prefix']."_".$odbc_query['label']."(";
	
	// get query args and convert query to arg-based
	preg_match_all("/\{[\s]*([^:}]*)[:\}]/", $odbc_query['query'], $ast_vars);

	// preg_match returns the entire match (with brackets) in array[0] and just the matched parts in array[1]
	$ast_vars = $ast_vars[1];
	
	if(is_array($ast_vars) && count($ast_vars)) {
		// get just unique values
		$ast_vars = array_unique($ast_vars);
		$currArg = 1;
		// replace with args
		foreach($ast_vars as $index => $ast_var) {
			$arg = array();			
			$arg['arg'] = 'ARG'.$currArg;
			$arg['var'] = $ast_var;
			$arg['varnum'] = $currArg;			
			$odbc_query['args'][] = $arg;
						
			// first replace with arg (with or without ':') AND SQL_ESC
			// replace where we don't have the ':' (asterisk var substring notation)
			$odbc_query['query'] = preg_replace("/\{[\s]*".$arg['var']."[\}]/", '{SQL_ESC(\${'.$arg['arg'].'})}', $odbc_query['query']);
			// replace where we DO have the ':' (asterisk var substring notation)
			$odbc_query['query'] = preg_replace("/\{[\s]*".$arg['var']."[:]([^\}]*)[\}]/", '{SQL_ESC(\${'.$arg['arg'].':$1})}', $odbc_query['query']);
			// continue building odbc command
			if($currArg > 1) $odbc_query['odbc_command'] .= ",";
			$odbc_query['odbc_command'] .= '${'.$ast_var.'}';
			
			++$currArg;			
			}		
		}	

	// finish/close odbc command
	$odbc_query['odbc_command'] .= ")}";
	return $odbc_query;
	}

	
// read an asterisk config file into an array	
function smartroutes_read_config($config_file) {
	$config = array();
	
	// open config file for reading
    $fh = fopen($config_file, 'r');
    
    // read current values
    $section_name = "";
	while($line=fgets($fh))	{
		$line = trim($line);
		if(empty($line)) continue;
		
		// found new section
		if($line[0] == '[') {
			// this is a new odbc function
			$section_name = substr($line, 1, -1);
			
			// just in case there was a comment or something on the end and we didn't strip the end bracket
			$end_bracket = strpos($section_name, ']');
			if($end_bracket !== false) {
				$section_name = substr($section_name,0,$end_bracket);
				}
			$section_name = trim($section_name);
			$config[$section_name] = array();
			}
		else {
			$value_setting = explode("=",$line, 2);
			}
		
		if(empty($section_name)) { 
			$section_name = "general";
			}
		if(empty($value_setting) || empty($value_setting[0]) || empty($value_setting[1])) continue;
		
		$value_setting[0] = strtolower(trim($value_setting[0]));

		if(isset($value_setting[1][0])) {
			if($value_setting[1][0] == '>') {
				// some asterisk assignments use => ...  (like the filepaths in asterisk.conf)	
				$value_setting[1] = substr($value_setting[1],1);
				}
			}
		$config[$section_name][$value_setting[0]] = trim($value_setting[1]);
		}
		 
	// close config file
    fclose($fh);
    
    return $config;	
}	
	

// save the Asterisk odbc queries/funcs file /etc/asterisk/func_odbc.conf 	
function smartroutes_save_odbc_funcs($odbc_queries) {
	global $version;

	// note that readsql and writesql are required for asterisk 1.6.x and higher.  In earlier versions, read and write might be required.
	// don't forget to SQL_ESC the args in the query sql
	$odbc_config = smartroutes_read_config('/etc/asterisk/func_odbc.conf');
    
    // remove any of our previous smartroute functions
    foreach($odbc_config as $funcname => $funcdef) {
    	$funcname = trim($funcname);
    	if(strpos($funcname, "SMARTRDB") !== false) {
    		// remove this previous smartroute func
    		unset($odbc_config[$funcname]);
    		}
    	if(isset($funcdef['prefix'])) {
    		$funcdef['prefix'] = trim($funcdef['prefix']);
    		if(strpos($funcdef['prefix'],"SMARTRDB") !== false) {
	    		// remove this previous smartroute func
    			unset($odbc_config[$funcname]);    			
    			}
    		}
    	}    
    
    // prepare new functions  (overlap our functions on top of existing ones so that we don't remove anything created outside this module)
    foreach($odbc_queries as $label => $query) {    	
    	$odbc_config[$query['label']]['prefix'] = $query['prefix'];
    	$odbc_config[$query['label']]['dsn'] = $query['dsn'];
    	
    	if(isset($query['write'])) {
			if(version_compare($version, "1.6", "lt")) {
				$odbc_config[$query['label']]['write'] = $query['write'];
				}
			else {
				$odbc_config[$query['label']]['writesql'] = $query['write'];
				}   	
    		}
    	else {    	
			if(version_compare($version, "1.6", "lt")) {
				$odbc_config[$query['label']]['read'] = $query['query'];
				}
			else {
				$odbc_config[$query['label']]['readsql'] = $query['query'];
				}    	
    		}
    	}  
	
	// write the odbc config
	$output = array();
	foreach($odbc_config as $label => $settings) {
		$output[] = "[$label]";
		foreach($settings as $key => $value) {
			$output[] = "$key = $value";
			}
		$output[] = "";		
		}
	// add newline at end
	$output[] = "";
	
    // open config file for writing and truncate to zero length
	$fh = fopen('/etc/asterisk/func_odbc.conf', 'w+');		
    $output = implode("\n", $output);
    fwrite($fh, $output);
    
	// close config file
    fclose($fh);
	
}


// when a smartroute is setup for default trunk call processing, provide notification on the static inbound route page
function smartroutes_hook_core($viewing_itemid, $target_menuid) {
	$html = '';
	
	if ($target_menuid == 'did')  {	
		$trunk_default_route_name = smartroutes_get_trunkdefault();
			
		if($trunk_default_route_name != null) {
			$html = '<tr><td colspan="2"><h5><hr>';
			$html .= '<p><span style="background-color: #CCFFFF; color: black; line-height: 125%; padding:2pt;">&nbsp;<b style="color: red;">'._("Important:").'</b>&nbsp;&nbsp;'._("Inbound trunk calls first processed by SmartRoute:").' ['.$trunk_default_route_name.']&nbsp;</span></p>'."\n";				
    		$html .= '<hr></h5></td></tr>';
			}
		}

	return $html;
	}


// this will add hangup macro to announcements and ivr's if call tracking enabled	
function smartroutes_hookGet_config($engine){
	global $version;  	
	global $core_conf;  
	global $ext;	
  	
  	if(smartroutes_get_calltrackingstatus_enabled() == "Yes") {
  		// add hangup macro to ivrs and announcements
  		
  		// func from modules/announcement/functions.inc.php   		
		foreach(announcement_list() as $row) {
			$ext->add('app-announcement-'.$row['announcement_id'], 'h', '', new ext_macro('hangupcall',''));			
			}
  		// func from modules/ivr/functions.inc.php   		
		foreach(ivr_list() as $row) {
			$ext->add('ivr-'.$row['ivr_id'], 'h', '', new ext_macro('hangupcall',''));			
			}	
  		// func from modules/core/functions.inc.php
  		foreach(core_routing_list() as $row) {
  			$ext->add('outrt-'.$row['route_id'], 'h', '', new ext_macro('hangupcall',''));	
  			}
  		}
	}	
	
	
// from fax module - provide fax functions for smartroutes (like on the static inbound route pages)	
function smartroutes_fax_hook_core($viewing_itemid, $target_menuid, $smartroute){  // ejr 2-31-11 modified to pass smartroute array
  //hmm, not sure why engine_getinfo() isnt being called here?! should probobly read: $info=engine_getinfo();
  //this is what serves fax code to inbound routing
  $tabindex=null;
  $type=isset($_REQUEST['type'])?$_REQUEST['type']:'';
  $extension=isset($_REQUEST['extension'])?$_REQUEST['extension']:'';
  $cidnum=isset($_REQUEST['cidnum'])?$_REQUEST['cidnum']:'';
  $extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';

  //if were editing, get save parms. Get parms
  if ($type != 'setup'){
    if(!$extension && !$cidnum){//set $extension,$cidnum if we dont already have them
      $opts=explode('/', $extdisplay);$extension=$opts['0'];$cidnum=$opts['1'];
    }
    
    // ejr 2-31-11 modified for smartroute data
    //$fax=fax_get_incoming($extension,$cidnum);
    if($smartroute['faxenabled'] == '1') 
    	$fax = array('detection'=>$smartroute['faxdetection'], 'detectionwait'=>$smartroute['faxdetectionwait'], 'destination'=>$smartroute['faxdestination'], 'legacy_email'=>null);
    else 
    	$fax = null;
  }else{
    $fax=null;
  }
  $html='';
  if($target_menuid == 'did'){
    $fax_dahdi_faxdetect=fax_dahdi_faxdetect();
    $fax_sip_faxdetect=fax_sip_faxdetect();
    $dahdi=ast_with_dahdi()?_('Dahdi'):_('Zaptel');
    $fax_detect=fax_detect();
    $fax_settings=fax_get_settings();
    //ensure that we are using destination for both fax detect and the regular calls
    $html='<script type="text/javascript">$(document).ready(function(){
    $("input[name=Submit]").click(function(){
      if($("input[name=faxenabled]:checked").val()=="true" && !$("[name=gotoFAX]").val()){//ensure the user selected a fax destination
      alert('._('"You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to."').');return false; } }) });</script>';
    $html .= '<tr><td colspan="2"><h5>';
    $html.=_('Fax Detect');
    $html.='<hr></h5></td></tr>';
    $html.='<tr>';
    $html.='<td><a href="#" class="info">';
    $html.=_("Detect Faxes").'<span>'._("Attempt to detect faxes on this DID.")."<ul><li>"._("No: No attempts are made to auto-determine the call type; all calls sent to destination below. Use this option if this DID is used exclusively for voice OR fax.")."</li><li>"._("Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line")."</li>";
    if($fax_settings['legacy_mode'] == 'yes' || $fax['legacy_email']!==null){
      $html.='<li>'._('Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination!').'</li>';
    }
    $html.='</ul></span></a>:</td>';
    //dont allow detection to be set if we have no valid detection types
    if(!$fax_dahdi_faxdetect && !$fax_sip_faxdetect && !$fax_detect['nvfax']){
      $js="if ($(this).val() == 'true'){alert('"._('No fax detection methods found or no valid license. Faxing cannot be enabled.')."');return false;}";
      $html.='<td><input type="radio" name="faxenabled" value="false" CHECKED />No';
      $html.='<input type="radio" name="faxenabled" value="true"  onclick="'.$js.'"/>Yes</td></tr>';
      $html.='</table><table>';
    }else{
      /*
       * show detection options
       *
       * js to show/hide the detection settings. Second slide is always in a
       * callback so that we ait for the fits animation to complete before
       * playing the second
       */
      if($fax['legacy_email']===null && $fax_settings['legacy_mode'] == 'no'){
        $jsno="$('.faxdetect').slideUp();";
        $jsyes="$('.faxdetect').slideDown();";
      }else{
        $jsno="$('.faxdetect').slideUp();$('.legacyemail').slideUp();";
        $jsyes="$('.legacyemail').slideUp('400',function(){
              $('.faxdetect').slideDown()
            });";
        $jslegacy="$('.faxdest27').slideUp('400',function(){
                $('.faxdetect, .legacyemail').not($('.faxdest27')).slideDown();
            });";
      }
      $html.='<td><input type="radio" name="faxenabled" value="false" CHECKED onclick="'.$jsno.'"/>No';
      $html.='<input type="radio" name="faxenabled" value="true" '.($fax?'CHECKED':'').' onclick="'.$jsyes.'"/>Yes';
      if($fax['legacy_email']!==null || $fax_settings['legacy_mode'] == 'yes'){
        $html.='<input type="radio" name="faxenabled" value="legacy"'.($fax['legacy_email'] !== null ? ' CHECKED ':'').'onclick="'.$jslegacy.'"/>Legacy';
      }
      $html.='</td></tr>';
      $html.='</table>';
    }
    //fax detection+destinations, hidden if there is fax is disabled
    $html.='<table class=faxdetect '.($fax?'':'style="display: none;"').'>';
    $info=engine_getinfo();
    $html.='<tr><td width="156px"><a href="#" class="info">'._('Fax Detection type').'<span>'._("Type of fax detection to use.")."<ul><li>".$dahdi.": "._("use ").$dahdi._(" fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in ").$dahdi.".conf</li><li>"._("Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files")."</li><li>"._("NV Fax Detect: Use NV Fax Detection; Requires NV Fax Detect to be installed and recognized by asterisk")."</li></ul>".'.</span></a>:</td>';
    $html.='<td><select name="faxdetection" tabindex="'.++$tabindex.'">';
    //$html.='<option value="Auto"'.($faxdetection == 'auto' ? 'SELECTED' : '').'>'. _("Auto").'</option>';<li>Auto: allow the system to chose the best fax detection method</li>
    $html.='<option value="dahdi" '.($fax['detection'] == 'dahdi' ? 'SELECTED' : '').' '.($fax_dahdi_faxdetect?'':'disabled').'>'.$dahdi.'</option>';
    $html.='<option value="nvfax"'.($fax['detection'] == 'nvfax' ? 'SELECTED' : '').($fax_detect['nvfax']?'':'disabled').'>'. _("NVFax").'</option>';
    $html.='<option value="sip" '.($fax['detection'] == 'sip' ? 'SELECTED' : '').' '.((($info['version'] >= "1.6.2") && $fax_sip_faxdetect)?'':'disabled').'>'. _("Sip").'</option>';
    $html.='</select></td></tr>';
    
    $html.='<tr><td><a href="#" class="info">'._("Fax Detection Time").'<span>'._('How long to wait and try to detect fax. Please note that callers to a '.$dahdi.' channel will hear ringing for this amount of time (i.e. the system wont "answer" the call, it will just play ringing)').'.</span></a>:</td>';
    $html.='<td><select name="faxdetectionwait" tabindex="'.++$tabindex.'">';
    // ejr 2-17-11 allow zero seconds  
    if($fax['detectionwait'] == ''){$fax['detectionwait']=4;}//default wait time is 4 second
    
    // ejr 2-15-11 allow zero seconds so fax context is created but we don't wait 
    // important for trunks that dialout and connect - fax detected by dahdi after we go out over trunk - no need to wait here
    for($i=0;$i < 11; $i++){
      $html.='<option value="'.$i.'" '.($fax['detectionwait']==$i?'SELECTED':'').'>'.$i.'</option>';
    }
    $html.='</select></td></tr>';
    if($fax['legacy_email']!==null || $fax_settings['legacy_mode'] == 'yes'){
      $html.='</table>';
      $html.='<table class="legacyemail"'.($fax['legacy_email'] === null ? ' style="display: none;"':'').'>';
      $html.='<tr ><td><a href="#" class="info">'._("Fax Email Destination").'<span>'._('Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only.').'.</span></a>:</td>';
      $html.='<td><input name="legacy_email" value="'.$fax['legacy_email'].'"></td></tr>';
      $html.='</table>';
      $html.='<table class="faxdest27 faxdetect" style="display: none" >';
  }
    $html.='<tr class="faxdest"><td><a href="#" class="info">'._("Fax Destination").'<span>'._('Where to send the call if we detect that its a fax').'.</span></a>:</td>';
    $html.='<td>';
    $html.=$fax_detect?drawselects(isset($fax['destination'])?$fax['destination']:null,'FAX',false,false):'';
    $html.='</td></tr></table>';
    $html.='<table>';
  }
  return $html;

}
	

?>