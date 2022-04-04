<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
// Die Informationen, die im Pluginmanager angezeigt werden
function userbirthdays_info(){

  return array(
    "name"		=> "User Geburtstage",
		"description"	=> "Durch dieses Plugin bekommen User eine persönliche Geburtstagsnachricht auf dem Index.",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
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
      'title' => 'Profilfeld oder MyBB-Geburtstagsfeld',
      'description' => 'In welcher Option werden die User Geburtstage gespeichert?',
      'optionscode' => 'select\n0=Profilfeld\n1=MyBB-Geburtstagsfeld',
      'value' => '0', // Default
      'disporder' => 1
    ),
  
    // Geburtstagsfeld
    'userbirthdays_birthday' => array(
      'title' => 'Geburtstagsfeld',
      'description' => 'Gib hier die ID von deinem Profilfeld ein, wo man den Geburtstag einträgt.',
      'optionscode' => 'text',
      'value' => '999', // Default
      'disporder' => 2
    ),
      
    // Spielername
    'userbirthdays_player' => array(
      'title' => 'Spielername',
      'description' => 'Gib hier die ID von deinem Profilfeld ein, wo man den Spielernamen einträgt.',
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
                Die schönsten Tage des letzten Jahres mögen die Schlechtesten des kommen sein. Lass dich feiern, genieß den Tag und bleib so, wie du bist!<br>
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
    
  foreach($setting_array as $name => $setting)
  {
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
function userbirthdays_is_installed(){
   
  global $mybb;
  
  if(isset($mybb->settings['userbirthdays_birthday'])) {
    return true;  
  }
  
  return false;
}

// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function userbirthdays_uninstall(){
   
  global $db;

  // EINSTELLUNGEN LÖSCHEN
  $db->delete_query('settings', "name LIKE 'userbirthdays%'");  
  $db->delete_query('settinggroups', "name = 'userbirthdays'");
  
  rebuild_settings();

  // TEMPLATE LÖSCHEN
  $db->delete_query("templates", "title IN('index_userbirthdays')");
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function userbirthdays_activate(){
  
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

$plugins->add_hook('global_start', 'userbirthdays_global');

// INDEX ANZEIGE
function userbirthdays_global() {
 
  global $db, $mybb, $templates, $theme, $header, $headerinclude, $footer, $lang, $index_userbirthdays, $team_userbirthdays;

  // USER-ID  
  $user_id = $mybb->user['uid'];

  // EINSTELLUNGEN ZIEHEN 
  $field_setting = $mybb->settings['userbirthdays_field'];
  $playerfid_setting = $mybb->settings['userbirthdays_player'];
  $playerfid = "fid".$playerfid_setting;
  $birthdayfid_setting = $mybb->settings['userbirthdays_birthday'];
  $birthdayfid = "fid".$birthdayfid_setting;  
  $birthday_text = $mybb->settings['userbirthdays_text'];
  $team_setting = $mybb->settings['userbirthdays_team'];

  // ACCOUNTSWITCHER
  $charas = userbirthdays_get_allchars($user_id);
  $charastring = implode(",", array_keys($charas));
  

  // USER ANZEIGE
  if ($field_setting == 0) {
    
    // AKTUELLES DATUM - TT.MM  
    $datenow = date("d.m", time());

    $birthday_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u    
    LEFT JOIN ".TABLE_PREFIX."userfields uf    
    ON (u.uid = uf.ufid) 
    WHERE $birthdayfid LIKE '$datenow%'
    AND uid IN ({$charastring})
    ");
      
    while($user = $db->fetch_array($birthday_query)) {

      $userid = $user['uid'];     
      $spieler = $user[$playerfid];

      eval("\$index_userbirthdays = \"".$templates->get ("index_userbirthdays")."\";");
    }

  } else {
      
    // AKTUELLES DATUM - TT.M  
    $datenow = date("j-n", time());

    $birthday_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u    
    LEFT JOIN ".TABLE_PREFIX."userfields uf    
    ON (u.uid = uf.ufid) 
    WHERE birthday LIKE '$datenow%'
    AND uid IN ({$charastring})
    ");
      
    while($user = $db->fetch_array($birthday_query)) {

      $userid = $user['uid'];     
      $spieler = $user[$playerfid];

      eval("\$index_userbirthdays = \"".$templates->get ("index_userbirthdays")."\";");
    }

  }


  // TEAM ANZEIGE
  if ($team_setting == 1)  {

    // PROFILFELD
    if ($field_setting == 0) {
    
      // AKTUELLES DATUM - TT.MM  
      $datenow = date("d.m", time());

      // GEBURSTAGE ZÄHLEN
      $countbirthdays = $db->fetch_field($db->query("SELECT COUNT(*) AS geburstage FROM ".TABLE_PREFIX."userfields uf
      WHERE $birthdayfid LIKE '$datenow%'
      "), 'geburstage');

      if ($countbirthdays > 0) {

        $birthdayuser_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u    
        LEFT JOIN ".TABLE_PREFIX."userfields uf    
        ON (u.uid = uf.ufid) 
        WHERE $birthdayfid LIKE '$datenow%'
        AND as_uid = '0'
        ");
   
        $birthday_user = "";
        
        while($user = $db->fetch_array($birthdayuser_query)) {

          $userid = $user['uid'];        
          $spieler = $user[$playerfid];

          $birthday_user .= build_profile_link($spieler, $userid).", ";

        }

        $team_userbirthdays = "<div class=\"red_alert\">Heute haben <b>".$countbirthdays."</b> User Geburtstag! Und zwar: ".$birthday_user."</div>";

      } else {
        $team_userbirthdays = "";
      }

    } else {
      
      // AKTUELLES DATUM - TT.M  
      $datenow = date("j-n", time());

      // GEBURSTAGE ZÄHLEN
      $countbirthdays = $db->fetch_field($db->query("SELECT COUNT(*) AS geburstage FROM ".TABLE_PREFIX."users u
      WHERE birthday LIKE '$datenow%'
      "), 'geburstage');

      if ($countbirthdays > 0) {

        $birthdayuser_query = $db->query(" SELECT * FROM ".TABLE_PREFIX."users u    
        LEFT JOIN ".TABLE_PREFIX."userfields uf    
        ON (u.uid = uf.ufid) 
        WHERE birthday LIKE '$datenow%'
        AND as_uid = '0'
        ");
   
        $birthday_user = "";
        
        while($user = $db->fetch_array($birthdayuser_query)) {

          $userid = $user['uid'];        
          $spieler = $user[$playerfid];

          $birthday_user .= build_profile_link($spieler, $userid).", ";

        }

        $team_userbirthdays = "<div class=\"red_alert\">Heute haben <b>".$countbirthdays."</b> User Geburtstag! Und zwar: ".$birthday_user."</div>";

      } else {
        $team_userbirthdays = "";
      }

    }

  } else {
    $team_userbirthdays = "";
  }
}

// ACCOUNTSWITCHER HILFSFUNKTION
function userbirthdays_get_allchars($user_id) {
   
  global $db, $mybb;

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
