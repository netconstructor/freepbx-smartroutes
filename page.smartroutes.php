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

// determine if Asterisk Addons for MySQL are installed and if ODBC support is intalled
// first check mysql
// look for /usr/lib/asterisk/modules/app_addon_sql_mysql.so
$mysqlInstalled = file_exists('/usr/lib/asterisk/modules/app_addon_sql_mysql.so');

// next check odbc
// look for /usr/lib/asterisk/modules/func_odbc.so (asterisk 1.4/1.6) or app_mysql.so (1.8+)
$odbcInstalled = (file_exists('/usr/lib/asterisk/modules/func_odbc.so') || file_exists('/usr/lib/asterisk/modules/app_mysql.so'));


$destSetNum = 0; // track destination set numbers used (important for dynamic creation - 2 per row)
$destRowsUsed = 0; // ensure we have unique row id's

$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$id = isset($_REQUEST['id'])?$_REQUEST['id']:'';
$tabindex = 0; 

if($action == 'add') {
	$id = smartroutes_add_route('new_route'); // remember to replace all spaces with underscore b/c will be in Asterisk dialplan
	needreload();
	$action = 'edit';
}
else if($action == 'edited') {
	// coming from edit screen and only two post buttons (delete or save)
	if (isset($_REQUEST['delete'])) {
		$action = 'delete';
	}
	else {
		$action = 'save';
	}
}

?>

<!-- Display right-side list of smartroutes -->
<div class="rnav"><ul>
<li><a id="<?php echo(empty($id)?'current':'nul'); ?>" href="config.php?display=smartroutes&amp;action=add">Add Route</a></li>
<?php 
	// pull list from db
	$smartroutes = smartroutes_list();
	
	if(isset($smartroutes)) {
		foreach($smartroutes as $route) {
			echo '<li><a id="'.($id==$route['id'] ? 'current':'nul').'" href="config.php?display=smartroutes&amp;action=edit&amp;id='.$route['id'].'">'.$route['name'].'</a></li>'."\n";
			}
		}
?>				
</ul></div>

<div class="content">
<!-- Process requested action -->
<?php 

switch ($action) {
	case 'edit':		
		smartroutes_edit($id);
		break;
	case 'save':
		smartroutes_save($id);		
		needreload();
		redirect_standard();
		break;
	case 'delete':
		smartroutes_del($id);
    	needreload();
    	redirect_standard();		
		break;
	default:
		break;
	}
	
if($action != 'edit') {	
		?>
		<!--  default layout if no action taken -->
		<h2><?php echo _("SmartRoutes"); ?></h2>
		<!--  Splash Screen / Instructions here -->
		<p>SmartRoutes is a module that allows you to control inbound call routing based on external database values.  Using SmartRoutes you can route incoming calls to the appropriate queue (skills based routing), you can automatically route calls from one trunk to another (sip->tdm gateway), you can set variables in the dial plan (like cdr, caller id name, etc), strip unnecessary caller-id and did prefixes from inbound calls, and much more based on the caller id, did called or other Asterisk call values.  As an example, you can route customer calls differently based on how much money they do with your business.</p>
		<?php
			$trunk_default_route_name = smartroutes_get_trunkdefault();
			
			if($trunk_default_route_name != null) {
				echo('<p><span style="background-color: #CCFFFF; color: black; line-height: 125%; padding:2pt;">&nbsp;<b style="color: red;">'._("Important:").'</b>&nbsp;&nbsp;'._("Inbound trunk calls processed by SmartRoute:").' ['.$trunk_default_route_name.']&nbsp;</span><br>'."\n");
				echo('<span style="color:gray;">To process standard inbound routes, choose destination "SmartRoutes" and specify "* FreePBX Std Inbound Routes *"</span></p>');				
				}
		?>
		 
		<b>Notes:</b>
		<ul>
		<?php if(!$mysqlInstalled) {?>
		<li><font color=DarkOrange>MySQL support cannot be confirmed in your Asterisk install.  If needed, please confirm installation.  You might try "yum install asterisk-addons" for 1.4.x or "yum install asterisk16-addons" for 1.6.x. Otherwise check <a href="http://www.voip-info.org/wiki/view/Asterisk+addon+asterisk-addons" target=_blank><font color="red">here</font></a>.</font></li>
		<?php } else { ?>
		<li><font color=DodgerBlue>MySQL support appears to be installed in your Asterisk</font></li>
		<?php }
		if(!$odbcInstalled) {?>
		<li><font color=DarkOrange>ODBC support cannot be confirmed in your Asterisk install.  If needed, please confirm installation.  Instructions for compiling Asterisk with ODBC are <a href="http://astbook.asteriskdocs.org/en/2nd_Edition/asterisk-book-html-chunk/asterisk-CHP-3.html" target="_blank"><font color="red">here</font></a>.  Instructions for configuring ODBC in linux and Asterisk are <a href="http://astbook.asteriskdocs.org/en/2nd_Edition/asterisk-book-html-chunk/installing_configuring_odbc.html" target="_blank"><font color="red">here</font></a>.</font></li>
		<?php } else { ?>
		<li><font color=DodgerBlue>ODBC support appears to be installed in your Asterisk</font></li>
		<?php } ?>
		
		<li>Digium has recommended that MySQL interaction use the more stable ODBC as opposed to Asterisk Addons MySQL commands (which have been deprecated).  For more info see: <a href="https://issues.asterisk.org/view.php?id=17964" target="_blank">this report</a> and <a href="http://forums.digium.com/viewtopic.php?f=13&t=76449" target="_blank">this post</a>.</li>
		<li>To access multiple databases, or for more advanced call routing you can have smartroutes that route to other smartroutes.  Setting the variables in the dialplan will allow database lookup values to persist from one Smartroute to the next.</li>
		<li>This module will set the FROM_DID variable in the catch-all section for inbound routes.</li>
		<li>This module works best in FreePBX 2.8 or higher (destinations are condensed to a combobox). For FreePBX versions before 2.8, use custom destinations to route calls out a trunk but note that failover destinations won't work.</li>
		<li>If you don't have your own database of telephone numbers to use for routing, the "Customer DB Module" in the FreePBX extended repository could work very well with SmartRoutes.  The Account field would be a good one to use for routing classification.  If you install that module, use the following settings:<br /><br /><b>host:</b> localhost, <b>database:</b> asterisk, <b>table:</b> customerdb, <b>search column:</b> did, <b>return column:</b> account</li>		
		</ul>
		<br />
		
		<p>The latest release of this module can be found at <a target="_blank" href="http://www.qualityansweringservice.com/anatomy-oscc/freepbx/smartroutes">SmartRoutes home</a>.</p>
		<p>For documentation, tips, hints, and applications, visit <a target="_blank" href="http://www.qualityansweringservice.com/anatomy-oscc/freepbx/smartroutes/help">help</a>.</p>
		<p>Submit a bug report at <a target="_blank" href="http://www.qualityansweringservice.com/anatomy-oscc/freepbx/smartroutes/bugs">bugs</a>.</p>
		
		<?php 
	}	

function smartroutes_edit($id) {
	global $tabindex;
	global $destSetNum; // track destination set numbers used (important for dynamic creation - 2 per row)
	global $destRowsUsed; // ensure we have unique row id's
	global $action;
	global $mysqlInstalled;
	global $odbcInstalled;
	
	$smartroute_route = smartroutes_get_route($id);
	$smartroute_queries = smartroutes_get_queries($id);
	$smartroute_dests = smartroutes_get_dests($id);
	
	if($smartroute_route == null) {
		// error retrieving route info
		return;
		}
	
	echo('<div class="content">'."\n");
	echo('<h2>'._("SmartRoutes").'</h2>'."\n");
	echo('<h3>'._("Edit Route ").$smartroute_route['name'].'</h3>'."\n");
	echo('<form name="smartroutes_routeEdit" id="smartroutes_routeEdit" action="'.$_SERVER['PHP_SELF'].'" method="post">'."\n");
	echo('<input type="hidden" name="action" value="edited" />'."\n"); 
	echo('<input type="hidden" name="display" value="smartroutes" />'."\n");
	echo('<input type="hidden" name="id" value="'.$id.'" />'."\n");
	echo('<input name="Submit" type="submit" style="display:none;" value="save" />'."\n");
	echo('<input name="delete" type="submit" value="'._("Delete").' '._("Route").' '.$smartroute_route['name'].'" />'."\n");

	$usedby = framework_display_destination_usage(smartroutes_getdest($id));
    if (!empty($usedby)) {
		echo('<br /><a href="#" class="info">'.$usedby['text'].':<span>'.$usedby['tooltip'].'</span></a>'."\n");
		}
	
	echo('<hr />'."\n");
	
	// ** setup the "identity" row
	// ****************************
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Name")."\n".'<span>');
    echo _("Define the name for this route.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");    
    	
    echo('<tr><td><a href=# class="info">'._("Route Name").'<span><br>'._("Name of this route. Should be used to describe what type of calls this route matches (for example, 'local' or 'longdistance').").'<br></span></a>:'."\n".'</td>');
    echo('<td><input type="text" '.(!empty($usedby)? 'READONLY': '').' size="20" name="name" value="'.htmlspecialchars($smartroute_route['name']).'" tabindex="'.++$tabindex.'"/></td></tr>'."\n");    
        
    echo('</table>'."\n");	
    
	// ** setup the "default trunk route" row
	// ****************************
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Default Trunk Route")."\n".'<span>');
    echo _("Default Trunk Route");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");    
    	
    echo('<tr><td colspan="2"><a href="#" class="info">'._("Select this SmartRoute as the primary handler for trunk calls?").'<span>'._("Should this SmartRoute be the iniital route for processing trunk calls?  (Note: Will bypass static routes - but can be sent to static routes as destination below).").'</span></a>:&nbsp;');
    echo('<select name="trunkdefault" tabindex="'.$tabindex++.'"><option value="1" '.($smartroute_route['trunkdefault'] == "1"? 'SELECTED':'').' >Yes</option><option value="0" '.($smartroute_route['trunkdefault'] != "1"? 'SELECTED':'').' >No</option></select></td></tr>'."\n");
    
	if($smartroute_route['trunkdefault'] == "1") {
		echo('<tr><td colspan="2"><br><span style="background-color: #CCFFFF; color: black; line-height: 125%; padding:2pt;">&nbsp;<b style="color: red;">'._("Important:").'</b>&nbsp;&nbsp;'._("Inbound trunk calls processed by this SmartRoute").'&nbsp;</span><br>'."\n");
		echo('<span style="color:gray;">To process standard inbound routes, choose destination "SmartRoutes" and specify "* FreePBX Std Inbound Routes *"</span></td></tr>'."\n");				
		}    
            
    echo('</table>'."\n");
    	
	
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Queries")."\n".'<span>');
    echo _("Queries will search an external database for information about the call to facilitate routing.  The first query is the \"key\" query used to determine how the call is routed.  If a match is found then we can perform other queries to set Asterisk dialplan vars before routing.  Note: Leave the main query empty to simply route to the destination.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");
    
    // find main query
    $main_query = 0;
	foreach ($smartroute_queries as $index => $query) {
		if($query['mainquery'] == '1') {
			$main_query = $index;
			break;
			}
		}
	
    // output the first *main* query - this one is static and switchable between wizard/advanced
	echo('<tr><td colspan="2">'."\n");
	
	echo('<b><a href=# class="info">'._("Main Query").'<span><br>'._("This query is used to match a destination in the destination set below.<br><br>The main query can use a wizard where you specify the table, column name for lookup, the Asterisk variable to match in the table, and the column to return. For example, you could specify the table name for a table that includes DIDs, specify the DID column, choose the FROM_DID variable and it will find the row for the incoming call DID and return the value from the specified column to use for matching routes below.<br><br>For complicated queries you can use the advanced mode and enter your own SQL with multiple returns.  The returns will be assigned to the Asterisk vars specified so that they can be used in the dialplan (or replace/update existing Asterisk dialplan vars). Note that in advanced mode, the first value returned will be used as the key for destination match lookups.  If you leave the main query empty then the call will automatically route to the default route.").'<br></span></a></b> - <a id="smartroutes_aw_switch" href="javascript:" style="cursor:pointer">'.($smartroute_queries[$main_query]['use_wizard']==1?_("Switch to Advanced"):_("Switch to Wizard")).'</a>'."\n");
	echo('<input type="hidden" name="smartroute_mainquery_wizard" value="'.($smartroute_queries[$main_query]['use_wizard']==1?_("yes"):_("no")).'" />'."\n");	
	
	echo('<div id="smartroutes_wiz_query" '.($smartroute_queries[$main_query]['use_wizard']==1?'':'style="display:none;"').'><table>'."\n");
	echo('<tr><td><a href=# class="info">'._("Table").'<span><br>'._("Enter the name of the table to search.").'<br></span></a></td><td><a href=# class="info">'._("Search Column").'<span><br>'._("Enter the name of the column to search.").'<br></span></a></td><td><a href=# class="info">'._("Look For").'<span><br>'._("What value are we looking for?").'<br></span></a></td><td><a href=# class="info">'._("Return Column").'<span><br>'._("What column should be returned when a match is found?  Note that this value will be assigned to the Asterisk var DBRESULT.").'<br></span></a></td></tr>'."\n");
	echo('<tr><nobr><td><input type="text" class="smartroute_query_wiz_table" name="smartroute_query_wiz_table" value="'.$smartroute_queries[$main_query]['wiz_table'].'" tabindex="'.$tabindex++.'" /></td>');
	echo('<td><input type="text" class="smartroute_query_wiz_scol" name="smartroute_query_wiz_scol" value="'.$smartroute_queries[$main_query]['wiz_findcolumn'].'" tabindex="'.$tabindex++.'" /></td>');
    echo('<td><select name="smartroute_query_wiz_mvar" tabindex="'.$tabindex++.'"><option value="EXTEN" '.($smartroute_queries[$main_query]['wiz_matchvar'] == "EXTEN"? 'SELECTED':'').' >Extension</option><option value="FROM_DID" '.($smartroute_queries[$main_query]['wiz_matchvar'] == "FROM_DID"? 'SELECTED':'').' >Called Number (DID)</option><option value="CALLERID(num)" '.($smartroute_queries[$main_query]['wiz_matchvar'] == "CALLERID(num)"? 'SELECTED':'').' >Caller ID</option></select></td>'."\n");
	echo('<td><input type="text" class="smartroute_query_wiz_rcol" name="smartroute_query_wiz_rcol" value="'.$smartroute_queries[$main_query]['wiz_retcolumn'].'" tabindex="'.$tabindex++.'" /></td></nobr></tr>');
	echo('</table></div>'."\n");
	
	echo('<div id="smartroutes_adv_query" '.($smartroute_queries[$main_query]['use_wizard']==1?'style="display:none;"':'').'><table>'."\n");
	echo('<tr><td><a href=# class="info">'._("SQL").'<span><br>'._("Enter an SQL query to use.  You can return up to 5 columns and they will be assigned to the associated Asterisk vars.  The first column returned will be the key used to match a destination below.  The other columns can be assigned to Asterisk dialplan vars for use in the dialplan, as a CID name prefix, recorded into the CDR, etc.<br><br>Note that any Asterisk vars in the query need to be indicated as they are in Asterisk with '\${asteriskvar}'.<br><br>Tip: SQL \"SELECT ('\${var}')\" will allow an Asterisk variable to be assigned to a new var (specified as one of the five) or be used as the destination match criteria.").'<br></span></a></td><td><a href=# class="info">'._("Assign AST Vars 1-5").'<span><br>'._("<b>Note: The first value returned is used for the destination match.</b><br><br>Up to five results from the query can be assigned to Asterisk dialplan vars. Examples would be: CALLERID(name), CDR(userfield), OUTCID_2, or a custom variable that you put in the FreePBX Caller ID prefix field.<br><br>Note that you can leave a return value blank and not assign a var.<br><br>Note that the vars entered here do not need to be formatted as Asterisk vars but just include the var name.").'<br></span></a></td></tr>'."\n");
	echo('<tr><td><input type="text" class="smartroute_query_adv_sql[0]" name="smartroute_query_adv_sql[0]" value="'.$smartroute_queries[$main_query]['adv_query'].'" tabindex="'.$tabindex++.'" /></td>');
	echo('<td><nobr><input type="text" class="smartroute_query_adv_var1[0]" name="smartroute_query_adv_var1[0]" value="'.$smartroute_queries[$main_query]['adv_varname1'].'" tabindex="'.$tabindex++.'" />');
	echo('<input type="text" class="smartroute_query_adv_var2[0]" name="smartroute_query_adv_var2[0]" value="'.$smartroute_queries[$main_query]['adv_varname2'].'" tabindex="'.$tabindex++.'" />');
	echo('<input type="text" class="smartroute_query_adv_var3[0]" name="smartroute_query_adv_var3[0]" value="'.$smartroute_queries[$main_query]['adv_varname3'].'" tabindex="'.$tabindex++.'" />');
	echo('<input type="text" class="smartroute_query_adv_var4[0]" name="smartroute_query_adv_var4[0]" value="'.$smartroute_queries[$main_query]['adv_varname4'].'" tabindex="'.$tabindex++.'" />');
	echo('<input type="text" class="smartroute_query_adv_var5[0]" name="smartroute_query_adv_var5[0]" value="'.$smartroute_queries[$main_query]['adv_varname5'].'" tabindex="'.$tabindex++.'" /></nobr></td></tr>');		
	echo('</table></div>'."\n");
	echo('</td></tr>'."\n");
	     
    echo('<tr><td colspan="2"><div class="smartroutes_setvar_query"><table>'."\n");
	echo('<br><b><a href=# class="info"><nobr>'._("Data Queries").'</nobr><span><br>'._("These queries are used to pull data related to this call or route that can be used in the routing or for other purposes (like setting CDR fields or caller id name prefix).<br><br>Enter your own SQL with multiple returns (up to 5 returns per query).  The returns will be assigned to the Asterisk vars specified so that they can be used in the dialplan (or replace/update existing Asterisk dialplan vars).<br><br>These queries are only performed if a match is found on the first return from the main query against destination match values below.").'<br></span></a></b>'."\n");    
	echo('<tr id="smartroutes_querylabels"><td style="padding-left: 18px;"><a href=# class="info">'._("SQL").'<span><br>'._("Enter an SQL query to use.  You can return up to 5 columns and they will be assigned to the associated Asterisk vars.  The first column returned will be the key used to match a destination below.  The other columns can be assigned to Asterisk dialplan vars for use in the dialplan, as a CID name prefix, recorded into the CDR, etc. <br><br>Note that any Asterisk vars in the query need to be indicated as they are in Asterisk with '\${asteriskvar}'.").'<br></span></a></td><td><a href=# class="info">'._("Assign AST Vars 1-5").'<span><br>'._("Up to five results from the query can be assigned to Asterisk dialplan vars. Examples would be: CALLERID(name), CDR(userfield), OUTCID_2, or a custom variable that you put in the FreePBX Caller ID prefix field.<br><br>Note that you can leave a return value blank and not assign a var.<br><br>Note that the vars entered here do not need to be formatted as Asterisk vars but just include the var name.").'<br></span></a></td></tr>'."\n");
    
	$query_row = 0; // 0 IS FOR THE ADVANCED VERSION OF MAIN QUERY (but immediately increased to 1)
	foreach ($smartroute_queries as $query) {
    	$query_row++;
    	
		if($query['mainquery'] == '1') {
			// these are the secondary queries - ignore this one
			continue;
			}		
    
		// uses js function queryRemove(row)
		echo('<tr><td><nobr><img src="'.$_SERVER['PHP_SELF'].'?handler=file&module=smartroutes&file=trash.png.php" style="float:none; margin-left:0px; margin-bottom:-3px; cursor:pointer;" alt="'._("remove").'" title="'._('Click here to remove this query').'" onclick="queryRemove('._("$query_row").')">');
		echo('<input type="text" class="smartroute_query_adv_sql" id="smartroute_query_adv_sql_'.$query_row.'" name="smartroute_query_adv_sql['.$query_row.']" value="'.$query['adv_query'].'" tabindex="'.$tabindex++.'" /></nobr></td>');
		
		echo('<td><nobr><input type="text" class="smartroute_query_adv_var1" id="smartroute_query_adv_var1_'.$query_row.'" name="smartroute_query_adv_var1['.$query_row.']" value="'.$query['adv_varname1'].'" tabindex="'.$tabindex++.'" />');
		echo('<input type="text" class="smartroute_query_adv_var2" id="smartroute_query_adv_var2_'.$query_row.'" name="smartroute_query_adv_var2['.$query_row.']" value="'.$query['adv_varname2'].'" tabindex="'.$tabindex++.'" />');
		echo('<input type="text" class="smartroute_query_adv_var3" id="smartroute_query_adv_var3_'.$query_row.'" name="smartroute_query_adv_var3['.$query_row.']" value="'.$query['adv_varname3'].'" tabindex="'.$tabindex++.'" />');
		echo('<input type="text" class="smartroute_query_adv_var4" id="smartroute_query_adv_var4_'.$query_row.'" name="smartroute_query_adv_var4['.$query_row.']" value="'.$query['adv_varname4'].'" tabindex="'.$tabindex++.'" />');
		echo('<input type="text" class="smartroute_query_adv_var5" id="smartroute_query_adv_var5_'.$query_row.'" name="smartroute_query_adv_var5['.$query_row.']" value="'.$query['adv_varname5'].'" tabindex="'.$tabindex++.'" />');		

      	echo('</nobr></td></tr>'."\n");
		}
    echo('<tr id="last_query_row"></tr>'."\n");		
	echo('</table></div><td></tr>'."\n");
	
    echo('<tr><td colspan="2"><input type="button" id="smartroutes-query-add"  value="'._("+ Add Var Lookup/Assignment Query").'" /></td></tr>'."\n");
    echo('</table>'."\n");
    	
	$tabindex += 2000; // make room for dynamic insertion of new fields
    
	// *** setup the destination rows
	// ******************************

	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Destinations")."\n".'<span>');
    echo _("Destinations will match the first value returned by the first query to determine destination.  You can redirect the call to a new number (from DB lookup) using the extension variable.  If the primary destination is not available then the call will roll to the failover destination.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");
    
    echo('<tr><td colspan="2"><a href=# class="info">'._("Match Type").'<span><br>'._("When comparing the main query \"key\" and matching a destination, what type of operator should we use?  EXACT looks for a perfect match. GREATER looks for the first match where the key is greater than the match.  LESSER looks for the the first match where the key is lesser than the match.").'<br></span></a>:'."\n");
    echo('<nobr><select name="search-type" tabindex="'.$tabindex++.'"><option value="EXACT" '.($smartroute_route['search-type'] == "EXACT"? 'SELECTED':'').' >EXACT</option><option value="GREATER" '.($smartroute_route['search-type'] == "GREATER"? 'SELECTED':'').' >GREATER</option><option value="LESSER" '.($smartroute_route['search-type'] == "LESSER"? 'SELECTED':'').' >LESSER</option></select>&nbsp;>>>&nbsp;First column returned in main query</nobr></td></tr>'."\n");
    echo('<tr><td colspan="2">&nbsp;</td></tr>'."\n");
    
    echo('<tr><td colspan="2"><div class="smartroutes_dest"><table>'."\n");
	echo('<tr id="smartroutes_destlabels"><td style="padding-left: 18px;"><a href=# class="info">'._("Match Value").'<span><br>'._("The value returned from the main query that this route defines.").'<br></span></a></td><td><a href=# class="info">'._("Override Primary<br>Extension/Context").'<span><br>'._("(optional) When destination normally passes '\${EXTEN}' in the Asterisk dialplan goto call, allow specifing new destination DID value or variable.  If the destination normally passes the 's' extension then we will replace the primary context with this variable or value (the specific target of the type selected is irrelevant because we'll override it).  This feature only applies to the primary destination.  To use this capability for failover, have failover go to another smartroute where the primary destination is the failover destination with a primary override extension/context.<br><br>Note that any Asterisk vars used here need to be indicated in Asterisk value notation with '\${asteriskvar}'.<br><br>Example extension translation uses: Custom Contexts, Smartroutes, Trunks (FreePBX 2.8+), and the FreePBX Std Inbound Routes.<br><br>Example context translation uses: IVR, and Announcements.").'<br></span></a></td><td><a href=# class="info">'._("Destination").'<span><br>'._("Primary destination for this route.").'<br></span></a></td><td><a href=# class="info">'._("Failover").'<span><br>'._("When primary destination is a *trunk* that is unable to connect, use this destination.  Note: This feature requires FreePBX version 2.8 or higher. ").'<br></span></a></td></tr>'."\n");
	
	// get freepbx version
	$installed_ver = getversion();
	
	foreach ($smartroute_dests as $dest) {
    	$destRowsUsed++;  	
    	
		// add <hr> if FreePBX version less than 2.8 - drawselect is a group of radio boxes and not a combo-box
		if(version_compare_freepbx($installed_ver, "2.8","lt")) {
			echo('<tr><td colspan=4><hr></td></tr>');
			}
    	
    	// uses js function queryRemove(row)
		echo('<tr><td valign="top"><nobr><img src="'.$_SERVER['PHP_SELF'].'?handler=file&module=smartroutes&file=trash.png.php" style="float:none; margin-left:0px; margin-bottom:-3px; cursor:pointer;" alt="'._("remove").'" title="'._('Click here to remove this destination').'" onclick="destRemove('._("$destRowsUsed").')">');		
		echo('<input type="text" class="smartroute_dest_match" id="smartroute_dest_match_'.$destRowsUsed.'" name="smartroute_dest_match['.$destRowsUsed.']" value="'.$dest['matchkey'].'" tabindex="'.$tabindex++.'" /></nobr></td>');
		echo('<td valign="top"><input type="text" class="smartroute_dest_extvar" id="smartroute_dest_extvar_'.$destRowsUsed.'" name="smartroute_dest_extvar['.$destRowsUsed.']" value="'.$dest['extvar'].'" tabindex="'.$tabindex++.'" /></td>');
		
		$drawSelectHTML1 = drawselects($dest['destination'],$destSetNum++,false,false);
		// remove table components from FreePBX < 2.8 
		$drawSelectHTML1 = str_replace("<tr>", "", $drawSelectHTML1);
		$drawSelectHTML1 = str_replace("<td colspan=2>", "", $drawSelectHTML1);		
		$drawSelectHTML1 = str_replace("</td>", "", $drawSelectHTML1);
		$drawSelectHTML1 = str_replace("</tr>", "", $drawSelectHTML1);
		
		$drawSelectHTML2 = drawselects($dest['failover_dest'],$destSetNum++,false,false);
		// remove table components from FreePBX < 2.8 
		$drawSelectHTML2 = str_replace("<tr>", "", $drawSelectHTML2);
		$drawSelectHTML2 = str_replace("<td colspan=2>", "", $drawSelectHTML2);		
		$drawSelectHTML2 = str_replace("</td>", "", $drawSelectHTML2);
		$drawSelectHTML2 = str_replace("</tr>", "", $drawSelectHTML2);
		
		echo('<td valign="top">'.$drawSelectHTML1.'</td>');
		echo('<td valign="top">'.$drawSelectHTML2);
		echo('<input type="hidden" name="smartroute_dest['.$destRowsUsed.']" value="'.($destSetNum-2).'" />'."\n");
		echo('<input type="hidden" name="smartroute_faildest['.$destRowsUsed.']" value="'.($destSetNum-1).'" />'."\n");
		echo('</td></tr>'."\n");
		}
    echo('<tr id="last_dest_row"></tr>'."\n");		
	echo('</table></div><td></tr>'."\n");
	
    echo('<tr><td colspan="2"><input type="button" id="smartroutes-dest-add"  value="'._("+ Add Destination Match").'" /></td></tr>'."\n");
    echo('</table>'."\n");
	
	$tabindex += 2000; // make room for dynamic insertion of new fields

	// ** setup the default destination
	// *********************************
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Default Destination")."\n".'<span>');
    echo _("Define the default destination for this route.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");    
    	
    echo('<tr><td><a href=# class="info">'._("Default Destination").'<span><br>'._("Default destination of this route if no match found.").'<br></span></a>:'."\n".'</td>');
	echo('<td>'.drawselects($smartroute_route['destination'],$destSetNum++,false,false).'</td></tr>'."\n");
	echo('<input type="hidden" name="smartroute_default_destination" value="'.($destSetNum-1).'" />'."\n");
        
    echo('</table>'."\n");	
	
	
	// ** setup the "database settings" rows
	// ****************************
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Database Settings")."\n".'<span>');
    echo _("Define the database settings for this route.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");    
    echo('<tr><td>');
    
	if(!$mysqlInstalled) {
		echo('<font color=DarkOrange>MySQL support cannot be confirmed in your Asterisk install.<br />'."\n");
		} 
	else { 
		echo('<font color=DodgerBlue>MySQL support appears to be installed in your Asterisk</font><br />'."\n");
		}
		
	if(!$odbcInstalled) {
		echo('<font color=DarkOrange>ODBC support cannot be confirmed in your Asterisk install.</font><br />'."\n");
		} 
	else {
		echo('<font color=DodgerBlue>ODBC support appears to be installed in your Asterisk</font><br />'."\n");
		} 
    
    echo('<br />'."\n");
    echo('</tr></td>'."\n");    
    
    echo('<tr><td><a href="#" class="info">'._("Database Type").'<span>'._("Select the database type to use.").'</span></a>:</td>'."\n");
    echo('<td><select name="dbengine" tabindex="'.$tabindex++.'"><option value="odbc" '.($smartroute_route['dbengine'] == "odbc"? 'SELECTED':'').' >ODBC (Recommended)</option><option value="mysql" '.($smartroute_route['dbengine'] != "odbc"? 'SELECTED':'').' >MySQL (Deprecated)</option></select></td></tr>'."\n");
    
	echo('<tr><td><a href="#" class="info">'._("MySQL Host").'<span>'._("Enter the MySQL Host (if using MySQL) DEPRECATED.").'</span></a></td>'."\n");
	echo('<td><input type="text" name="mysql-host" value="'.$smartroute_route['mysql-host'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");
	
	echo('<tr><td><a href="#" class="info">'._("MySQL Database").'<span>'._("Enter the MySQL Database (if using MySQL).").'</span></a></td>'."\n");
	echo('<td><input type="text" name="mysql-database" value="'.$smartroute_route['mysql-database'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");
	
	echo('<tr><td><a href="#" class="info">'._("MySQL Username").'<span>'._("Enter the MySQL Username (if using MySQL).").'</span></a></td>'."\n");
	echo('<td><input type="text" name="mysql-username" value="'.$smartroute_route['mysql-username'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");

	echo('<tr><td><a href="#" class="info">'._("MySQL Password").'<span>'._("Enter the MySQL Password (if using MySQL).").'</span></a></td>'."\n");
	echo('<td><input type="text" name="mysql-password" value="'.$smartroute_route['mysql-password'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");
	
	echo('<br>'."\n");

	// list dsn's configured in Asterisk (/etc/asterisk/res_odbc.conf
	$dsn_list = smartroutes_get_dsns();
	if(is_array($dsn_list) && count($dsn_list)) {
		echo('<tr><td><a href="#" class="info">'._("ODBC DSN").'<span>'._("Enter the ODBC DSN (if using ODBC) RECOMMENDED").'</span></a></td>'."\n");
		echo('<td><select name="odbc-dsn" tabindex="'.$tabindex++.'">."\n"');
		foreach($dsn_list as $dsn_name) {
    		echo('<option value="'.$dsn_name.'" '.($smartroute_route['odbc-dsn'] == $dsn_name? 'SELECTED':'').' >'.$dsn_name.'</option>'."\n");
			}
    
    	echo('</select></td></tr>'."\n");
		}
	else {		
		// just provide an entry field (assume they will add the odbc source to asterisk later
		echo('<tr><td><a href="#" class="info">'._("ODBC DSN  (Note: None found configured in Asterisk)").'<span>'._("Enter the ODBC DSN (if using ODBC) RECOMMENDED").'</span></a></td>'."\n");		
		echo('<td><input type="text" name="odbc-dsn" value="'.$smartroute_route['odbc-dsn'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");    
		}   	

	// ** setup the "track current calls" row
	// ****************************
    echo('<tr><td><a href="#" class="info">'._("Enable database tracking of current calls?").'<span>'._("This setting causes current calls to be tracked in a database for call traffic shaping with SmartRoutes.  NOTE: It will require that this SmartRoute use the FreePBX 'asterisk' database (and the 'smartroutes_currentcalls' table).<br><br>When enabled, current calls are tracked in a table so that you can perform a lookup in the table to count the number of calls to a specific DID, from a given CID, or in total and route calls differently based on the call volume.  Using this feature, if a single DID floods your system with calls then you can send all calls (after the first xx calls) to a lower priority so that other customers/DIDs aren't affected or first send calls to a high-volume announcement.  Sample settings to return the number of calls to this DID:<br><br><b>host:</b> localhost, <b>database:</b> asterisk, <b>table:</b> smartroute_currentcalls<br><b>sql</b>: SELECT COUNT(*) FROM (SELECT src,dst,uniqueid FROM smartroute_currentcalls WHERE (dst=${FROM_DID} AND calldate < DATE_SUB(NOW(), INTERVAL 2 MINUTE))) as T").'</span></a>:</td>'."\n");
    echo('<td><select name="trackcurrentcalls" tabindex="'.$tabindex++.'"><option value="1" '.($smartroute_route['trackcurrentcalls'] == "1"? 'SELECTED':'').' >Yes</option><option value="0" '.($smartroute_route['trackcurrentcalls'] != "1"? 'SELECTED':'').' >No</option></select></td></tr>'."\n");
		
    
    echo('</table>'."\n");	

	// ** setup the "route settings" rows
	// ***********************************
	echo('<table>'."\n");
	echo('<tr><td colspan="2"><h5><a href=# class="info">'._("Options")."\n".'<span>');
    echo _("Define the route settings for this route.");
    echo('<br /><br /></span></a><hr></h5></td></tr>'."\n");    
 
	echo('<tr><td><a href="#" class="info">'._("Limit CID Digits").'<span>'._("Enter the trailing xx digits to keep from the caller ID (effectively stripping unnecessary prefixes).  Leave blank or enter 0 for no stripping of initial caller ID digits.  This is important if your SIP provider puts extra digits at the beginning of your caller ID number and that number format isn't accepted by another SIP provider the call may go out on.").'</span></a></td>'."\n");
	echo('<td><input type="text" size="2" name="limitciddigits" value="'.$smartroute_route['limitciddigits'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");

	echo('<tr><td><a href="#" class="info">'._("Limit DID Digits").'<span>'._("Enter the trailing xx digits to keep the DID (effectively stripping unnecessary prefixes).  Leave blank or enter 0 for no stripping of initial DID digits.  This is important if your SIP provider puts extra digits at the beginning of your DID number (ex: \"+001\") and that number format isn't appropriate for your dialplan or won't be accepted by another SIP provider the call may go out on.").'</span></a></td>'."\n");
	echo('<td><input type="text" size="2" name="limitdiddigits" value="'.$smartroute_route['limitdiddigits'].'" tabindex="'.$tabindex++.'"></td></tr>'."\n");

    
	// ***** FOLLOWING USED WITH FORM FIELDS TAKEN FROM FREEPBX DID/INBOUND ROUTE
	// ****************************************************************************
	$privacyman = $smartroute_route['privacyman'];
	$pmmaxretries = $smartroute_route['pmmaxretries'];
	$pmminlength = $smartroute_route['pmminlength'];
	$alertinfo = $smartroute_route['alertinfo'];
	$mohclass = $smartroute_route['mohclass'];
	$grppre = $smartroute_route['grppre'];
	$delay_answer = $smartroute_route['delay_answer'];
	$ringing = $smartroute_route['ringing'];  
	
	// ******************************************************
	// ***** FOLLOWING TAKEN FROM FREEPBX DID/INBOUND ROUTE
	// ******************************************************	
	?>    
    
    <tr>
      <td><a href="#" class="info"><?php echo _("Alert Info")?><span><?php echo _('ALERT_INFO can be used for distinctive ring with SIP devices.')?></span></a>:</td>
      <td><input type="text" name="alertinfo" size="10" value="<?php echo $alertinfo ?>" tabindex="<?php echo ++$tabindex;?>"></td>
    </tr>
    <tr>
      <td><a href="#" class="info"><?php echo _("CID name prefix")?><span><?php echo _('You can optionally prefix the Caller ID name. ie: If you prefix with "Sales:", a call from John Doe would display as "Sales:John Doe" on the extensions that ring.')?></span></a>:</td>
      <td><input type="text" name="grppre" size="10" value="<?php echo $grppre ?>" tabindex="<?php echo ++$tabindex;?>"></td>
    </tr>
<?php   if (function_exists('music_list')) { ?>
    <tr>
      <td><a href="#" class="info"><?php echo _("Music On Hold")?><span><?php echo _("Set the MoH class that will be used for calls that come in on this route. For example, choose a type appropriate for routes coming in from a country which may have announcements in their language.")?></span></a>:</td>
      <td>
        <select name="mohclass" tabindex="<?php echo ++$tabindex;?>">
        <?php
          $tresults = music_list();
          $cur = (isset($mohclass) && $mohclass != "" ? $mohclass : 'default');
//          echo '<option value="none">'._("No Music")."</option>";
          if (isset($tresults[0])) {
            foreach ($tresults as $tresult) {
              ($tresult == 'none' ? $ttext = _("No Music") : $ttext = $tresult);
                  ($tresult == 'default' ? $ttext = _("Default") : $ttext = $tresult);
              echo '<option value="'.$tresult.'"'.($tresult == $cur ? ' SELECTED' : '').'>'._($ttext)."</option>\n";
            }
          }
        ?>
        </select>
      </td>
    </tr>
<?php } ?>
    <tr>
      <td><a href="#" class="info"><?php echo _("Signal RINGING")?><span><?php echo _('Some devices or providers require RINGING to be sent before ANSWER. You\'ll notice this happening if you can send calls directly to a phone, but if you send it to an IVR, it won\'t connect the call.')?></span></a>:</td>
      <td><input type="checkbox" name="ringing" value="CHECKED" <?php echo $ringing ?>  tabindex="<?php echo ++$tabindex;?>"/></td>
    </tr>
    <tr>
      <td><a href="#" class="info"><?php echo _("Pause Before Answer")?><span><?php echo _("An optional delay to wait before processing this route. Setting this value will delay the channel from answering the call. This may be handy if external fax equipment or security systems are installed in parallel and you would like them to be able to seize the line.")?></span></a>:</td>
      <td><input type="text" name="delay_answer" size="3" value="<?php echo ($delay_answer != '0')?$delay_answer:'' ?>" tabindex="<?php echo ++$tabindex;?>"></td>
    </tr>
    
    <tr><td colspan="2"><h5><?php echo _("Privacy")?><hr></h5></td></tr>

    <tr>
      <td><a href="#" class="info"><?php echo _("Privacy Manager")?><span><?php echo _('If no Caller ID has been received, Privacy Manager will ask the caller to enter their phone number. If an user/extension has Call Screening enabled, the incoming caller will be be prompted to say their name when the call reaches the user/extension.')?></span></a>:</td>
      <td>
        <select name="privacyman" tabindex="<?php echo ++$tabindex;?>">
          <option value="0" <?php  echo ($privacyman == '0' ? 'SELECTED' : '')?>><?php echo _("No")?>
          <option value="1" <?php  echo ($privacyman == '1' ? 'SELECTED' : '')?>><?php echo _("Yes")?>
        </select>
      </td>
    </tr>
    <tr class="pm_opts" <?php echo $privacyman == '0' ? 'style="display:none"':''?>>
      <td><a href="#" class="info"><?php echo _("Max attempts")?><span><?php echo _('Number of attempts the caller has to enter a valid callerID')?></span></a>:</td>
      <td>
        <select name="pmmaxretries" tabindex="<?php echo ++$tabindex;?>">
          <?php
            for($i=1;$i<11;$i++){
              if(!isset($pmmaxretries)||$pmmaxretries==''){$pmmaxretries=3;}//set defualts
              echo '<option value="'.$i.'"'.($pmmaxretries == $i ? 'SELECTED' : '').' >'.$i.'</option>';
            }
          ?>
        </select>
      </td>
    </tr>
    <tr class="pm_opts" <?php echo $privacyman == '0' ? 'style="display:none"':''?>>
      <td><a href="#" class="info"><?php echo _("Min Length")?><span><?php echo _('Minimum amount of digits callerID needs to contain in order to be considered valid')?></span></a>:</td>
      <td>
        <select name="pmminlength" tabindex="<?php echo ++$tabindex;?>">
          <?php
            if(!isset($pmminlength)||$pmminlength==''){$pmminlength=10;}//set USA defaults
            for($i=1;$i<16;$i++){
              echo '<option value="'.$i.'"'.($pmminlength == $i ? 'SELECTED' : '').' >'.$i.'</option>';
            }
          ?>
        </select>
      </td>
    </tr>
    
<?php

	// the fax hook for did's doesn't work here because we're not a core did.  
	// originally called it directly but it wasn't loading the settings stored here so we made our own version
	// that's why we check for one of the 'real' fax functions to see if fax support is loaded 
	if (function_exists('smartroutes_fax_hook_core') && function_exists('fax_get_config')) {
		echo smartroutes_fax_hook_core($id, 'did', $smartroute_route);
		}	
		
	// implementation of module hook
	// object was initialized in config.php
	echo $module_hook->hookHtml;
	
    echo('<tr><td colspan="2"><h6><input name="Submit" type="submit" value="'._("Submit").'" tabindex="'.$tabindex++.'">&nbsp;&nbsp;<br></td></tr>'."\n");
    echo('</table>'."\n");    
	}	
	
?>



<script language="javascript"> 
<!--
 
$(document).ready(function(){
		
	if($("[name=name]").val() == "") {
			$("[name=name]").focus();
		} 
	else {
			$("[name=smartroute_query_wiz_table]").focus();
		}


	// hide query column labels if no rows
	var idx = $('input[name^="smartroute_query_adv_sql"]').size();
	if(idx < 2) { // main query always there (1)
		$("#smartroutes_querylabels").hide();
		}	
		
	// hide destination column labels if no rows
	var idx = $('input[name^="smartroute_dest_match"]').size();
	if(idx < 1) {
		$("#smartroutes_destlabels").hide();
		}
	

	$("[name=dbengine]").bind('blur click change keypress',function(){
		var dbtype=$(this).val();
		if(dbtype == "odbc") {
			$("[name=mysql-host]").parent().parent().hide();
			$("[name=mysql-database]").parent().parent().hide();
			$("[name=mysql-username]").parent().parent().hide();
			$("[name=mysql-password]").parent().parent().hide();
			$("[name=odbc-dsn]").parent().parent().show();		
			}
		else {
			$("[name=mysql-host]").parent().parent().show();
			$("[name=mysql-database]").parent().parent().show();
			$("[name=mysql-username]").parent().parent().show();
			$("[name=mysql-password]").parent().parent().show();
			$("[name=odbc-dsn]").parent().parent().hide();		
			}
		});
	// set initial state
	$("[name=dbengine]").change();
	
	/* Add a new query */
	$("#smartroutes-query-add").click(function(){
		var idx = $(".smartroute_query_adv_var1").size();
		var idxp = idx - 1;
		var tabindex = parseInt($("#smartroute_query_adv_var5_"+idxp).attr('tabindex')) + 1;
		var tabindex1 = tabindex + 2;
		var tabindex2 = tabindex + 3;
		var tabindex3 = tabindex + 4;
		var tabindex4 = tabindex + 5;
		var tabindex5 = tabindex + 6;

		// make sure row labels are visible
		$("#smartroutes_querylabels").show();		
		
		var insert_html = '<?php
			// *** NOTE THAT WE USE THE SAME FORM FIELD LINES AS ABOVE EXCEPT THAT THE ++ IS REMOVED FROM tabindex AND WE ADJUST THE VAL BEFORE EACH LINE 
			// fix for our .js here from loop above
			$query_row = "'+idx+'";
			$query['adv_query'] = '';
			$query['adv_varname1'] = '';
			$query['adv_varname2'] = '';
			$query['adv_varname3'] = '';
			$query['adv_varname4'] = '';
			$query['adv_varname5'] = '';
			
			$tabindex = "'+tabindex+'";		
			// uses js function queryRemove(row)
		    echo('<tr><td><nobr><img src="'.$_SERVER['PHP_SELF'].'?handler=file&module=smartroutes&file=trash.png.php" style="float:none; margin-left:0px; margin-bottom:-3px; cursor:pointer;" alt="'._("remove").'" title="'._('Click here to remove this query').'" onclick="queryRemove('._("$query_row").')">');
			
			echo('<input type="text" class="smartroute_query_adv_sql" id="smartroute_query_adv_sql_'.$query_row.'" name="smartroute_query_adv_sql['.$query_row.']" value="'.$query['adv_query'].'" tabindex="'.$tabindex.'" /></nobr></td>');
			$tabindex = "'+tabindex1+'";		
			echo('<td><nobr><input type="text" class="smartroute_query_adv_var1" id="smartroute_query_adv_var1_'.$query_row.'" name="smartroute_query_adv_var1['.$query_row.']" value="'.$query['adv_varname1'].'" tabindex="'.$tabindex.'" />');
			$tabindex = "'+tabindex2+'";		
			echo('<input type="text" class="smartroute_query_adv_var2" id="smartroute_query_adv_var2_'.$query_row.'" name="smartroute_query_adv_var2['.$query_row.']" value="'.$query['adv_varname2'].'" tabindex="'.$tabindex.'" />');
			$tabindex = "'+tabindex3+'";		
			echo('<input type="text" class="smartroute_query_adv_var3" id="smartroute_query_adv_var3_'.$query_row.'" name="smartroute_query_adv_var3['.$query_row.']" value="'.$query['adv_varname3'].'" tabindex="'.$tabindex.'" />');
			$tabindex = "'+tabindex4+'";		
			echo('<input type="text" class="smartroute_query_adv_var4" id="smartroute_query_adv_var4_'.$query_row.'" name="smartroute_query_adv_var4['.$query_row.']" value="'.$query['adv_varname4'].'" tabindex="'.$tabindex.'" />');
			$tabindex = "'+tabindex5+'";		
			echo('<input type="text" class="smartroute_query_adv_var5" id="smartroute_query_adv_var5_'.$query_row.'" name="smartroute_query_adv_var5['.$query_row.']" value="'.$query['adv_varname5'].'" tabindex="'.$tabindex.'" />');		
	
	  		echo('</nobr></td></tr>');
	  		?>';
	  
	 
		var new_insert = $("#last_query_row").before(insert_html).prev();
		});
	
	$("#smartroutes_aw_switch").click(function(){
		if($("#smartroutes_wiz_query").is(":visible")) {
			// hide wizard and enable advanced
			$("#smartroutes_wiz_query").hide();
			$("#smartroutes_adv_query").show();
			$("#smartroutes_aw_switch").text('<?php echo _("Switch to Wizard"); ?>');
			$('[name="smartroute_mainquery_wizard"]').val('no');
			}	
		else {
			// hide advanced and enable wizard
			$("#smartroutes_adv_query").hide();
			$("#smartroutes_wiz_query").show();
			$("#smartroutes_aw_switch").text('<?php echo _("Switch to Advanced"); ?>');
			$('[name="smartroute_mainquery_wizard"]').val('yes');
			}
		});
	
	//show/hide privacy manager options
	$('select[name=privacyman]').change(function(){
		if($(this).val()==0){$('.pm_opts').fadeOut();}
		if($(this).val()==1){$('.pm_opts').fadeIn();}
		});	
	
	
	/* Add a new destination */
	$("#smartroutes-dest-add").click(function(){
		// identify last row currently in use to get next tabindex value
		var idx = $('input[name^="smartroute_dest_match"]').size();
		var idxp = idx - 1;		
		var findRowTabIndex = $('input[name^="smartroute_dest_extvar"]:last').attr('tabindex');
		if(findRowTabIndex == undefined) {
			// probably aren't any destinations yet - we're the first
			// look for match type
			findRowTabIndex = $('[name="search-type"]').attr('tabindex');
		}
		var tabindex = parseInt(findRowTabIndex) + 1;
		var tabindex1 = tabindex + 2;
		var tabindex2 = tabindex + 3;

		// html/php is done, this is interactive js so we have to find the last used destSetNum/destRowsUsed
		var destRowsUsed = 0;
		var destSetNum = 0;
		var lastDestSet = $('[name^="smartroute_faildest"]:last').val();
		if(lastDestSet == undefined) {
			destSetNum = 1;
		}
		else {
			destSetNum = parseInt(lastDestSet) + 1;
		}
		var failSetNum = destSetNum+1;		
		var lastDestName = $('input[name^="smartroute_dest_match"]:last').attr('id');		
		if(lastDestName != undefined) {		
			destRowsUsed = 	parseInt(lastDestName.replace('smartroute_dest_match_',''))+1;
		}
		
		// make sure labels are visible
		$("#smartroutes_destlabels").show();		
	
		var insert_html = '<?php
			// REMEMBER THAT THE PHP IS STATIC AND NOT DYNAMIC SO WE HAVE REPLACEMENT VARS GENERATED AND LET JS FILL-IN DYNAMIC VALUES
			// we could have put the 'js' vars in-line here but the drawselects function has already executed when page was output
		
			// *** NOTE THAT WE USE THE SAME FORM FIELD LINES AS ABOVE EXCEPT THAT THE ++ IS REMOVED FROM tabindex AND WE ADJUST THE VAL BEFORE EACH LINE
			// we also modified the $destSetNum value to support dynamic creation
			
			// get freepbx version
			$installed_ver = getversion();		
			 
			// fix for our .js here from loop above
			$dest['matchkey'] = '';
			$dest['extvar'] = '';
			$dest['destination'] = '';
			$dest['failover_dest'] = '';
			$dest['destination'] = '';
			
			$destRowsUsed = "'+destRowsUsed+'";
			
			// add <hr> if FreePBX version less than 2.8 - drawselect is a group of radio boxes and not a combo-box
			if(version_compare_freepbx($installed_ver, "2.8","lt")) {
				echo('<tr><td colspan=4><hr></td></tr>');
				}
							
		    echo('<tr><td><nobr><img src="'.$_SERVER['PHP_SELF'].'?handler=file&module=smartroutes&file=trash.png.php" style="float:none; margin-left:0px; margin-bottom:-3px; cursor:pointer;" alt="'._("remove").'" title="'._('Click here to remove this destination').'" onclick="destRemove('._("$destRowsUsed").')">');
		    
			$tabindex = "'+tabindex+'";			
			echo('<input type="text" class="smartroute_dest_match" id="smartroute_dest_match_'.$destRowsUsed.'" name="smartroute_dest_match['.$destRowsUsed.']" value="'.$dest['matchkey'].'" tabindex="'.$tabindex.'" /></nobr></td>');
			$tabindex = "'+tabindex1+'";		
			echo('<td><input type="text" class="smartroute_dest_extvar" id="smartroute_dest_extvar_'.$destRowsUsed.'" name="smartroute_dest_extvar['.$destRowsUsed.']" value="'.$dest['extvar'].'" tabindex="'.$tabindex.'" /></td>');
			
			//removed the ++ from $destSetNum and hard-coded the destSet numbers (from original code in form above)
			// also escape single quotes so it won't break the javascript string insert_html
			$tabindex = '0'; // will be fixed below
			$drawSelectHTML1 = addslashes(drawselects($dest['destination'],'21000',false,false));
			$drawSelectHTML1 = trim( preg_replace( '/\s+/', ' ', $drawSelectHTML1)); // remove line breaks
			// remove table components from FreePBX < 2.8 
			$drawSelectHTML1 = str_replace("<tr>", "", $drawSelectHTML1);
			$drawSelectHTML1 = str_replace("<td colspan=2>", "", $drawSelectHTML1);		
			$drawSelectHTML1 = str_replace("</td>", "", $drawSelectHTML1);
			$drawSelectHTML1 = str_replace("</tr>", "", $drawSelectHTML1);
			
			$drawSelectHTML2 = addslashes(drawselects($dest['failover_dest'],'22000',false,false));
			$drawSelectHTML2 = trim( preg_replace( '/\s+/', ' ', $drawSelectHTML2)); // remove line breaks 
			// remove table components from FreePBX < 2.8 
			$drawSelectHTML2 = str_replace("<tr>", "", $drawSelectHTML2);
			$drawSelectHTML2 = str_replace("<td colspan=2>", "", $drawSelectHTML2);		
			$drawSelectHTML2 = str_replace("</td>", "", $drawSelectHTML2);
			$drawSelectHTML2 = str_replace("</tr>", "", $drawSelectHTML2);
			
			// was: .(version_compare_freepbx($installed_ver, "2.8","lt")?"<h4>Destination</h4>":"")
			echo('<td>'.$drawSelectHTML1.'</td>');
			echo('<td>'.$drawSelectHTML2);
		    
		    // NOTE: I had to change the next two lines (from original in form code) to handle the javascript destSetNum/failSetNum
			echo('<input type="hidden" name="smartroute_dest['.$destRowsUsed.']" value="\'+destSetNum+\'" />');
			echo('<input type="hidden" name="smartroute_faildest['.$destRowsUsed.']" value="\'+failSetNum+\'" />');
			echo('</td></tr>'); // ** had to remove the "\n" for this version
		?>';
		// replace the destination set numbers
		insert_html = insert_html.replace(/21000/g,destSetNum);
		insert_html = insert_html.replace(/22000/g,failSetNum);

		var new_insert = $("#last_dest_row").before(insert_html).prev();

		// now fix the tabindex values for the drawselects
		$('[name$='+destSetNum+'].destdropdown').each(function(i,e){ $(this).attr("tabindex", tabindex2); $(this).tabindex = tabindex2; tabindex2++; });
		$('[name$='+destSetNum+'].destdropdown2').each(function(i,e){ $(this).attr("tabindex", tabindex2); $(this).tabindex = tabindex2; tabindex2++; });
		$('[name$='+failSetNum+'].destdropdown').each(function(i,e){ $(this).attr("tabindex", tabindex2); $(this).tabindex = tabindex2; tabindex2++; });
		$('[name$='+failSetNum+'].destdropdown2').each(function(i,e){ $(this).attr("tabindex", tabindex2); $(this).tabindex = tabindex2; tabindex2++; });

		if(typeof setupDynamicFormFields == 'function') {
			// call freepbx javascript function to activate the drop-down triggers
			// this function was submitted as a patch to FreePBX for version 2.9 and will be maintained by FreePBX going forward
			setupDynamicFormFields();
			}
		else {			
			// ** this code might change with future versions of FreePBX ** taken from version 2.8
			// taken from FreePBX document.ready function that auto installs this on the page load
			// this will cause the second drop-down for destination item details
			$('.destdropdown').bind('blur click change keypress',function(){
				var name=$(this).attr('name');
				var id=name.replace('goto','');
				var dest=$(this).val();
				$('[name$='+id+'].destdropdown2').hide();
				$('[name='+dest+id+'].destdropdown2').show();
				});
			$('.destdropdown').bind('change',function(){
				if($(this).find('option:selected').val()=='Error'){
					$(this).css('background-color','red');
					}
				else{
					$(this).css('background-color','white');
					}
				});
			}
	});

	// this tracks which submit button was pressed (save or delete) because we validate the name differently for save/delete
	var clickedSubmitVar = null;
	
	$('#smartroutes_routeEdit :submit').click(function() { 
		clickedSubmitVar = $(this).attr("name");  
		});
		
	$("#smartroutes_routeEdit").submit(function() {
		var msgInvalidRouteName = "Route name is invalid, please try again";
		var msgInvalidRoutePwd = "Default destination is invalid, please try again";
		var msgInvalidOutboundCID = "Invalid Outbound Caller ID";
		
		var rname = $("[name=name]").val();
		if (!rname.match('^[a-zA-Z0-9][a-zA-Z0-9_\-]+$'))
			return warnInvalid($("[name=name]"), msgInvalidRouteName);
		
		if((rname == "new_route" || rname == "") && clickedSubmitVar != "delete") {
			return warnInvalid($("[name=name]"), msgInvalidRouteName);		
			}

		// fix two numeric fields
		var fieldVal = $("[name=limitciddigits]").val();
		fieldVal = jQuery.trim(fieldVal);		
		if(fieldVal.length) {		
			var digits = parseInt(fieldVal);
			$("[name=limitciddigits]").val(digits);
			}

		fieldVal = $("[name=limitdiddigits]").val();
		fieldVal = jQuery.trim(fieldVal);		
		if(fieldVal.length) {		
			digits = parseInt(fieldVal);
			$("[name=limitdiddigits]").val(digits);
			}
		

		// *** HAVE TO CALL setDestinations(formname, #) on submit if we used drawselects
		setDestinations(smartroutes_routeEdit, $("#smartroute_default_destination").val());
		$('[name^="smartroute_dest"]').each(function(i,e){ setDestinations(smartroutes_routeEdit, $(this).val());});
		$('[name^="smartroute_faildest"]').each(function(i,e){ setDestinations(smartroutes_routeEdit, $(this).val());});
		return true; 	
		});
	
});  // end document.ready



function queryRemove(idx) {
	$("#smartroute_query_adv_sql_"+idx).parent().parent().parent().remove();

	// hide query column labels if no rows
	var idx = $('input[name^="smartroute_query_adv_sql"]').size();
	if(idx < 2) { // main query always there (1)
		$("#smartroutes_querylabels").hide();
		}		
	}


function destRemove(idx) {

	<?php 
	if(version_compare_freepbx($installed_ver, "2.8","lt")) {
		// delete the preceding <HR> row
		echo('$("#smartroute_dest_match_"+idx).parent().parent().parent().prev().remove();');
		}
	?>
	
	$("#smartroute_dest_match_"+idx).parent().parent().parent().remove();

	// hide destination column labels if no rows
	var idx = $('input[name^="smartroute_dest_match"]').size();
	if(idx < 1) {
		$("#smartroutes_destlabels").hide();
		}
	}
 
 
//-->
</script> 

</form>

</div>

