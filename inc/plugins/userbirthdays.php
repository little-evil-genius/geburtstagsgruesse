<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_config_settings_change", "userbirthdays_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "userbirthdays_settings_peek");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'userbirthdays_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'userbirthdays_admin_update_plugin');
$plugins->add_hook('global_intermediate', 'userbirthdays_global');
$plugins->add_hook("misc_start", "userbirthdays_misc");
$plugins->add_hook("fetch_wol_activity_end", "userbirthdays_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "userbirthdays_online_location");
 
// Die Informationen, die im Pluginmanager angezeigt werden
function userbirthdays_info() {
	return array(
		"name"		=> "Geburtstagsgrüße",
		"description"	=> "Durch dieses Plugin bekommen User:innen eine persönliche Geburtstagsnachricht auf dem Index angezeigt. Zusätzlich kann eine Liste ausgeben werden im Forum mit allen Geburtstagen.",
		"website"	=> "https://github.com/little-evil-genius/user-geburtstagsgruesse",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "2.1",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function userbirthdays_install() {
    
    global $db;

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message("Das Plugin <a href=\"http://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2\" target=\"_blank\">\"Enhanced Account Switcher\"</a> muss installiert sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'userbirthdays',
        'title'         => 'Geburtstagsgrüße',
        'description'   => 'Einstellungen für das Plugin "Geburtstagsgrüße"',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    userbirthdays_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "userbirthdays",
        "title" => $db->escape_string("Geburtstagsgrüße"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    userbirthdays_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $stylesheet = userbirthdays_stylesheet();
    $sid = $db->insert_query('themestylesheets', $stylesheet);
    cache_stylesheet(1, "userbirthdays.css", $stylesheet['stylesheet']);
    update_theme_stylesheet_list("1");
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function userbirthdays_is_installed(){

	global $mybb;

	if (isset($mybb->settings['userbirthdays_birthday'])) {
		return true;
	}
	return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function userbirthdays_uninstall(){
    
	global $db, $cache;

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'userbirthdays'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'userbirthdays%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'userbirthdays%'");
    $db->delete_query('settinggroups', "name = 'userbirthdays'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'userbirthdays.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']); 
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function userbirthdays_activate() {
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    // VARIABLEN EINFÜGEN
    find_replace_templatesets('header', '#'.preg_quote('{$bbclosedwarning}').'#', '{$bbclosedwarning}{$index_userbirthdays}{$team_userbirthdays}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function userbirthdays_deactivate() {
    
    global $db, $cache;
    
    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("header", "#".preg_quote('{$index_userbirthdays}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$team_userbirthdays}')."#i", '', 0);
}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function userbirthdays_settings_change(){
    
    global $db, $mybb, $userbirthdays_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='userbirthdays'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $userbirthdays_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function userbirthdays_settings_peek(&$peekers){

    global $userbirthdays_settings_peeker;

    if ($userbirthdays_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_userbirthdays_field"), $("#row_setting_userbirthdays_birthday"), /^(0|1)/, false)';
        $peekers[] = 'new Peeker($(".setting_userbirthdays_list"), $("#row_setting_userbirthdays_list_allowgroups, #row_setting_userbirthdays_list_nav, #row_setting_userbirthdays_list_menu, #row_setting_userbirthdays_list_type"),/1/,true)';
        $peekers[] = 'new Peeker($("#setting_userbirthdays_list_type"), $("#row_setting_userbirthdays_list_menu"), /^0/, false)';
    }
}

// ADMIN BEREICH - RPG STUFF //
// Stylesheet zum Master Style hinzufügen
function userbirthdays_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "userbirthdays") {

        $css = userbirthdays_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "userbirthdays.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Geburtstagsgrüße")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'userbirthdays.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=userbirthdays\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function userbirthdays_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "userbirthdays") {

        // Einstellungen überprüfen => Type = update
        userbirthdays_settings('update');
        rebuild_settings();

        // Templates 
        userbirthdays_templates('update');

        // Stylesheet
        $update_data = userbirthdays_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'userbirthdays.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('userbirthdays.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Geburtstagsgrüße")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = userbirthdays_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=userbirthdays\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// INDEX ANZEIGE
function userbirthdays_global() {

    global $db, $mybb, $lang, $templates, $index_userbirthdays, $team_userbirthdays, $birthday_text, $bannerText, $headlineText;

    // SPRACHDATEI
    $lang->load('userbirthdays');

    // EINSTELLUNGEN
    $birthday_text = $mybb->settings['userbirthdays_text'];

    $checkBirthdayUser = userbirthdays_birthdayIndex($mybb->user['uid']);
    $user = array();
    if ($checkBirthdayUser != 0) {

        $playername = userbirthdays_playername($mybb->user['uid']);
        $headlineText = $lang->sprintf($lang->userbirthdays_index_headline, $playername);
        
        // Profilfelder & Users Tabelle
        $user = get_user($mybb->user['uid']);
        $userfields_query = $db->simple_select("userfields", "*", "ufid = ".$mybb->user['uid']);
        $userfields = $db->fetch_array($userfields_query);
        $user = array_merge($user, $userfields);

        // Steckbrieffelder
        if ($db->table_exists("application_ucp_fields")) {
            if (!function_exists('application_ucp_build_view')) {
                require_once MYBB_ROOT . 'inc/plugins/application_ucp.php';
                $applicationfields = application_ucp_build_view($mybb->user['uid'], "profile", "array");
                $user = array_merge($user, $applicationfields);
            }
        }
        
        eval("\$index_userbirthdays = \"".$templates->get ("userbirthdays_index")."\";");
    } else {
        $index_userbirthdays = "";
    }

    if ($mybb->settings['userbirthdays_team'] != 1) return;
    if ($mybb->usergroup['canmodcp'] != 1) return;
    $birthdayTeam = userbirthdays_birthdayIndex();

    if ($birthdayTeam['count'] != 0) {

        $birthdaysetting = $mybb->settings['userbirthdays_field'];

        $mainAccounts = array();
        $birthday_user = array();
        while ($birthday = $db->fetch_array($birthdayTeam['query'])) {

            // leer laufen lassen
            $uid = "";
            $playername = "";

            // Mit Infos füllen
            if ($birthdaysetting == 0) {
                $uid = $birthday['ufid'];
            } else {
                $uid = $birthday['uid'];
            }

            $as_uid = $db->fetch_field($db->simple_select("users", "as_uid", "uid = ".$uid),"as_uid");
            $main_uid = ($as_uid == 0) ? $uid : $as_uid;

            if (!in_array($main_uid, $mainAccounts)) {
                $mainAccounts[] = $main_uid;
                $playername = userbirthdays_playername($main_uid);
                $birthday_user[] = build_profile_link($playername, $main_uid);
            }
        }
            
        $birthdayuserlist = implode(' & ', $birthday_user);

        if (count($birthday_user) == 1) {
            $bannerText = $lang->sprintf($lang->userbirthdays_banner_team_single, $birthdayuserlist);
        } else {
            $bannerText = $lang->sprintf($lang->userbirthdays_banner_team_plural, count($birthday_user), $birthdayuserlist);
        }

        eval("\$team_userbirthdays = \"".$templates->get ("userbirthdays_teambanner")."\";");
    } else {
        $team_userbirthdays = "";	
    }
}

// GEBURTSTAGSLISTE
function userbirthdays_misc() {

    global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $page, $lists_menu, $user_bit;

    // return if the action key isn't part of the input
    if ($mybb->get_input('action', MYBB::INPUT_STRING) !== 'userbirthdays') {
        return;
    }

    if ($mybb->settings['userbirthdays_list'] == 0) return;

    // EINSTELLUNGEN
    $list_allowgroups = $mybb->settings['userbirthdays_list_allowgroups'];
    $list_nav = $mybb->settings['userbirthdays_list_nav'];
    $list_type = $mybb->settings['userbirthdays_list_type'];
    $list_menu = $mybb->settings['userbirthdays_list_menu'];

    if (!is_member($list_allowgroups)) {
        error_no_permission();
    }

    // SPRACHDATEI
    $lang->load('userbirthdays');

    $mybb->input['action'] = $mybb->get_input('action');

    // Liste
    if($mybb->input['action'] == "userbirthdays") {

        // Listenmenü
		if($list_type != 2){
            // Jules Plugin
            if ($list_type == 1) {
                $lang->load("lists");
                $query_lists = $db->simple_select("lists", "*");
                $menu_bit = "";
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($list_menu)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($list_nav)){
            add_breadcrumb($lang->userbirthdays_lists, $list_nav);
            add_breadcrumb($lang->userbirthdays_list, "misc.php?action=userbirthdays");
		} else{
            add_breadcrumb($lang->userbirthdays_list, "misc.php?action=userbirthdays");
		}

        $monthsname = array(
            '1' => $lang->userbirthdays_jan,
            '2' => $lang->userbirthdays_feb,
            '3' => $lang->userbirthdays_mar,
            '4' => $lang->userbirthdays_apr,
            '5' => $lang->userbirthdays_mai,
            '6' => $lang->userbirthdays_jun,
            '7' => $lang->userbirthdays_jul,
            '8' => $lang->userbirthdays_aug,
            '9' => $lang->userbirthdays_sep,
            '10' => $lang->userbirthdays_okt,
            '11' => $lang->userbirthdays_nov,
            '12' => $lang->userbirthdays_dez
        );

        $months = "";
        foreach ($monthsname as $number => $name) {

            // Geburtstage auslesen
            $user_bit = userbirthdays_birthdaylist($number);

            eval("\$months .= \"".$templates->get("userbirthdays_list_month")."\";");
        }

        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("userbirthdays_list")."\";");
        output_page($page);
        die();
    }
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function userbirthdays_get_allchars($user_id) {

	global $db;

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY uid");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY uid");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $charas[] = $users['uid'];
	}
	return $charas;  
}

// GEBURTSTAGE - LISTE
function userbirthdays_birthdaylist($number) {

    global $mybb, $db, $templates;

    $birthdaysetting = $mybb->settings['userbirthdays_field'];

    $user_bit = "";
    // MyBB Geburtstagsfeld - TT-M-YYYY
    if ($birthdaysetting == 2) {
        $birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
        WHERE birthday LIKE '%-".$number."-%' 
        AND as_uid = 0
        ORDER BY CAST(SUBSTRING_INDEX(birthday, '-', 1) AS UNSIGNED), username ASC
        ");

        while ($birthday = $db->fetch_array($birthday_query)) {

            // leer laufen lassen
            $uid = "";
            $playername = "";
            $birthdayFormatted = "";

            // Mit Infos füllen
            $uid = $birthday['uid'];
            $playername = userbirthdays_playername($uid);
            $playername = build_profile_link($playername, $uid);

            list($d, $m, $y) = explode("-", $birthday['birthday']);
            if (!empty($y)) {
                $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT) . "." . $y;
            } else {
                $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT);
            }
 
            eval("\$user_bit .= \"".$templates->get("userbirthdays_list_user")."\";");
        }
    } 
    // Profilfeld - TT.MM.YYYY
    else if ($birthdaysetting == 0) {
        $birthdayfield = $mybb->settings['userbirthdays_birthday'];
        $month = str_pad($number, 2, "0", STR_PAD_LEFT); 

        $birthday_query = $db->query("SELECT uf.*, u.username, u.as_uid FROM ".TABLE_PREFIX."userfields uf
        JOIN ".TABLE_PREFIX."users u ON u.uid = uf.ufid
        WHERE fid".$birthdayfield." LIKE '%.".$month.".%' 
        AND as_uid = 0
        ORDER BY CAST(LEFT(fid".$birthdayfield.", 2) AS UNSIGNED), username ASC
        ");

        while ($birthday = $db->fetch_array($birthday_query)) {

            // leer laufen lassen
            $uid = "";
            $playername = "";
            $birthdayFormatted = "";

            // Mit Infos füllen
            $uid = $birthday['ufid'];
            $playername = userbirthdays_playername($uid);
            $playername = build_profile_link($playername, $uid);

            list($d, $m, $y) = explode(".", $birthday['fid'.$birthdayfield]);
            if (!empty($y)) {
                $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT) . "." . $y;
            } else {
                $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT);
            }
 
            eval("\$user_bit .= \"".$templates->get("userbirthdays_list_user")."\";");
        }
    }
    // Steckbrieffeld
    else if ($birthdaysetting == 1) {
        $birthdayfield = $mybb->settings['userbirthdays_birthday'];
        $month = str_pad($number, 2, "0", STR_PAD_LEFT);

        $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$birthdayfield."'"), "id");
        $fieldtyp = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp", "fieldname = '".$birthdayfield."'"), "fieldtyp");

        // YYYY-MM-TT
        if ($fieldtyp == "date") {
            $birthday_query = $db->query("SELECT af.*, u.username, u.as_uid FROM ".TABLE_PREFIX."application_ucp_fields af
            JOIN ".TABLE_PREFIX."users u ON u.uid = af.ufid
            WHERE value LIKE '%-".$month."-%' 
            AND fieldid = ".$fieldid."
            AND as_uid = 0
            ORDER BY CAST(SUBSTRING(value, 9, 2) AS UNSIGNED), (SELECT username FROM ".TABLE_PREFIX."users u WHERE u.uid = af.uid) ASC
            ");
        } 
        // TT.MM.YYYY
        else {
            $birthday_query = $db->query("SELECT af.*, u.username, u.as_uid FROM ".TABLE_PREFIX."application_ucp_fields af
            WHERE value LIKE '%.".$month.".%' 
            AND fieldid = ".$fieldid."
            AND as_uid = 0
            ORDER BY CAST(LEFT(value, 2) AS UNSIGNED), (SELECT username FROM ".TABLE_PREFIX."users u WHERE u.uid = af.uid) ASC
            ");
        }

        while ($birthday = $db->fetch_array($birthday_query)) {

            // leer laufen lassen
            $uid = "";
            $playername = "";
            $birthdayFormatted = "";

            // Mit Infos füllen
            $uid = $birthday['uid'];
            $playername = userbirthdays_playername($uid);
            $playername = build_profile_link($playername, $uid);

            if ($fieldtyp == "date") {
                list($y, $m, $d) = explode("-", $birthday['value']);
                $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT) . "." . $y;
            } else {
                list($d, $m, $y) = explode(".", $birthday['value']);
                if (!empty($y)) {
                    $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT) . "." . $y;
                } else {
                    $birthdayFormatted = str_pad($d, 2, "0", STR_PAD_LEFT) . "." . str_pad($m, 2, "0", STR_PAD_LEFT);
                }
            }
 
            eval("\$user_bit .= \"".$templates->get("userbirthdays_list_user")."\";");
        }
    }

    return $user_bit;
}

// GEBURTSTAGE - INDEX
function userbirthdays_birthdayIndex($uid = '') {

    global $mybb, $db, $templates;

    $birthdaysetting = $mybb->settings['userbirthdays_field'];

    $today = new DateTime();
    $today->setTime(0, 0, 0);

    if (!empty($uid)) {
        $UIDsarray = userbirthdays_get_allchars($uid);      
        $allUIDs = implode(",",$UIDsarray);

        if ($birthdaysetting == 0) {
            $userSQL = "AND ufid IN (".$allUIDs.")";
        } else {
            $userSQL = "AND uid IN (".$allUIDs.")";
        }
    } else {
        $userSQL = "";
    }

    // MyBB Geburtstagsfeld - TT-M-YYYY
    if ($birthdaysetting == 2) {

        $day = $today->format('j');  // ohne führende 0
        $month = $today->format('n'); // ohne führende 0

        $birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
        WHERE birthday LIKE '".$day."-".$month."-%' 
        ".$userSQL);
    }
    // Profilfeld - TT.MM.YYYY
    else if ($birthdaysetting == 0) {

        $birthdayfield = $mybb->settings['userbirthdays_birthday'];

        $day = $today->format('d');  // mit führende 0
        $month = $today->format('m'); // mit führende 0

        $birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
        WHERE fid".$birthdayfield." LIKE '".$day.".".$month.".%' 
        ".$userSQL);
    }
    // Steckbrieffeld
    else if ($birthdaysetting == 1) {

        $birthdayfield = $mybb->settings['userbirthdays_birthday'];

        $day = $today->format('d');  // mit führende 0
        $month = $today->format('m'); // mit führende 0

        $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$birthdayfield."'"), "id");
        $fieldtyp = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp", "fieldname = '".$birthdayfield."'"), "fieldtyp");

        // YYYY-MM-TT
        if ($fieldtyp == "date") {
            $birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields af
            WHERE value LIKE '%-".$month."-".$day."' 
            AND fieldid = ".$fieldid." 
            ".$userSQL);
        } 
        // TT.MM.YYYY
        else {
            $birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields af
            WHERE value LIKE '".$day.".".$month.".%' 
            AND fieldid = ".$fieldid." 
            ".$userSQL);
        }
    }

    $checkBirthday = $db->num_rows($birthday_query);

    if (!empty($uid)) {
        return $checkBirthday;
    } else {
        return array(
            'count' => $checkBirthday,
            'query' => $birthday_query
        );
    }
}

// SPITZNAME
function userbirthdays_playername($uid){
    
    global $db, $mybb;

    $playername_setting = $mybb->settings['userbirthdays_player'];

    if (!empty($playername_setting)) {
        if (is_numeric($playername_setting)) {
            $playername_fid = "fid".$playername_setting;
            $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid ,"ufid = '".$uid."'"), $playername_fid);
        } else {
            $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_setting."'"), "id");
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$uid."' AND fieldid = '".$playername_fid."'"), "value");
        }
    } else {
        $playername = "";
    }

    if (!empty($playername)) {
        $playername = $playername;
    } else {
        $playername = get_user($uid)['username'];
    }

    return $playername;
}

#######################################
### DATABASE | SETTINGS | TEMPLATES ###
#######################################

// EINSTELLUNGEN
function userbirthdays_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'userbirthdays_field' => array(
			'title' => 'Geburtstagsoption',
            'description' => 'Mit welcher Option werden die Geburtstage der User:innen erfasst?',
            'optionscode' => 'select\n0=Profilfeld\n1=Steckbrief-Plugin von Risuena\n2=MyBB-Geburtstagsfeld',
            'value' => '2', // Default
            'disporder' => 1
		),
        'userbirthdays_birthday' => array(
			'title' => 'Geburtstagsfeld',
			'description' => 'Wie lautet die FID/der Identifikator von deinem Profilfeld/Steckbrieffeld Geburtstagsfeld? <b>Format:</b>TT.MM.JJJJ<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '999', // Default
			'disporder' => 2
		),
		'userbirthdays_player' => array(
			'title' => 'Spitzname',
			'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '4', // Default
			'disporder' => 3
		),
		'userbirthdays_text' => array(
			'title' => 'Geburtstagstext',
			'description' => 'Der Standardtext, welcher angezeigt werden soll bei einem Geburtstag. HTML und BBCodes sind erlaubt.',
			'optionscode' => 'textarea',
			'value' => 'Wir wünschen dir so viel Glück, wie der Regen Tropfen, so viel Liebe, wie die Sonne Strahlen und so viel Freude, wie der Himmel Sterne hat.<br>Wir wünschen dir einen wundervollen Tag, viel Freude, Gesundheit und Zufriedenheit für das nächste Jahr und viel Glück für alles, was du dir vornimmst.<br>Die schönsten Tage des letzten Jahres mögen die schlechtesten des kommenden Jahres sein. Lass dich feiern, genieß den Tag und bleib so, wie du bist!<br>Herzlichen Glückwunsch zum Geburtstag!', // Default
			'disporder' => 4
		),
        'userbirthdays_team' => array(
			'title' => 'Teambenachrichtigung',
			'description' => 'Soll das Team eine Benachrichtigung bekommen über die User:innen, welche an dem Tag Geburtstag haben?',
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 5
		),
        'userbirthdays_list' => array(
			'title' => 'Liste aller Geburtstage',
			'description' => 'Soll es eine Liste geben die alle Geburtstage der User:innen ausgibt?',
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 6
		),
        'userbirthdays_list_allowgroups' => array(
            'title' => 'Erlaubte Gruppen',
			'description' => 'Welche Gruppen dürfen diese Liste sehen?',
			'optionscode' => 'groupselect',
			'value' => '4', // Default
			'disporder' => 7
        ),
		'userbirthdays_list_nav' => array(
			'title' => "Listen PHP",
			'description' => "Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.",
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 8
		),
		'userbirthdays_list_type' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '0', // Default
			'disporder' => 9
		),
        'userbirthdays_list_menu' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 10
        ),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'userbirthdays' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }
    }

    rebuild_settings();
}

// TEMPLATES
function userbirthdays_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'userbirthdays_index',
        'template'	=> $db->escape_string('<table width="100%">
        <tr>
		<td class="thead">{$headlineText}</td>
        </tr>
        <tr>
		<td>{$birthday_text}</td>
        </tr>
        </table>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'userbirthdays_list',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->userbirthdays_list}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead"><strong>{$lang->userbirthdays_list}</strong></td>
			</tr>
			<tr>
				<td class="trow1" align="center">
					<div class="userbirthdays_list">
						{$months}
					</div>
				</td>
			</tr>
		</table>
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'userbirthdays_list_month',
        'template'	=> $db->escape_string('<div class="userbirthdays_list_month">
        <div class="userbirthdays_list_month-headline">{$name}</div>
        <div class="userbirthdays_list_month-user">{$user_bit}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'userbirthdays_list_user',
        'template'	=> $db->escape_string('<div class="userbirthdays_list_month-user_bit">{$birthdayFormatted} - {$playername}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'userbirthdays_teambanner',
        'template'	=> $db->escape_string('<div class="red_alert">{$bannerText}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        // ALTES TEMPLATE LÖSCHEN
        $db->delete_query("templates", "title IN('index_userbirthdays')");

        // TEMPLATE GRUPPE HINZUFÜGEN
        $prefix = "userbirthdays";
        $query = $db->simple_select("templategroups", "gid", "prefix = '".$db->escape_string($prefix)."'");
        $existing = $db->fetch_field($query, "gid");
        if (!$existing) {
            $templategroup = array(
                "prefix" => $prefix,
                "title" => $db->escape_string("Geburtstagsgrüße"),
            );
            $db->insert_query("templategroups", $templategroup);
        }

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }   
            else {
                $db->insert_query("templates", $template);
            }
        }
        
	
    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function userbirthdays_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'userbirthdays.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.userbirthdays_list {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        }

        .userbirthdays_list_month {
        flex: 32%; 
        box-sizing: border-box;
        }

        .userbirthdays_list_month-headline {
        background: #0f0f0f url(../../../images/tcat.png) repeat-x;
        color: #fff;
        border-top: 1px solid #444;
        border-bottom: 1px solid #000;
        padding: 6px;
        font-size: 12px;
        font-weight: bold;
        }',
		'cachefile' => 'userbirthdays.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function userbirthdays_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function userbirthdays_is_updated(){

    global $db, $mybb;

	if (isset($mybb->settings['userbirthdays_list_menu'])) {
		return true;
	}
	return false;
}
