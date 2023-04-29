<?php
//error_reporting (-1);
//ini_set ('display_errors', true);
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if (!defined("IN_MYBB")) {
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook("admin_tools_action_handler", "userbirthdays_admin_tools_action_handler");
$plugins->add_hook("admin_tools_menu", "userbirthdays_admin_tools_menu");
$plugins->add_hook("admin_load", "userbirthdays_admin_manage");
$plugins->add_hook('global_start', 'userbirthdays_global');

// Die Informationen, die im Pluginmanager angezeigt werden
function userbirthdays_info() {

	return array(
		"name" => "User Geburtstage",
		"description" => "Durch dieses Plugin bekommen User eine persönliche Geburtstagsnachricht auf dem Index.",
		"author" => "little.evil.genius",
		"authorsite" => "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version" => "2.0",
		"compatibility" => "18*"
	);

}

// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function userbirthdays_install() {

	global $db, $cache, $mybb;

	// EINSTELLUNGEN
	$setting_group = array(
		'name' => 'userbirthdays',
		'title' => 'User Geburtstage',
		'description' => 'Einstellungen für das User Geburtstage-Plugin',
		'disporder' => 5, // The order your setting group will display
		'isdefault' => 0
	);

	$gid = $db->insert_query("settinggroups", $setting_group);
	$setting_array = array(

		// Profilfeld oder MyBB-Geburtstagsfeld
		'userbirthdays_field' => array(
			'title' => 'Geburtstagsfeld',
			'description' => 'Mit welcher Option werden die User Geburtstage erfasst?',
			'optionscode' => 'select\n0=Profilfeld\n1=Steckbrief-Plugin von Risuena\n2=MyBB-Geburtstagsfeld',
			'value' => '0', // Default
			'disporder' => 1
		),

		// Geburtstagsfeld
		'userbirthdays_birthday' => array(
			'title' => 'Geburtstagsfeld',
			'description' => 'Wie lautet die FID/der Identifikator von deinem Profilfeld/Steckbrieffeld Geburtstagsfeld? <b>Format:</b>TT.MM.JJJJ<br>
      <b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '999', // Default
			'disporder' => 2
		),

		// Spielername
		'userbirthdays_player' => array(
			'title' => 'Spielername',
			'description' => 'Wie lautet die FID/der Identifikator von dem Profilfeld/Steckbrieffeld Spielername?<br>
      <b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
			'optionscode' => 'text',
			'value' => '5', // Default
			'disporder' => 3
		),

		// Geburtstagstext
		'userbirthdays_text' => array(
			'title' => 'Geburtstagstext',
			'description' => 'Der Standardtext, welcher angezeigt werden soll bei einem Geburtstag. Zeilenumbrüche müssen mit <"br"> (ohne ") erfolgen',
			'optionscode' => 'textarea',
			'value' => 'Wir wünschen dir so viel Glück, wie der Regen Tropfen, so viel Liebe, wie die Sonne Strahlen und so viel Freude, wie der Himmel Sterne hat.<br>
      Wir wünschen dir einen wundervollen Tag, viel Freude, Gesundheit und Zufriedenheit für das nächste Jahr und viel Glück für alles, was du dir vornimmst.<br>
      Die schönsten Tage des letzten Jahres mögen die schlechtesten des kommenden Jahres sein. Lass dich feiern, genieß den Tag und bleib so, wie du bist!<br>
      Herzlichen Glückwunsch zum Geburtstag!', // Default
			'disporder' => 4
		),

		// Teambenachrichtigung
		'userbirthdays_team' => array(
			'title' => 'Teambenachrichtigung',
			'description' => 'Soll das Team eine Benachrichtigung bekommen über die User, welche an dem Tag Geburtstag haben?',
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 5
		),
	);

	foreach ($setting_array as $name => $setting) {
		$setting['name'] = $name;
		$setting['gid'] = $gid;
		$db->insert_query('settings', $setting);
	}

	rebuild_settings();

	// TEMPLATES ERSTELLEN
	$insert_array = array(
		'title' => 'index_userbirthdays',
		'template' => $db->escape_string('<table width="100%">
        <tr>
          <td class="thead">Herzlichen Glückwunsch zum Geburtstag, {$spieler}!</td>
        </tr>
        <tr>
          <td>{$birthday_text}</td>
        </tr>
      </table>'),
		'sid' => '-1',
		'version' => '',
		'dateline' => TIME_NOW
	);

	$db->insert_query("templates", $insert_array);

}

// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function userbirthdays_is_installed() {

	global $mybb;

	if (isset($mybb->settings['userbirthdays_birthday'])) {
		return true;
	}

	return false;
}

function userbirthdays_uninstall() {

	global $db;

	// EINSTELLUNGEN LÖSCHEN
	$db->delete_query('settings', "name LIKE 'userbirthdays%'");
	$db->delete_query('settinggroups', "name = 'userbirthdays'");

	rebuild_settings();

	// TEMPLATE LÖSCHEN
	$db->delete_query("templates", "title IN('index_userbirthdays')");
}

// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function userbirthdays_activate() {

	global $db, $cache;

	require MYBB_ROOT."/inc/adminfunctions_templates.php";

	// VARIABLEN EINFÜGEN
	find_replace_templatesets("header", "#".preg_quote('{$awaitingusers}')."#i", '{$awaitingusers}{$index_userbirthdays}{$team_userbirthdays}');
}

// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function userbirthdays_deactivate() {

	global $db, $cache;

	require MYBB_ROOT."/inc/adminfunctions_templates.php";

	// VARIABLEN ENTFERNEN
	find_replace_templatesets("header", "#".preg_quote('{$index_userbirthdays}{$team_userbirthdays}')."#i", '', 0);

}

##############################
### FUNKTIONEN - THE MAGIC ###
##############################

/// ADMIN-CP PEEKER
$plugins->add_hook('admin_config_settings_change', 'userbirthdays_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'userbirthdays_settings_peek');
function userbirthdays_settings_change(){
    
  global $db, $mybb, $userbirthdays_settings_peeker;

  $result = $db->simple_select('settinggroups', 'gid', "name='userbirthdays'", array("limit" => 1));
  $group = $db->fetch_array($result);
  $userbirthdays_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}

function userbirthdays_settings_peek(&$peekers){
  global $mybb, $userbirthdays_settings_peeker;

if ($userbirthdays_settings_peeker) {
     $peekers[] = 'new Peeker($("#setting_userbirthdays_field"), $("#row_setting_userbirthdays_birthday"), /^(0|1)/, false)';
  }
}

// ADMIN BEREICH //
// action handler fürs acp konfigurieren
function userbirthdays_admin_tools_action_handler(&$actions) {
	$actions['userbirthdays'] = array('active' => 'userbirthdays', 'file' => 'userbirthdays');
}

// Menü einfügen
function userbirthdays_admin_tools_menu(&$sub_menu) {
	global $mybb, $lang;

	$lang->load('userbirthdays');

	$sub_menu[] = [
		"id" => "userbirthdays",
		"title" => $lang->userbirthdays_manage,
		"link" => "index.php?module=tools-userbirthdays"
	];
}

// ACP Seiten
function userbirthdays_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file;

	$lang->load('userbirthdays');

	if ($page->active_action != 'userbirthdays') {
		return false;
	}

	// EINSTELLUNGEN ZIEHEN
	$field_setting = $mybb->settings['userbirthdays_field'];
	$playerfid_setting = $mybb->settings['userbirthdays_player'];
	$birthdayfid_setting = $mybb->settings['userbirthdays_birthday'];

	// Katjas Plugin - extra Tabelle bauen
	if ($field_setting == 1 OR !is_numeric($playerfid_setting)) {
		//ANFANG DES STRINGS BAUEN
		$selectstring = "LEFT JOIN (select um.uid as auid,";

		//FELDER DIE AKTIV SIND HOLEN
		if ($field_setting != 1) {
			$getfields = $db->simple_select("application_ucp_fields", "*", "active = 1 AND fieldname = '".$playerfid_setting."'");
		} else {
			$getfields = $db->simple_select("application_ucp_fields", "*", "active = 1");
		}

		//DIE FELDER DURCHGEHEN
		while ($searchfield = $db->fetch_array($getfields)) {
			//weiter im Querie, hier modeln wir unsere Felder ders users (apllication_ucp_fields taballe) zu einer Tabellenreihe wie die FELDER um -> name der Spalte ist fieldname, wert wie gehabt value
			$selectstring .= " max(case when um.fieldid ='{$searchfield['id']}' then um.value end) AS '{$searchfield['fieldname']}',";
		}

		$selectstring = substr($selectstring, 0, -1);
		$selectstring .= " from `" . TABLE_PREFIX . "application_ucp_userfields` as um group by uid) as fields ON auid = u.uid";

		$query_join = $selectstring;
	} else {
		$query_join = "";
	}

	// SPIELERNAME ÜBRERPRÜFEN
	if (is_numeric($playerfid_setting)) {
		$playerfid = "fid".$playerfid_setting;
	} else {
		$playerfid = "fields.".$playerfid_setting;
	}

	// Add to page navigation
	$page->add_breadcrumb_item($lang->userbirthdays_manage);

	if ($run_module == 'tools' && $action_file == 'userbirthdays') {

		// ÜBERSICHT
		if ($mybb->input['action'] == "" || !isset($mybb->input['action'])) {
			// Optionen im Header bilden
			$page->output_header($lang->userbirthdays_manage_overview);

			// Alle Geburtstage Button
			$sub_tabs['userbirthdays'] = [
				"title" => $lang->userbirthdays_manage_overview,
				"link" => "index.php?module=tools-userbirthdays",
				"description" => $lang->userbirthdays_manage_overview_desc

			];
			// Update Button
			$sub_tabs['userbirthdays_update'] = [
				"title" => $lang->userbirthdays_manage_update,
				"link" => "index.php?module=tools-userbirthdays&amp;action=update",
				"description" => $lang->userbirthdays_manage_update_desc
			];

			$page->output_nav_tabs($sub_tabs, 'userbirthdays');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

			// Übersichtsseite
			$form = new Form("index.php?module=tools-userbirthdays", "post");
			$form_container = new FormContainer($lang->userbirthdays_manage);
			// Informationen
			$form_container->output_row_header($lang->userbirthdays_overview_monthname, array('style' => 'text-align: justify; width: 10%;'));
			// Optionen
			$form_container->output_row_header($lang->userbirthdays_overview_user, array('style' => 'text-align: center;'));

			if ($field_setting == 2) {
				$monthsname = array(
					"1" => "Januar",
					"2" => "Februar",
					"3" => "März",
					"4" => "April",
					"5" => "Mai",
					"6" => "Juni",
					"7" => "Juli",
					"8" => "August",
					"9" => "September",
					"10" => "Oktober",
					"11" => "November",
					"12" => "Dezember"
				);
			} else {
				$monthsname = array(
					"01" => "Januar",
					"02" => "Februar",
					"03" => "März",
					"04" => "April",
					"05" => "Mai",
					"06" => "Juni",
					"07" => "Juli",
					"08" => "August",
					"09" => "September",
					"10" => "Oktober",
					"11" => "November",
					"12" => "Dezember"
				);
			}

			foreach ($monthsname as $month => $name) {

				$form_container->output_cell('<strong>'.htmlspecialchars_uni($name).'</strong>');

				// MyBB FELD - Datum T-M-YYYY
				if ($field_setting == 2) {
					$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
          LEFT JOIN ".TABLE_PREFIX."userfields uf
          ON u.uid = uf.ufid
          $query_join
          WHERE u.birthday LIKE '%-".$month."-%'
          AND as_uid = 0
          ORDER BY CAST(u.birthday AS signed) ASC, ".$playerfid." ASC
          ");

					$user_bit = "";
					while ($user = $db->fetch_array($query)) {

						$birthday = explode("-", $user['birthday']);

						if ($birthday[0] < 10) {
							$birthday[0] = "0".$birthday[0];
						}
						if ($birthday[1] < 10) {
							$birthday[1] = "0".$birthday[1];
						}

						$birthday = implode(".", $birthday);
						if (is_numeric($playerfid_setting)) {
							$user['username'] = build_profile_link($user['fid'.$playerfid_setting], $user['uid']);
						} else {
							$user['username'] = build_profile_link($user[$playerfid_setting], $user['uid']);
						}

						$user_bit .= $birthday." &raquo; ".$user['username']."<br>";
					}
				}
				// Profilfeld - Datum TT.MM.YYYY
				else if ($field_setting == 0) {
					$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
          LEFT JOIN ".TABLE_PREFIX."userfields uf
          ON u.uid = uf.ufid
          $query_join
          WHERE fid".$birthdayfid_setting." LIKE '%.".$month.".%'
          AND as_uid = 0
          ORDER BY CAST(fid".$birthdayfid_setting." AS signed) ASC, ".$playerfid." ASC
          ");

					$user_bit = "";
					while ($user = $db->fetch_array($query)) {

						if (is_numeric($playerfid_setting)) {
							$user['username'] = build_profile_link($user['fid'.$playerfid_setting], $user['uid']);
						} else {
							$user['username'] = build_profile_link($user[$playerfid_setting], $user['uid']);
						}

						$user_bit .= $user['fid'.$birthdayfid_setting]." &raquo; ".$user['username']."<br>";
					}
				}
				// Steckbrieffeld - Datum TT.MM.YYYY
				else if ($field_setting == 1) {

					$query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
          LEFT JOIN ".TABLE_PREFIX."userfields uf
          ON u.uid = uf.ufid
          $query_join
          WHERE fields.".$birthdayfid_setting." LIKE '%.".$month.".%'
          AND as_uid = 0
          ORDER BY CAST(fields.".$birthdayfid_setting." AS signed) ASC, ".$playerfid." ASC
          ");

					$user_bit = "";
					while ($user = $db->fetch_array($query)) {

						if (is_numeric($playerfid_setting)) {
							$user['username'] = build_profile_link($user['fid'.$playerfid_setting], $user['uid']);
						} else {
							$user['username'] = build_profile_link($user[$playerfid_setting], $user['uid']);
						}

						$user_bit .= $user[$birthdayfid_setting]." &raquo; ".$user['username']."<br>";
					}
				}
				$form_container->output_cell($user_bit);

				$form_container->construct_row();
			}


			$form_container->end();
			$form->end();
			$page->output_footer();
			exit;
		}

		// UPDATE
		if ($mybb->input['action'] == "update") {

			// Optionen im Header bilden
			$page->output_header($lang->userbirthdays_manage." - ".$lang->userbirthdays_manage_update);

			// Alle Geburtstage Button
			$sub_tabs['userbirthdays'] = [
				"title" => $lang->userbirthdays_manage_overview,
				"link" => "index.php?module=tools-userbirthdays",
				"description" => $lang->userbirthdays_manage_overview_desc

			];
			// Update Button
			$sub_tabs['userbirthdays_update'] = [
				"title" => $lang->userbirthdays_manage_update,
				"link" => "index.php?module=tools-userbirthdays&amp;action=update",
				"description" => $lang->userbirthdays_manage_update_desc
			];

			$page->output_nav_tabs($sub_tabs, 'userbirthdays_update');

			// UPDATE SACHEN
			if ($mybb->request_method == "post") {

				if (isset($mybb->input['update'])) {

					// EINSTELLUNGEN UPDATEN
					// Geburtstagsoption
					$update_field = array(
						'title' => 'Geburtstagsoption',
						'description' => 'Mit welcher Option werden die User Geburtstage erfasst?',
						'optionscode' => 'select\n0=Profilfeld\n1=Steckbrief-Plugin von Risuena\n2=MyBB-Geburtstagsfeld',
					);
					$db->update_query("settings", $update_field, "name = 'userbirthdays_field'");

					// Geburtstagsfeld
					$update_birthday = array(
            'description' => 'Wie lautet die FID/der Identifikator von deinem Profilfeld/Steckbrieffeld Geburtstagsfeld? <b>Format:</b>TT.MM.JJJJ<br>   
            <b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
					);
					$db->update_query("settings", $update_birthday, "name = 'userbirthdays_birthday'");

					// Spielername
					$update_player = array(
						'description' => 'Wie lautet die FID/der Identifikator von deinem Profilfeld/Steckbrieffeld Spielername?<br>               
            <b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
					);
					$db->update_query("settings", $update_player, "name = 'userbirthdays_player'");

				}

				$mybb->input['module'] = "userbirthdays";
				$mybb->input['action'] = 'Das Update wurde erfolgreich durchgeführt!';

				flash_message('Das Update wurde erfolgreich durchgeführt!', 'success');
				admin_redirect("index.php?module=tools-userbirthdays&action=update");
			}

			// Überprüfen, ob Update nötig ist
			// Änderungen zu alten Version
			$oldSettingsOptions = $db->fetch_field($db->query("SELECT optionscode FROM ".TABLE_PREFIX."settings WHERE name = 'userbirthdays_field'"), "optionscode");
			$newSettingsOptions = '1=Steckbrief-Plugin von Risuena';
			$pos = strpos($oldSettingsOptions, $newSettingsOptions);


			$form = new Form("index.php?module=tools-userbirthdays&amp;action=update", "post");
			$form_container = new FormContainer($lang->userbirthdays_update);
			// Name
			$form_container->output_row_header($lang->userbirthdays_update_name);
			// Optionen
			$form_container->output_row_header($lang->userbirthdays_update_option, array('style' => 'text-align: center; width: 30%;'));

			$form_container->output_cell($lang->userbirthdays_update_cell_name);

			// BUTTON ANZEIGEN ODER NICHT
			if ($pos === false) {
				$form_container->output_cell("<center>".$form->generate_submit_button($lang->userbirthdays_update_cell_button, array("name" => "update"))."</center>");
			} else {
				//update durchgeführt
				$form_container->output_cell("<center>".$lang->userbirthdays_update_none."</center>");
			}

			$form_container->construct_row();

			$form_container->end();
			$form->end();
			$page->output_footer();
			exit;
		}

	}
}

// INDEX ANZEIGE
function userbirthdays_global() {

	global $db, $mybb, $templates, $theme, $header, $headerinclude, $footer, $lang, $index_userbirthdays, $team_userbirthdays;

	// USER-ID
	$user_id = $mybb->user['uid'];

	if ($user_id == 0) return;

	// EINSTELLUNGEN ZIEHEN
	$field_setting = $mybb->settings['userbirthdays_field'];
	$playerfid_setting = $mybb->settings['userbirthdays_player'];
	$birthdayfid_setting = $mybb->settings['userbirthdays_birthday'];

	$birthday_text = $mybb->settings['userbirthdays_text'];
	$team_setting = $mybb->settings['userbirthdays_team'];


	// Katjas Plugin - extra Tabelle bauen
	if ($field_setting == 1 OR !is_numeric($playerfid_setting)) {

		//ANFANG DES STRINGS BAUEN
		$selectstring = "LEFT JOIN (select um.uid as auid,";

		//FELDER DIE AKTIV SIND HOLEN
		if ($field_setting != 1) {
			$getfields = $db->simple_select("application_ucp_fields", "*", "active = 1 AND fieldname = '".$playerfid_setting."'");
		} else {
			$getfields = $db->simple_select("application_ucp_fields", "*", "active = 1");
		}

		//DIE FELDER DURCHGEHEN
		while ($searchfield = $db->fetch_array($getfields)) {
			//weiter im Querie, hier modeln wir unsere Felder ders users (apllication_ucp_fields taballe) zu einer Tabellenreihe wie die FELDER um -> name der Spalte ist fieldname, wert wie gehabt value
			$selectstring .= " max(case when um.fieldid ='{$searchfield['id']}' then um.value end) AS '{$searchfield['fieldname']}',";
		}

		$selectstring = substr($selectstring, 0, -1);
		$selectstring .= " from `" . TABLE_PREFIX . "application_ucp_userfields` as um group by uid) as fields ON auid = u.uid";

		$query_join = $selectstring;
	} else {
		$query_join = "";
	}

	// SPIELERNAME ÜBRERPRÜFEN
	if (is_numeric($playerfid_setting)) {
		$playerfid = "fid".$playerfid_setting;
	} else {
		$playerfid = "fields.".$playerfid_setting;
	}

	// ACCOUNTSWITCHER
	// Zusatzfunktion - CharakterUID-string
	$charas = userbirthdays_get_allchars($user_id);
	$charastring = implode(",", array_keys($charas));
	$mainID = $db->fetch_field($db->simple_select("users", "as_uid", "uid = '".$user_id."'"), "as_uid");
	if (empty($mainID)) {
		$mainID = $user_id;
	}

	// GEBURTSTAGS-GRUSS
	// MyBB Feld - T-M
	if ($field_setting == 2) {

		// AKTUELLES DATUM - T-M
		$datenow = date("j-n", time());

		$birthday_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
    LEFT JOIN ".TABLE_PREFIX."userfields uf
    ON (u.uid = uf.ufid)
    $query_join
    WHERE u.birthday LIKE '".$datenow."-%'
    AND uid IN ('".$charastring."')
    ");

		while ($user = $db->fetch_array($birthday_query)) {

			$userid = $user['uid'];

			if (is_numeric($playerfid_setting)) {
				$spieler = $user['fid'.$playerfid_setting];
			} else {
				$spieler = $user[$playerfid_setting];
			}


			eval("\$index_userbirthdays = \"".$templates->get ("index_userbirthdays")."\";");
		}
	} else {

		// AKTUELLES DATUM - TT.MM
		$datenow = date("d.m", time());

		// Profilfeld
		if ($field_setting == 0) {
			$birthday_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u
      LEFT JOIN ".TABLE_PREFIX."userfields uf
      ON (u.uid = uf.ufid)
      $query_join
      WHERE fid".$birthdayfid_setting." LIKE '".$datenow.".%'
      AND uid IN ('".$charastring."')
      ");
		} else {
			$birthday_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u
      LEFT JOIN ".TABLE_PREFIX."userfields uf
      ON (u.uid = uf.ufid)
      $query_join
      WHERE fields.".$birthdayfid_setting." LIKE '".$datenow.".%'
      AND uid IN ('".$charastring."')
      ");
		}

		while ($user = $db->fetch_array($birthday_query)) {

			$userid = $user['uid'];
			if (is_numeric($playerfid_setting)) {
				$spieler = $user['fid'.$playerfid_setting];
			} else {
				$spieler = $user[$playerfid_setting];
			}

			eval("\$index_userbirthdays = \"".$templates->get ("index_userbirthdays")."\";");
		}
	}

	// TEAM ANZEIGE
	if ($team_setting == 1 AND $mybb->usergroup['canmodcp'] == "1") {

		// MyBB Feld - T-M
		if ($field_setting == 2) {

			// AKTUELLES DATUM - T-M
			$datenow = date("j-n", time());

			$birthdayuser_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
      LEFT JOIN ".TABLE_PREFIX."userfields uf
      ON (u.uid = uf.ufid)
      $query_join
      WHERE u.birthday LIKE '".$datenow."-%'
      AND as_uid = '0'
      ");

			if ($db->num_rows($birthdayuser_query) > 0) {

				$birthday_user = "";
				while ($user = $db->fetch_array($birthdayuser_query)) {
					$userid = $user['uid'];

					if (is_numeric($playerfid_setting)) {
						$spieler = $user['fid'.$playerfid_setting];
					} else {
						$spieler = $user[$playerfid_setting];
					}

					$birthday_user .= build_profile_link($spieler, $userid).", ";
				}

				$birthday_user = substr($birthday_user, 0, -2);

				if ($db->num_rows($birthdayuser_query) == 1) {
					$team_userbirthdays = "<div class=\"red_alert\">Heute hat ".$birthday_user." Geburtstag!</div>";
				} else {
					$team_userbirthdays = "<div class=\"red_alert\">Heute haben <b>".$db->num_rows($birthdayuser_query)."</b> User Geburtstag! Und zwar: ".$birthday_user."</div>";
				}
			} else {
				$team_userbirthdays = "";
			}

		} else {

			// AKTUELLES DATUM - TT.MM
			$datenow = date("d.m", time());

			// Profilfeld
			if ($field_setting == 0) {
				$birthdayuser_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u
        LEFT JOIN ".TABLE_PREFIX."userfields uf
        ON (u.uid = uf.ufid)
        $query_join
        WHERE fid".$birthdayfid_setting." LIKE '".$datenow.".%'
        AND as_uid = '0'
        ");
			} else {
				$birthdayuser_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u
        LEFT JOIN ".TABLE_PREFIX."userfields uf
        ON (u.uid = uf.ufid)
        $query_join
        WHERE fields.".$birthdayfid_setting." LIKE '".$datenow.".%'
        AND as_uid = '0'
        ");
			}

			if ($db->num_rows($birthdayuser_query) > 0) {

				$birthday_user = "";
				while ($user = $db->fetch_array($birthdayuser_query)) {
					$userid = $user['uid'];

					if (is_numeric($playerfid_setting)) {
						$spieler = $user['fid'.$playerfid_setting];
					} else {
						$spieler = $user[$playerfid_setting];
					}

					$birthday_user .= build_profile_link($spieler, $userid).", ";
				}

				$birthday_user = substr($birthday_user, 0, -2);

				if ($db->num_rows($birthdayuser_query) == 1) {
					$team_userbirthdays = "<div class=\"red_alert\">Heute hat ".$birthday_user." Geburtstag!</div>";
				} else {
					$team_userbirthdays = "<div class=\"red_alert\">Heute haben <b>".$db->num_rows($birthdayuser_query)."</b> User Geburtstag! Und zwar: ".$birthday_user."</div>";
				}
			} else {
				$team_userbirthdays = "";
			}
		}

	}
}

// ACCOUNTSWITCHER HILFSFUNKTION
function userbirthdays_get_allchars($user_id) {

	global $db,
	$mybb;

	//für den fall nicht mit hauptaccount online
	$as_uid = intval($mybb->user['as_uid']);

	$charas = array();

	if ($as_uid == 0) {
		// as_uid = 0 wenn hauptaccount oder keiner angehangen
		$get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $user_id) OR (uid = $user_id) ORDER BY username");
	} else if ($as_uid != 0) {
		//id des users holen wo alle an gehangen sind
		$get_all_users = $db->query("SELECT uid,username FROM " . TABLE_PREFIX . "users WHERE (as_uid = $as_uid) OR (uid = $user_id) OR (uid = $as_uid) ORDER BY username");
	}

	while ($users = $db->fetch_array($get_all_users)) {
		$uid = $users['uid'];
		$charas[$uid] = $users['username'];
	}

	return $charas;
}
