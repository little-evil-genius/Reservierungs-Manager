<?php
/**
 * Reservierungs-Manager  - by little.evil.genius
 * https://github.com/little-evil-genius/Reservierungs-Manager
 * https://storming-gates.de/member.php?action=profile&uid=1712
 * Dieses Plugin erweitert MyBB um ein flexibles Reservierungssystem. 
 * Es ermöglicht, Reservierungen für verschiedene Kategorien - beispielsweise Avatarpersonen, Nachnamen, Canon-Charaktere oder andere forumsspezifische Inhalte - zentral zu verwalten. 
 * Reservierungen laufen automatisch nach einer festgelegten Frist ab und werden selbstständig entfernt, sodass das Team keine manuelle Kontrolle oder Pflege übernehmen muss.
*/

// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// HOOKS
$plugins->add_hook("admin_config_settings_change", "reservations_settings_change");
$plugins->add_hook("admin_settings_print_peekers", "reservations_settings_peek");
$plugins->add_hook("admin_rpgstuff_action_handler", "reservations_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_permissions", "reservations_admin_rpgstuff_permissions");
$plugins->add_hook("admin_rpgstuff_menu", "reservations_admin_rpgstuff_menu");
$plugins->add_hook("admin_load", "reservations_admin_manage");
$plugins->add_hook("admin_rpgstuff_update_stylesheet", "reservations_admin_update_stylesheet");
$plugins->add_hook("admin_rpgstuff_update_plugin", "reservations_admin_update_plugin");
$plugins->add_hook("misc_start", "reservations_misc");
$plugins->add_hook('showthread_start', 'reservations_showthread_form');
$plugins->add_hook('showthread_end', 'reservations_showthread_output');
$plugins->add_hook('global_intermediate', 'reservations_global');
$plugins->add_hook("modcp_nav", "reservations_modcp_nav");
$plugins->add_hook("modcp_start", "reservations_modcp");
$plugins->add_hook('fetch_wol_activity_end', 'reservations_online_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'reservations_online_location');
 
// Die Informationen, die im Pluginmanager angezeigt werden
function reservations_info()
{
	return array(
		"name"		=> "Reservierungs-Manager",
		"description"	=> "Das Plugin erweitert das Board um ein dynamisches Reservierungssystem.",
		"website"	=> "https://github.com/little-evil-genius/Reservierungs-Manager",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.0",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function reservations_install() {
    
    global $db, $lang, $cache;

    // SPRACHDATEI
    $lang->load("reservations");

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message($lang->reservations_error_rpgstuff, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->reservations_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKTABELLEN
    reservations_database();

    // EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
    $setting_group = array(
        'name'          => 'reservations',
        'title'         => 'Reservierungs-Manager',
        'description'   => 'Einstellungen für den Reservierungs-Manager',
        'disporder'     => $maxdisporder+1,
        'isdefault'     => 0
    );
    $db->insert_query("settinggroups", $setting_group);

    // Einstellungen
    reservations_settings();
    rebuild_settings();

    // TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "reservations",
        "title" => $db->escape_string("Reservierungs-Manager"),
    );
    $db->insert_query("templategroups", $templategroup);
    // Templates 
    reservations_templates();
    
    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $css = reservations_stylesheet();
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "reservations.css"), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}

	// Task hinzufügen
    $date = new DateTime(date("d.m.Y", strtotime('+1 day')));
    $reservationsTask = array(
        'title' => 'Reservierungen',
        'description' => 'löscht abgelaufene Reservierungen',
        'file' => 'reservations',
        'minute' => 0,
        'hour' => 0,
        'day' => '*',
        'month' => '*',
        'weekday' => '*',
        'nextrun' => $date->getTimestamp(),
        'logging' => 1,
        'locked' => 1
    );
    $db->insert_query('tasks', $reservationsTask);
    $cache->update_tasks();
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function reservations_is_installed() {

    global $db;

    if ($db->table_exists("reservations")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function reservations_uninstall() {

    global $db, $cache;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("reservations_types")) {
        $db->drop_table("reservations_types");
    }
    if($db->table_exists("reservations_grouppermissions")) {
        $db->drop_table("reservations_grouppermissions");
    }
    if($db->table_exists("reservations")) {
        $db->drop_table("reservations");
    }

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'reservations'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE 'reservations%'");
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'reservations%'");
    $db->delete_query('settinggroups', "name = 'reservations'");
    rebuild_settings();

    // STYLESHEET ENTFERNEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
	$db->delete_query("themestylesheets", "name = 'reservations.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}

	// TASK LÖSCHEN
	$db->delete_query('tasks', "file='reservations'");
	$cache->update_tasks();
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function reservations_activate() {

    // VARIABLEN EINFÜGEN
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('showthread', '#'.preg_quote('{$ratethread}').'#', '{$ratethread} {$reservations_showthread}');
	find_replace_templatesets('header', '#'.preg_quote('{$modnotice}').'#', '{$modnotice}{$reservations_team} {$reservations_index}');
	find_replace_templatesets('modcp_nav_users', '#'.preg_quote('{$nav_ipsearch}').'#', '{$nav_ipsearch} {$nav_reservations}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function reservations_deactivate() {
    
    // VARIABLEN ENTFERNEN
    require MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_reservations}')."#i", '', 0);
    find_replace_templatesets("header", "#".preg_quote('{$reservations_index}')."#i", '', 0);
    find_replace_templatesets("header", "#".preg_quote('{$reservations_team}')."#i", '', 0);
    find_replace_templatesets("showthread", "#".preg_quote('{$reservations_showthread}')."#i", '', 0);
}

######################
### HOOK FUNCTIONS ###
######################

// EINSTELLUNGEN VERSTECKEN
function reservations_settings_change(){
    
    global $db, $mybb, $reservations_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='reservations'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $reservations_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function reservations_settings_peek(&$peekers){

    global $reservations_settings_peeker;

    if ($reservations_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_reservations_system"), $("#row_setting_reservations_thread"),/2/,true)';
        $peekers[] = 'new Peeker($(".setting_reservations_system"), $("#row_setting_reservations_lists_nav, #row_setting_reservations_lists_menu, #row_setting_reservations_lists_menu_tpl"),/1/,true)'; 
    }
}

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function reservations_admin_rpgstuff_action_handler(&$actions) {
	$actions['reservations_types'] = array('active' => 'reservations_types', 'file' => 'reservations_types');
	$actions['reservations_data'] = array('active' => 'reservations_data', 'file' => 'reservations_data');
}

// Benutzergruppen-Berechtigungen im ACP
function reservations_admin_rpgstuff_permissions(&$admin_permissions) {

	global $lang;
	
    $lang->load('reservations');

	$admin_permissions['reservations_types'] = $lang->reservations_permission_types;
    $admin_permissions['reservations_data'] = $lang->reservations_permission_data;

	return $admin_permissions;
}

// im Menü einfügen
function reservations_admin_rpgstuff_menu(&$sub_menu) {

    global $lang, $db;

    $lang->load('reservations');

    $sub_menu[] = [
        'id'    => 'reservations_types',
        'title' => $lang->reservations_nav_types,
        'link'  => 'index.php?module=rpgstuff-reservations_types'
    ];

    $types = $db->fetch_array(
        $db->query("SELECT identification FROM ".TABLE_PREFIX."reservations_types ORDER BY disporder ASC, title ASC LIMIT 1")
    );

    if($types) {
        $sub_menu[] = [
            'id'    => 'reservations_data',
            'title' => $lang->reservations_nav_data,
            'link'  => 'index.php?module=rpgstuff-reservations_data&action='.$types['identification']
        ];
    }
}

// die Verwaltung
function reservations_admin_manage() {

    global $mybb, $db, $lang, $page, $run_module, $action_file;

    if ($page->active_action != 'reservations_types' AND $page->active_action != 'reservations_data') {
		return false;
	}

	$lang->load('reservations');

    // TYPEN
	if ($run_module == 'rpgstuff' && $action_file == 'reservations_types') {

        $checkingoption_list = array(
            "0" => $lang->reservations_types_form_checking_select,
            "1" => $lang->reservations_types_form_checking_select_field,
            "2" => $lang->reservations_types_form_checking_select_name
        );

        $checkingname_list = array(
            "0" => $lang->reservations_types_form_checking_name_select_first,
            "1" => $lang->reservations_types_form_checking_name_select_last,
            "2" => $lang->reservations_types_form_checking_name_select_full
        );

		// Add to page navigation
		$page->add_breadcrumb_item($lang->reservations_types_breadcrumb_main, "index.php?module=rpgstuff-reservations_types");

        // ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header($lang->reservations_types_overview_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types",
				"description" => $lang->reservations_types_tabs_overview_desc
			];
            $sub_tabs['add_type'] = [
				"title" => $lang->reservations_types_tabs_add_type,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=add_type"
			];
            $page->output_nav_tabs($sub_tabs, 'overview');

            if ($mybb->request_method == "post" && $mybb->get_input('do') == "save_disporder") {

                if(!is_array($mybb->get_input('disporder', MyBB::INPUT_ARRAY))) {
                    flash_message($lang->reservations_types_overview_disporder_error, 'error');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }

                foreach($mybb->get_input('disporder', MyBB::INPUT_ARRAY) as $field_id => $order) {
        
                    $update_sort = array(
                        "disporder" => (int)$order    
                    );

                    $db->update_query("reservations_types", $update_sort, "rtid = '".(int)$field_id."'");
                }

                flash_message($lang->reservations_types_overview_disporder_flash, 'success');
                admin_redirect("index.php?module=rpgstuff-reservations_types");
            }

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Übersichtsseite
			$form = new Form("index.php?module=rpgstuff-reservations_types", "post", "", 1);
            echo $form->generate_hidden_field("do", 'save_disporder');
            $form_container = new FormContainer($lang->reservations_types_overview_container);
            $form_container->output_row_header($lang->reservations_types_overview_container_type, array('style' => 'text-align: left;'));
            $form_container->output_row_header($lang->reservations_types_overview_container_disporder, array('style' => 'text-align: center; width: 5%;'));
            $form_container->output_row_header($lang->reservations_options_container, array('style' => 'text-align: center; width: 10%;'));
			
            // Alle Typen
			$query_types = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_types
            ORDER BY disporder ASC, title ASC
            ");

            // Typen auslesen
			while ($typ = $db->fetch_array($query_types)) {

                // leer laufen lassen
                $rtid = "";
                $identification = "";
                $title = "";
                $disporder = "";
                $gender = "";
                $checkoption = "";
                $checkfield = "";
                $checkname = "";
                $groupsplit = "";
                $condensedgroups = "";

                // mit Infos füllen
                $rtid = $typ['rtid'];
                $identification = $typ['identification'];
                $title = $typ['title'];
                $disporder = $typ['disporder'];
                $gender = $typ['gender'];
                $checkoption = $typ['checkoption'];
                $checkfield = $typ['checkfield'];
                $checkname = $typ['checkname'];
                $groupsplit = $typ['groupsplit'];
                $condensedgroups = $typ['condensedgroups'];

                // Vergleich
                if ($checkoption > 0) {
                    // Profilfeld/Steckbrieffeld
                    if ($checkoption == 1) {
                        if (is_numeric($checkfield)) {
                            $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid= ".$checkfield), "name");
                            $checking = $lang->reservations_types_overview_bit_checking_profilefield;
                        } else {
                            $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$checkfield."'"), "label");
                            $checking = $lang->reservations_types_overview_bit_checking_applicationfield;
                        }
                    }
                    // Name
                    else {
                        if ($checkname == 0) {
                            $fieldname = $lang->reservations_types_overview_bit_checking_name_first;
                        } else if ($checkname == 1) {
                            $fieldname = $lang->reservations_types_overview_bit_checking_name_last;
                        } else {
                            $fieldname = $lang->reservations_types_overview_bit_checking_name_full;
                        }
                        $checking = $lang->reservations_types_overview_bit_checking_name;
                    }

                    $checkingOption = $lang->sprintf($lang->reservations_types_overview_bit_checking, $checking, $fieldname);
                } else {
                    $checkingOption = $lang->reservations_types_overview_bit_checking_none;
                }

                // Geschlecht
                if ($gender == 1) {
                    $genderOption = $lang->reservations_types_overview_bit_gender;
                } else {
                    $genderOption = $lang->reservations_types_overview_bit_gender_none;
                }

                // Gruppenunterteilung
                if ($groupsplit == 1) {
                    $splitOption = $lang->reservations_types_overview_bit_groupsplit;

                    // Zusammengefasste Gruppen
                    if (!empty($condensedgroups)) {

                        $split_condensedgroups = explode("\n", $condensedgroups);

                        $condensedgroups = "";
                        foreach ($split_condensedgroups as $condensedgroup) {
                            $ids = array_map('trim', explode(',', $condensedgroup));

                            $groupnames = [];
                            foreach ($ids as $id) {
                                $groupnames[] = $db->fetch_field($db->simple_select("reservations_grouppermissions", "name", "rgid = '".$id."'"), "name");
                            }
                            $groupnames = implode(" &amp; ", $groupnames);
                            $condensedgroups .= "<li>".$groupnames." <a href=\"index.php?module=rpgstuff-reservations_types&amp;action=delete_condensedgroups&amp;rtid=".$rtid."&amp;condensedID=".$condensedgroup."&amp;my_post_key={$mybb->post_code}\" onClick=\"return AdminCP.deleteConfirmation(this, '".$lang->reservations_types_condensedgroups_delete_notice."')\">[x]</a></li>";
                        }

                        $condensedgroupsOption = "<b>Zusammengefasste Gruppen:</b><br>
                        <ul style=\"margin: 0.3em 1.5em 0.5em;padding-inline-start: 0px;\">
                        ".$condensedgroups."
                        </ul>";

                    } else {
                        $condensedgroupsOption = "";
                    }
                } else {
                    $splitOption = $lang->reservations_types_overview_bit_groupsplit_none;
                    $condensedgroupsOption = "";
                }

                // GRUPPENBERECHTIGUNGEN
                // Alle Berechtigungen
                $query_grouppermissions = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                ORDER BY disporder ASC, name ASC
                ");

                $grouppermissions = "";
                $grouppermission_none = $lang->reservations_types_grouppermission_overview_none;
                $grouppermission_infinite = $lang->reservations_types_grouppermission_overview_infinite;
                while ($permission = $db->fetch_array($query_grouppermissions)) {

                    // leer laufen lassen
                    $rgid = "";
                    $groups = "";
                    $name = "";
                    $disporderG = "";
                    $maxcount = ""; 
                    $duration = ""; 
                    $extend = ""; 
                    $extendtime = ""; 
                    $extendcount = ""; 
                    $lockcount = ""; 

                    // mit Infos füllen
                    $rgid = $permission['rgid'];
                    $groups = $permission['usergroups'];
                    $name = $permission['name'];
                    $disporderG = $permission['disporder'];
                    $maxcount = $permission['maxcount'];
                    $duration = $permission['duration']; 
                    $extend = $permission['extend'];
                    $extendtime = $permission['extendtime'];
                    $extendcount = $permission['extendcount'];
                    $lockcount = $permission['lockcount'];

                    // Gruppen
                    $groupsArray = array_map('trim', explode(',', $groups));
                    $groupnames = [];
                    foreach ($groupsArray as $gid) {
                        $groupnames[] = $db->fetch_field($db->simple_select("usergroups", "title", "gid= ".$gid), "title");
                    }
                    $usergroups = $lang->sprintf($lang->reservations_types_grouppermission_overview_groups, implode(', ', $groupnames));

                    // Anzahl
                    if($maxcount > 0) {
                        $maxcountOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_maxcount, $maxcount);
                    } else {
                        $maxcountOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_maxcount, $grouppermission_infinite);
                    }

                    // Dauer
                    if($duration > 0) {
                        $durationOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_duration, $duration);
                    } else {
                        $durationOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_duration, $grouppermission_infinite);
                    }

                    // Verlängerungen
                    if($extend == 1) {
                        $extendoptions = $lang->sprintf($lang->reservations_types_grouppermission_overview_extend_count, $extendcount, $extendtime);
                        $extendOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_extend, $extendoptions);
                    } else {
                        $extendOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_extend, $grouppermission_none);
                    }

                    // Sperre
                    if($lockcount > 0) {
                        $lockoptions = $lang->sprintf($lang->reservations_types_grouppermission_overview_lock_count, $lockcount);
                        $lockOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_lock, $lockoptions);
                    } else {
                        $lockOption = $lang->sprintf($lang->reservations_types_grouppermission_overview_lock, $grouppermission_none);
                    }
                    

					$grouppermissions .= "<div style=\"margin: 0.2em 1em 0.5em 0;\">
					<b>".$name."</b><br>
					<i>".$usergroups."</i>
					<ul style=\"margin: 0.3em 1.5em 0.5em;padding-inline-start: 0px;\">
					<li>".$maxcountOption."</li>
					<li>".$durationOption."</li>
					<li>".$extendOption."</li>
					<li>".$lockOption."</li> 
					</ul>
					<a href=\"index.php?module=rpgstuff-reservations_types&amp;action=edit_grouppermission&amp;rgid=".$rgid."\">".$lang->reservations_types_grouppermission_overview_edit."</a> | 
					<a href=\"index.php?module=rpgstuff-reservations_types&amp;action=delete_grouppermission&amp;rgid=".$rgid."&amp;my_post_key={$mybb->post_code}\" onClick=\"return AdminCP.deleteConfirmation(this, '".$lang->reservations_types_grouppermission_delete_notice."')\">".$lang->reservations_types_grouppermission_overview_delete."</a>
					</div>";
                }

                // Keine Berechtigungen
                if($db->num_rows($query_grouppermissions) == 0){
                   $grouppermissions = $lang->reservations_types_grouppermission_overview_noElements;
                }

                // AUSGABE DER INFOS
				$form_container->output_cell("<a href=\"index.php?module=rpgstuff-reservations_types&amp;action=edit_type&amp;rtid=".$rtid."\"><strong>".$title."</strong></a> (".$identification.")
				<p>".$checkingOption."<br>
				".$genderOption." | ".$splitOption."</p>
				<div style=\"display: flex;flex-wrap: wrap;margin: 0.3em;\">".$grouppermissions."</div>
				".$condensedgroupsOption."
				");

				// SORTIERUNG
				$form_container->output_cell($form->generate_numeric_field("disporder[{$rtid}]", $disporder, array('style' => 'width: 80%; text-align: center;', 'min' => 0)), array("class" => "align_center"));

				// OPTIONEN
				$popup = new PopupMenu("reservationstype_".$rtid, $lang->reservations_options_popup);	
                $popup->add_item(
                    $lang->reservations_options_popup_types_edit,
                    "index.php?module=rpgstuff-reservations_types&amp;action=edit_type&amp;rtid=".$rtid
                );
                $popup->add_item(
                    $lang->reservations_options_popup_types_delete,
                    "index.php?module=rpgstuff-reservations_types&amp;action=delete_type&amp;rtid=".$rtid."&amp;my_post_key={$mybb->post_code}", 
					"return AdminCP.deleteConfirmation(this, '".$lang->reservations_types_delete_type_notice."')"
                );
				$popup->add_item(
                    $lang->reservations_options_popup_grouppermission,
                    "index.php?module=rpgstuff-reservations_types&amp;action=add_grouppermission&amp;rtid=".$rtid
                );
                if ($groupsplit == 1 && $db->num_rows($query_grouppermissions) > 0) {
                    $popup->add_item(
                        $lang->reservations_options_popup_condensedgroups,
                        "index.php?module=rpgstuff-reservations_types&amp;action=add_condensedgroups&amp;rtid=".$rtid
                    );
                }
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();
            }

            // keine Typen bisher
			if($db->num_rows($query_types) == 0){
                $form_container->output_cell($lang->reservations_types_overview_noElements, array("colspan" => 3, 'style' => 'text-align: center;'));
                $form_container->construct_row();
			}

            $form_container->end();

            // keine Typen = kein Button
            if($db->num_rows($query_types) > 0){
                $buttons = array($form->generate_submit_button($lang->reservations_types_overview_disporder_button));
                $form->output_submit_wrapper($buttons);
            }

            $form->end();
            $page->output_footer();
			exit;
        }

        // TYP HINZUFÜGEN
        if ($mybb->get_input('action') == "add_type") {
            
            if ($mybb->request_method == "post") {

                $errors = reservations_validate_types();

                // No errors - insert
                if (empty($errors)) {

                    if (is_numeric($mybb->get_input('checking_field'))) {
                        $checkfield = (int)$mybb->get_input('checking_field');
                    } else {
                        $checkfield = $db->escape_string($mybb->get_input('checking_field'));
                    }

                    $insert_reservations_type = array(
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "gender" => (int)$mybb->get_input('gender'),
                        "checkoption" => (int)$mybb->get_input('checking'),
                        "checkfield" => $checkfield,
                        "checkname" => (int)$mybb->get_input('checking_name'),
                        "groupsplit" => (int)$mybb->get_input('groupsplit')
                    );
                    $db->insert_query("reservations_types", $insert_reservations_type);


                    flash_message($lang->reservations_types_add_type_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }
            }

            $page->add_breadcrumb_item($lang->reservations_types_breadcrumb_add_type);
			$page->output_header($lang->reservations_types_breadcrumb_main." - ".$lang->reservations_types_add_type_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types"
			];
            $sub_tabs['add_type'] = [
				"title" => $lang->reservations_types_tabs_add_type,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=add_type",
				"description" => $lang->reservations_types_tabs_add_type_desc
			];
            $page->output_nav_tabs($sub_tabs, 'add_type');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_types&amp;action=add_type", "post", "", 1);
            $form_container = new FormContainer($lang->reservations_types_add_type_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);

            $form_container->output_row(
                $lang->reservations_types_form_identification,
                $lang->reservations_types_form_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );

            $form_container->output_row(
                $lang->reservations_types_form_title,
                $lang->reservations_types_form_title_desc,
                $form->generate_text_box('title', $mybb->get_input('title'))
            );

            $form_container->output_row(
                $lang->reservations_types_form_description,
                $lang->reservations_types_form_description_desc,
                $form->generate_text_box('description', $mybb->get_input('description'))
            );

            $form_container->output_row(
                $lang->reservations_types_form_disporder,
                $lang->reservations_types_form_disporder_desc,
                $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0)), 'disporder'
            );

            $form_container->output_row(
                $lang->reservations_types_form_gender,
                $lang->reservations_types_form_gender_desc,
                $form->generate_yes_no_radio('gender', $mybb->get_input('gender'))
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking,
                $lang->reservations_types_form_checking_desc,
                $form->generate_select_box('checking', $checkingoption_list, $mybb->get_input('checking'), array('id' => 'checking')),
				'checking'
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking_field,
                $lang->reservations_types_form_checking_field_desc,
                $form->generate_text_box('checking_field', $mybb->get_input('checking_field')), 
                'checking_field',
                array('id' => 'row_checkingfield')
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking_name,
                $lang->reservations_types_form_checking_name_desc,
                $form->generate_select_box('checking_name', $checkingname_list, $mybb->get_input('checking_name'), array('id' => 'checking_name')),
				'checking_name', array('id' => 'row_checkingname')
            );

            $form_container->output_row(
                $lang->reservations_types_form_groupsplit,
                $lang->reservations_types_form_groupsplit_desc,
                $form->generate_yes_no_radio('groupsplit', $mybb->get_input('groupsplit'))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->reservations_types_add_type_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("#checking"), $("#row_checkingfield"), /^1/, false);
                    new Peeker($("#checking"), $("#row_checkingname"), /^2/, false);
                    });
                    </script>';

            $page->output_footer();
            exit;
        }

        // TYP BEARBEITEN
        if ($mybb->get_input('action') == "edit_type") {

            // Get the data
            $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);
            $type_query = $db->simple_select("reservations_types", "*", "rtid = '".$rtid."'");
            $type = $db->fetch_array($type_query);
            
            if ($mybb->request_method == "post") {
                    
                $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);

                $errors = reservations_validate_types($rtid);

                // No errors - insert
                if (empty($errors)) {

                    if (is_numeric($mybb->get_input('checking_field'))) {
                        $checkfield = (int)$mybb->get_input('checking_field');
                    } else {
                        $checkfield = $db->escape_string($mybb->get_input('checking_field'));
                    }

                    $update_reservations_type = array(
                        "title" => $db->escape_string($mybb->get_input('title')),
                        "description" => $db->escape_string($mybb->get_input('description')),
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "gender" => (int)$mybb->get_input('gender'),
                        "checkoption" => (int)$mybb->get_input('checking'),
                        "checkfield" => $checkfield,
                        "checkname" => (int)$mybb->get_input('checking_name'),
                        "groupsplit" => (int)$mybb->get_input('groupsplit')
                    );
                    $db->update_query("reservations_types", $update_reservations_type, "rtid='".$rtid."'");

                    flash_message($lang->reservations_types_edit_type_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }
            }

            $page->add_breadcrumb_item($lang->reservations_types_breadcrumb_edit_type);
			$page->output_header($lang->reservations_types_breadcrumb_main." - ".$lang->reservations_types_edit_type_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types"
			];
            $sub_tabs['edit_type'] = [
				"title" => $lang->reservations_types_tabs_edit_type,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=edit_type",
				"description" => $lang->sprintf($lang->reservations_types_tabs_edit_type_desc, $type['title'])
			];
            $page->output_nav_tabs($sub_tabs, 'edit_type');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$title = $mybb->get_input('title');
				$description = $mybb->get_input('description');
				$disporder = $mybb->get_input('disporder', MyBB::INPUT_INT);
				$gender = $mybb->get_input('gender', MyBB::INPUT_INT);
				$checkoption = $mybb->get_input('checking', MyBB::INPUT_INT);
				$checkfield = $mybb->get_input('checking_field');
				$checkname = $mybb->get_input('checking_name', MyBB::INPUT_INT);
				$groupsplit = $mybb->get_input('groupsplit', MyBB::INPUT_INT);
			} else {
				$title = $type['title'];
				$description = $type['description'];
				$disporder = (int)$type['disporder'];
				$gender = (int)$type['gender'];
				$checkoption = (int)$type['checkoption'];
				$checkfield = $type['checkfield'];
				$checkname = (int)$type['checkname'];
				$groupsplit = (int)$type['groupsplit'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_types&amp;action=edit_type", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->reservations_types_edit_type_container, $type['title']));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("rtid", $rtid);

            $form_container->output_row(
                $lang->reservations_types_form_title,
                $lang->reservations_types_form_title_desc,
                $form->generate_text_box('title', $title)
            );

            $form_container->output_row(
                $lang->reservations_types_form_description,
                $lang->reservations_types_form_description_desc,
                $form->generate_text_box('description', $description)
            );

            $form_container->output_row(
                $lang->reservations_types_form_disporder,
                $lang->reservations_types_form_disporder_desc,
                $form->generate_numeric_field('disporder', $disporder, array('id' => 'disporder', 'min' => 0)), 'disporder'
            );

            $form_container->output_row(
                $lang->reservations_types_form_gender,
                $lang->reservations_types_form_gender_desc,
                $form->generate_yes_no_radio('gender', $gender)
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking,
                $lang->reservations_types_form_checking_desc,
                $form->generate_select_box('checking', $checkingoption_list, $checkoption, array('id' => 'checking')),
				'checking'
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking_field,
                $lang->reservations_types_form_checking_field_desc,
                $form->generate_text_box('checking_field', $checkfield), 
                'checking_field',
                array('id' => 'row_checkingfield')
            );

            $form_container->output_row(
                $lang->reservations_types_form_checking_name,
                $lang->reservations_types_form_checking_name_desc,
                $form->generate_select_box('checking_name', $checkingname_list, $checkname, array('id' => 'checking_name')),
				'checking_name', array('id' => 'row_checkingname')
            );

            $form_container->output_row(
                $lang->reservations_types_form_groupsplit,
                $lang->reservations_types_form_groupsplit_desc,
                $form->generate_yes_no_radio('groupsplit', $groupsplit)
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->reservations_types_add_type_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
                <script type="text/javascript">
                $(function() {
                    new Peeker($("#checking"), $("#row_checkingfield"), /^1/, false);
                    new Peeker($("#checking"), $("#row_checkingname"), /^2/, false);
                    });
                    </script>';

            $page->output_footer();
            exit;
        }

        // TYP LÖSCHEN
        if ($mybb->get_input('action') == "delete_type") {
            
            // Get the data
            $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($rtid)) {
				flash_message($lang->reservations_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			if ($mybb->request_method == "post") {

                // Reservierungen löschen
                $db->delete_query('reservations', "type = '".$rtid."'");
                // Gruppenberechtigungen löschen
                $db->delete_query('reservations_grouppermissions', "rtid = '".$rtid."'");
                // Typ löschen
                $db->delete_query('reservations_types', "rtid = '".$rtid."'");

				flash_message($lang->reservations_types_delete_type_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-reservations_types&amp;action=delete_type&amp;rtid=".$rtid,
					$lang->reservations_types_delete_type_notice
				);
			}
			exit;
        }

        // GRUPPENBERECHTIGUNG HINZUFÜGEN
        if ($mybb->get_input('action') == "add_grouppermission") {

            $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);
            $typename = $db->fetch_field($db->simple_select("reservations_types", "title", "rtid= ".$rtid), "title");
            $groupsplit = $db->fetch_field($db->simple_select("reservations_types", "groupsplit", "rtid= ".$rtid), "groupsplit");
            
            if ($mybb->request_method == "post") {

                $errors = reservations_validate_grouppermission();

                // No errors - insert
                if (empty($errors)) {

                    $selected_groups = implode(",", $mybb->get_input('usergroups', MyBB::INPUT_ARRAY));

                    $insert_reservations_grouppermissions = array(
                        "rtid" => (int)$mybb->get_input('rtid'),
                        "usergroups" => $selected_groups,
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "maxcount" => (int)$mybb->get_input('maxcount'),
                        "duration" => (int)$mybb->get_input('duration'),
                        "extend" => (int)$mybb->get_input('extend'),
                        "extendtime" => (int)$mybb->get_input('extendtime'),
                        "extendcount" => (int)$mybb->get_input('extendcount'),
                        "lockcount" => (int)$mybb->get_input('lockcount'),
                        "locknote" => (int)$mybb->get_input('locknote')
                    );
                    $db->insert_query("reservations_grouppermissions", $insert_reservations_grouppermissions);


                    flash_message($lang->reservations_types_grouppermission_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }
            }

            $page->add_breadcrumb_item($lang->reservations_types_breadcrumb_add_grouppermission);
			$page->output_header($lang->reservations_types_breadcrumb_main." - ".$lang->reservations_types_grouppermission_add_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types"
			];
            $sub_tabs['add_grouppermission'] = [
				"title" => $lang->reservations_types_tabs_add_grouppermission,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=add_grouppermission",
				"description" => $lang->sprintf($lang->reservations_types_tabs_add_grouppermission_desc, $typename)
			];
            $page->output_nav_tabs($sub_tabs, 'add_grouppermission');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
                $extend = $mybb->get_input('extend');
                $locknote = $mybb->get_input('locknote');
			} else {
                $extend = 0;
                $locknote = 0;
            }

            $usergroups_list = reservations_usergroups_list($rtid);

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_types&amp;action=add_grouppermission", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->reservations_types_grouppermission_add_container, $typename));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("rtid", $rtid);

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_usergroups, 
                $lang->reservations_types_grouppermission_form_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $mybb->get_input('usergroups', MyBB::INPUT_ARRAY), array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', 
                array(), 
                array('id' => 'row_usergroups')
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_name,
                $lang->reservations_types_grouppermission_form_name_desc,
                $form->generate_text_box('name', $mybb->get_input('name'))
            );

            if ($groupsplit == 1) {
                $form_container->output_row(
                    $lang->reservations_types_grouppermission_form_disporder,
                    $lang->reservations_types_grouppermission_form_disporder_desc,
                    $form->generate_numeric_field('disporder', $mybb->get_input('disporder'), array('id' => 'disporder', 'min' => 0))
                );
            }

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_maxcount,
                $lang->reservations_types_grouppermission_form_maxcount_desc,
                $form->generate_numeric_field('maxcount', $mybb->get_input('maxcount'), array('id' => 'maxcount', 'min' => 0))
            );
            
            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_locknote,
                $lang->reservations_types_grouppermission_form_locknote_desc,
                $form->generate_yes_no_radio('locknote', $locknote)
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_duration,
                $lang->reservations_types_grouppermission_form_duration_desc,
                $form->generate_numeric_field('duration', $mybb->get_input('duration'), array('id' => 'duration', 'min' => 0))
            );
            
            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extend,
                $lang->reservations_types_grouppermission_form_extend_desc,
                $form->generate_yes_no_radio('extend', $extend),
                'row_extend'
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extendcount,
                $lang->reservations_types_grouppermission_form_extendcount_desc,
                $form->generate_text_box('extendcount', $mybb->get_input('extendcount')),
                null,
                array(),
                array('id' => 'row_extendcount')
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extendtime,
                $lang->reservations_types_grouppermission_form_extendtime_desc,
                $form->generate_text_box('extendtime', $mybb->get_input('extendtime')), 
                null,
                array(),
                array('id' => 'row_extendtime')
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_lockcount,
                $lang->reservations_types_grouppermission_form_lockcount_desc,
                $form->generate_numeric_field('lockcount', $mybb->get_input('lockcount'), array('id' => 'lockcount', 'min' => 0))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->reservations_types_grouppermission_add_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
            <script type="text/javascript">
            $(function() {
                new Peeker($("input[name=\'extend\']"), $("#row_extendcount, #row_extendtime"), "1", true );
            });
            </script>';

            $page->output_footer();
            exit;
        }

        // GRUPPENBERECHTIGUNG BEARBEITEN
        if ($mybb->get_input('action') == "edit_grouppermission") {

            // Get the data
            $rgid = $mybb->get_input('rgid', MyBB::INPUT_INT);
            $grouppermission_query = $db->simple_select("reservations_grouppermissions", "*", "rgid = '".$rgid."'");
            $permission = $db->fetch_array($grouppermission_query);

            $typename = $db->fetch_field($db->simple_select("reservations_types", "title", "rtid= ".$permission['rtid']), "title");
            $groupsplit = $db->fetch_field($db->simple_select("reservations_types", "groupsplit", "rtid= ".$permission['rtid']), "groupsplit");
            
            if ($mybb->request_method == "post") {

                $errors = reservations_validate_grouppermission();

                // No errors - insert
                if (empty($errors)) {

                    $rgid = $mybb->get_input('rgid', MyBB::INPUT_INT);
                    $selected_groups = implode(",", $mybb->get_input('usergroups', MyBB::INPUT_ARRAY));

                    $update_reservations_grouppermissions = array(
                        "usergroups" => $selected_groups,
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "disporder" => (int)$mybb->get_input('disporder'),
                        "maxcount" => (int)$mybb->get_input('maxcount'),
                        "duration" => (int)$mybb->get_input('duration'),
                        "extend" => (int)$mybb->get_input('extend'),
                        "extendtime" => (int)$mybb->get_input('extendtime'),
                        "extendcount" => (int)$mybb->get_input('extendcount'),
                        "lockcount" => (int)$mybb->get_input('lockcount'),
                        "locknote" => (int)$mybb->get_input('locknote')
                    );
                    $db->update_query("reservations_grouppermissions", $update_reservations_grouppermissions, "rgid='".$rgid."'");


                    flash_message($lang->reservations_types_grouppermission_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }
            }

            $page->add_breadcrumb_item($lang->reservations_types_breadcrumb_edit_grouppermission);
			$page->output_header($lang->reservations_types_breadcrumb_main." - ".$lang->reservations_types_grouppermission_edit_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types"
			];
            $sub_tabs['edit_grouppermission'] = [
				"title" => $lang->reservations_types_tabs_edit_grouppermission,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=edit_grouppermission",
				"description" => $lang->sprintf($lang->reservations_types_tabs_edit_grouppermission_desc, $permission['name'], $typename)
			];
            $page->output_nav_tabs($sub_tabs, 'edit_grouppermission');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
                $selected_groups = $mybb->get_input('usergroups', MyBB::INPUT_ARRAY);
                $name = $mybb->get_input('name');
                $disporder = (int)$mybb->get_input('disporder');
                $maxcount = (int)$mybb->get_input('maxcount');
                $duration = (int)$mybb->get_input('duration');
                $extend = (int)$mybb->get_input('extend');
                $extendcount = (int)$mybb->get_input('extendcount');
                $extendtime = (int)$mybb->get_input('extendtime');
                $lockcount = (int)$mybb->get_input('lockcount');
                $locknote = (int)$mybb->get_input('locknote');
			} else {
                $selected_groups = explode(",", $permission['usergroups']);
                $name = $permission['name'];
                $disporder = (int)$permission['disporder'];
                $maxcount = (int)$permission['maxcount'];
                $duration = (int)$permission['duration'];
                $extend = (int)$permission['extend'];
                $extendcount = (int)$permission['extendcount'];
                $extendtime = (int)$permission['extendtime'];
                $lockcount = (int)$permission['lockcount'];
                $locknote = (int)$permission['locknote'];
            }

            $usergroups_list = reservations_usergroups_list($permission['rtid'], $rgid);

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_types&amp;action=edit_grouppermission", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->reservations_types_grouppermission_edit_container, $permission['name'], $typename));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("rgid", $rgid);

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_usergroups, 
                $lang->reservations_types_grouppermission_form_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $selected_groups, array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', 
                array(), 
                array('id' => 'row_usergroups')
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_name,
                $lang->reservations_types_grouppermission_form_name_desc,
                $form->generate_text_box('name', $name)
            );

            if ($groupsplit == 1) {
                $form_container->output_row(
                    $lang->reservations_types_grouppermission_form_disporder,
                    $lang->reservations_types_grouppermission_form_disporder_desc,
                    $form->generate_numeric_field('disporder', $disporder, array('id' => 'disporder', 'min' => 0))
                );
            }

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_maxcount,
                $lang->reservations_types_grouppermission_form_maxcount_desc,
                $form->generate_numeric_field('maxcount', $maxcount, array('id' => 'maxcount', 'min' => 0))
            );
            
            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_locknote,
                $lang->reservations_types_grouppermission_form_locknote_desc,
                $form->generate_yes_no_radio('locknote', $locknote)
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_duration,
                $lang->reservations_types_grouppermission_form_duration_desc,
                $form->generate_numeric_field('duration', $duration, array('id' => 'duration', 'min' => 0))
            );
            
            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extend,
                $lang->reservations_types_grouppermission_form_extend_desc,
                $form->generate_yes_no_radio('extend', $extend),
                'row_extend'
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extendcount,
                $lang->reservations_types_grouppermission_form_extendcount_desc,
                $form->generate_text_box('extendcount', $extendcount),
                null,
                array(),
                array('id' => 'row_extendcount')
            );

            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_extendtime,
                $lang->reservations_types_grouppermission_form_extendtime_desc,
                $form->generate_text_box('extendtime', $extendtime), 
                null,
                array(),
                array('id' => 'row_extendtime')
            );


            $form_container->output_row(
                $lang->reservations_types_grouppermission_form_lockcount,
                $lang->reservations_types_grouppermission_form_lockcount_desc,
                $form->generate_numeric_field('lockcount', $lockcount, array('id' => 'lockcount', 'min' => 0))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->reservations_types_grouppermission_edit_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
            <script type="text/javascript">
            $(function() {
                new Peeker($("input[name=\'extend\']"), $("#row_extendcount, #row_extendtime"), "1", true );
            });
            </script>';

            $page->output_footer();
            exit;
        }

        // GRUPPENBERECHTIGUNG LÖSCHEN
        if ($mybb->get_input('action') == "delete_grouppermission") {
            
            // Get the data
            $rgid = $mybb->get_input('rgid', MyBB::INPUT_INT);

			// Error Handling
			if (empty($rgid)) {
				flash_message($lang->reservations_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			if ($mybb->request_method == "post") {

                // Gruppenberechtigungen löschen
                $db->delete_query('reservations_grouppermissions', "rgid = '".$rgid."'");

				flash_message($lang->reservations_types_grouppermission_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-reservations_types&amp;action=delete_grouppermission&amp;rgid=".$rgid,
					$lang->reservations_types_grouppermission_delete_notice
				);
			}
			exit;
        }

        // GRUPPE ZUSAMMENFASSEN
        if ($mybb->get_input('action') == "add_condensedgroups") {

            $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);
            $type_query = $db->simple_select("reservations_types", "title, condensedgroups", "rtid = '".$rtid."'");
            $type = $db->fetch_array($type_query);
            $typename = $type['title'];
            
            if ($mybb->request_method == "post") {

                $selected_rgids = $mybb->get_input('rgid', MyBB::INPUT_ARRAY);
                if (count($selected_rgids) <= 1) {
                    $errors = $lang->reservations_types_condensedgroups_form_error;
                }

                // No errors - insert
                if (empty($errors)) {

                    $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);
                    $condensedgroups = $db->fetch_field($db->simple_select("reservations_types", "condensedgroups", "rtid= ".$rtid), "condensedgroups");
                    $selected_rgids = implode(",", $mybb->get_input('rgid', MyBB::INPUT_ARRAY));

                    if (!empty($condensedgroups)) {
                        $thing = $condensedgroups."\n".$selected_rgids;
                    } else {
                        $thing = $selected_rgids;
                    }

                    $update_reservations_condensedgroups = array(
                        "condensedgroups" => $thing,
                    );
                    $db->update_query("reservations_types", $update_reservations_condensedgroups, "rtid='".$rtid."'");

                    flash_message($lang->reservations_types_grouppermission_add_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_types");
                }
            }

            $page->add_breadcrumb_item($lang->reservations_types_breadcrumb_add_condensedgroups);
			$page->output_header($lang->reservations_types_breadcrumb_main." - ".$lang->reservations_types_condensedgroups_add_header);

			// Menü
			$sub_tabs['overview'] = [
				"title" => $lang->reservations_types_tabs_overview,
				"link" => "index.php?module=rpgstuff-reservations_types"
			];
            $sub_tabs['add_condensedgroups'] = [
				"title" => $lang->reservations_types_tabs_add_condensedgroups,
				"link" => "index.php?module=rpgstuff-reservations_types&amp;action=add_condensedgroups",
				"description" => $lang->sprintf($lang->reservations_types_tabs_add_condensedgroups_desc, $typename)
			];
            $page->output_nav_tabs($sub_tabs, 'add_condensedgroups');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_types&amp;action=add_condensedgroups", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->reservations_types_condensedgroups_add_container, $typename));
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("rtid", $rtid);

            $query_grouppermissions = reservations_condensedgroups_query($rtid);

            if($db->num_rows($query_grouppermissions) > 0){
                $condensedgroups_options = [];
                while ($permission = $db->fetch_array($query_grouppermissions)) {
                    $condensedgroups_options[] = $form->generate_check_box('rgid[]', $permission['rgid'], $permission['name'], array('checked' => in_array($permission['rgid'], (array)$mybb->get_input('rgid', MyBB::INPUT_ARRAY)), 'id' => 'rgid'.$permission['rgid']));            
                }

                $form_container->output_row(
                    $lang->reservations_types_condensedgroups_form, 
                    $lang->reservations_types_condensedgroups_form_desc, 
                    implode('<br />', $condensedgroups_options)
                );

                $form_container->end();
                $buttons[] = $form->generate_submit_button($lang->reservations_types_condensedgroups_add_button);
                $form->output_submit_wrapper($buttons);

            } else {
                $form_container->output_row($lang->reservations_types_condensedgroups_add_none);
                $form_container->end();
            }
            
            $form->end();

            $page->output_footer();
            exit;
        }

        // GRUPPENZUSAMMENFASSUNG LÖSCHEN
        if ($mybb->get_input('action') == "delete_condensedgroups") {
            
            // Get the data
            $rtid = $mybb->get_input('rtid', MyBB::INPUT_INT);
            $condensedID = trim($mybb->get_input('condensedID'));

			// Error Handling
			if (empty($rtid) || empty($condensedID)) {
				flash_message($lang->reservations_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			}

			if ($mybb->request_method == "post") {

                $existing_condensed = $db->fetch_field($db->simple_select("reservations_types", "condensedgroups", "rtid = '".$rtid."'"),"condensedgroups");

                if ($existing_condensed) {
                    $condensed = array_filter(array_map('trim', explode("\n", $existing_condensed)));
                    
                    $new_condensed = [];
                    foreach ($condensed as $entry) {

                        if ($entry !== $condensedID) {
                            $new_condensed[] = $entry;
                        }
                    }

                    $new_value = implode("\n", $new_condensed);

                    $update_condensedgroups = array(
                        "condensedgroups" => $db->escape_string($new_value)
                    );
                    $db->update_query("reservations_types", $update_condensedgroups, "rtid='".$rtid."'");
                }

				flash_message($lang->reservations_types_condensedgroups_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-reservations_types");
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-reservations_types&amp;action=delete_condensedgroups&amp;rtid=".$rtid."&amp;condensedID=".$condensedID,
					$lang->reservations_types_condensedgroups_delete_notice
				);
			}
			exit;
        }
    }

    // RESERVIERUNGEN
	if ($run_module == 'rpgstuff' && $action_file == 'reservations_data') {

		// Add to page navigation
		$page->add_breadcrumb_item($lang->reservations_data_breadcrumb_main);

        // einzelne Typen
        $query_types = $db->query("SELECT rtid, identification, title, gender FROM ".TABLE_PREFIX."reservations_types
        ORDER BY disporder ASC, title ASC        
        ");

        while ($typ = $db->fetch_array($query_types)) {

            // leer laufen lassen
            $rtid = "";
            $identification = "";
            $title = "";
            $genderOption = "";

            // mit Infos füllen
            $rtid = $typ['rtid'];
            $identification = $typ['identification'];
            $title = $typ['title'];
            $genderOption = $typ['gender'];

            if ($mybb->get_input('action') == $identification) {

                $page->add_breadcrumb_item($title);
                $page->output_header($lang->reservations_data_header." - ".$title);
    
                // Menü
                reservations_types_nav_tabs($identification);
                
                $form_container = new FormContainer($lang->sprintf($lang->reservations_data_container_active, $title));
                $form_container->output_row_header($lang->reservations_data_container_reservation, array('style' => 'text-align: left;'));
                // Geschlechtstrennung
                if ($genderOption == 1) {
                    $form_container->output_row_header($lang->reservations_data_container_gender, array('style' => 'text-align: left;width: 15%;'));
                }
                $form_container->output_row_header('User:in', array('style' => 'text-align: center; width: 15%;'));
                $form_container->output_row_header('Optionen', array('style' => 'text-align: center; width: 10%;'));
                
                $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations 
                WHERE type = ".$rtid."
                AND lockcheck = 0
                ORDER BY reservation ASC");
                    
                while ($res = $db->fetch_array($query_reservations)) {

                    // Leer laufen lassen
                    $rid = "";
                    $uid = "";
                    $playername = "";                                  
                    $reservation = "";                
                    $gender = "";
    
                    // Mit Infos füllen
                    $rid = $res['rid'];
                    $uid = $res['uid'];                          
                    $reservation = $res['reservation'];
                    $gender = $res['gender'];
                    
                    if ($uid == 0) {
                        $playername = $res['playername'];
                        $member = $playername.$lang->reservations_guest;
                    } else {
                        if (!empty(get_user($uid))) {
                            $playername = reservations_playername($uid);
                            $member = build_profile_link($playername, $uid);
                        } else {
                            $playername = $res['playername'];
                            $member = $playername.$lang->reservations_old; 
                        }   
                    }

                    $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-reservations_data&amp;action=edit_data&amp;rid='.$rid.'">'.$reservation.'</a></strong>');   
                    if ($genderOption == 1) { 
                        $form_container->output_cell($gender);
                    }
                    $form_container->output_cell($member);

                    $popup = new PopupMenu("reservationsdata_".$rid, $lang->reservations_options_popup);	
                    $popup->add_item(
                        $lang->reservations_options_popup_data_edit,        
                        "index.php?module=rpgstuff-reservations_data&amp;action=edit_data&amp;rid=".$rid
                    );
                    if (empty(get_user($uid))) {
                      $popup->add_item(
                          $lang->reservations_options_popup_data_delete,
                          "index.php?module=rpgstuff-reservations_data&amp;action=delete_data&amp;rid=".$rid."&amp;my_post_key={$mybb->post_code}", 
                          "return AdminCP.deleteConfirmation(this, '".$lang->reservations_types_delete_data_notice."')"
                        );
                    }
                    $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                            
                    $form_container->construct_row();    
                }
           
                $form_container->end();
                $page->output_footer();
                exit;        
            }
        }
        
        // Gesperrte
        if ($mybb->get_input('action') == 'blocked') {

            $page->add_breadcrumb_item($lang->reservations_data_tab_blocked);
            $page->output_header($lang->reservations_data_header_blocked);
    
            // Menü
            reservations_types_nav_tabs('blocked');
                
            $form_container = new FormContainer($lang->reservations_data_container_blocked);
            $form_container->output_row_header($lang->reservations_data_container_reservation, array('style' => 'text-align: left;'));
            $form_container->output_row_header('User:in', array('style' => 'text-align: center; width: 15%;'));
            $form_container->output_row_header('Optionen', array('style' => 'text-align: center; width: 10%;'));
                
            $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations 
            WHERE lockcheck = 1
            ORDER BY reservation ASC");

            while ($res = $db->fetch_array($query_reservations)) {

                // Leer laufen lassen
                $rid = "";
                $uid = "";
                $playername = "";                                  
                $reservation = "";                    
                $gender = "";
    
                // Mit Infos füllen
                $rid = $res['rid'];
                $uid = $res['uid'];                          
                $reservation = $res['reservation'];
                $gender = $res['gender'];    
        
                if ($uid == 0) {
                    $playername = $res['playername'];
                    $member = $playername.$lang->reservations_guest;
                } else {
                    if (!empty(get_user($uid))) {
                        $playername = reservations_playername($uid);
                        $member = build_profile_link($playername, $uid);        
                    } else {
                        $playername = $res['playername'];
                        $member = $playername.$lang->reservations_old;        
                    }    
                }

                $form_container->output_cell('<strong><a href="index.php?module=rpgstuff-reservations_data&amp;action=edit_data&amp;rid='.$rid.'">'.$reservation.'</a></strong>');   
                $form_container->output_cell($member);

                $popup = new PopupMenu("reservationsdata_".$rid, $lang->reservations_options_popup);	
                $popup->add_item(
                    $lang->reservations_options_popup_data_edit,        
                    "index.php?module=rpgstuff-reservations_data&amp;action=edit_data&amp;rid=".$rid
                );
                if (empty(get_user($uid))) {
                    $popup->add_item(
                        $lang->reservations_options_popup_data_delete,
                        "index.php?module=rpgstuff-reservations_data&amp;action=delete_data&amp;rid=".$rid."&amp;my_post_key={$mybb->post_code}", 
                        "return AdminCP.deleteConfirmation(this, '".$lang->reservations_types_delete_data_notice."')"
                    );
                }
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                        
                $form_container->construct_row();    
            }
                
            $form_container->end();
            $page->output_footer();    
            exit;        
        }

        // Bearbeiten
        if ($mybb->get_input('action') == 'edit_data') {

            $type_select = array();
            $get_all_types = $db->query("SELECT rtid, title FROM ".TABLE_PREFIX."reservations_types
            ORDER BY disporder ASC, title ASC        
            ");

            $type_select[''] = 'Kategorie auswählen';
            while ($types = $db->fetch_array($get_all_types)) {
                $rtid = $types['rtid'];
                $type_select[$rtid] = $types['title'];
            }

            $genderOptions = $mybb->settings['reservations_gender'];
            $genderArray = array_map('trim', explode(',', $genderOptions));
            $gender_select = array();
            $gender_select[''] = 'Geschlecht auswählen';
            foreach ($genderArray as $genderOption) {
                $gender_select[$genderOption] = $genderOption;
            }

            // Get the data
            $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
            $reservation_query = $db->simple_select("reservations", "*", "rid = '".$rid."'");
            $res = $db->fetch_array($reservation_query);

            $type_query = $db->simple_select("reservations_types", "identification, gender, title", "rtid = '".$res['type']."'");
            $typeOptions = $db->fetch_array($type_query);

            $page->add_breadcrumb_item($typeOptions['title'], "index.php?module=rpgstuff-reservations_data&amp;action=".$typeOptions['identification']);
            $page->add_breadcrumb_item($lang->reservations_data_breadcrumb_edit);
            $page->output_header($lang->reservations_data_header." - ".$lang->reservations_data_header_edit);
    
            // Menü
            $sub_tabs['edit_data'] = [
                "title" => $lang->reservations_data_tab_edit,
                "link" => "index.php?module=rpgstuff-reservations_data&amp;action=edit_data",
                "description" => $lang->reservations_data_tab_edit_desc    
            ];
            $page->output_nav_tabs($sub_tabs, 'edit_data');
            
            if ($mybb->request_method == "post") {
                    
                $rid = $mybb->get_input('rid', MyBB::INPUT_INT);

                $errors = reservations_validate_data($rid);

                // No errors - update
                if (empty($errors)) {

                    $update_reservations = array(
                        "type" => (int)$mybb->get_input('type'),
                        "reservation" => $db->escape_string($mybb->get_input('reservation')),
                        "gender" => $db->escape_string($mybb->get_input('gender')),
                        "wantedUrl" => $db->escape_string($mybb->get_input('wantedUrl'))
                    );

                    if (!empty($mybb->get_input('newUID'))) {

                        $uid = $mybb->get_input('newUID');
                        $playername = reservations_playername($uid);

                        $update_reservations['uid'] = (int)$uid;
                        $update_reservations['playername'] = $db->escape_string($playername);
                    }

                    $db->update_query("reservations", $update_reservations, "rid='".$rid."'");

                    $identification = $db->fetch_field($db->simple_select("reservations_types", "identification", "rtid = ".$mybb->get_input('type')),"identification");
                    flash_message($lang->reservations_data_edit_flash, 'success');
                    admin_redirect("index.php?module=rpgstuff-reservations_data&amp;action=".$identification);
                }
            }

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
				$type = $mybb->get_input('type', MyBB::INPUT_INT);
				$reservation = $mybb->get_input('reservation');
				$gender = $mybb->get_input('gender');
				$wantedUrl = $mybb->get_input('wantedUrl');
				$uid = $mybb->get_input('newUID');
			} else {
				$type = (int)$res['type'];
				$reservation = $res['reservation'];
				$gender = $res['gender'];
				$wantedUrl = $res['wantedUrl'];
				$uid = $res['uid'];
            }

            // Build the form
            $form = new Form("index.php?module=rpgstuff-reservations_data&amp;action=edit_data", "post", "", 1);
            $form_container = new FormContainer($lang->reservations_data_edit_container);
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
            echo $form->generate_hidden_field("rid", $rid);

            $form_container->output_row(
                $lang->reservations_data_form_type,
                $lang->reservations_data_form_type_desc,
                $form->generate_select_box('type', $type_select, $type, array('id' => 'type')),
				'type'
            );

            $form_container->output_row(
                $lang->reservations_data_form_reservation,
                $lang->reservations_data_form_reservation_desc,
                $form->generate_text_box('reservation', $reservation)
            );

            if ($typeOptions['gender'] == 1) {
                $form_container->output_row(
                    $lang->reservations_data_form_gender,
                    $lang->reservations_data_form_gender_desc,
                    $form->generate_select_box('gender', $gender_select, $gender, array('id' => 'gender')),
                    'gender'    
                );
            }

            if ($typeOptions['identification'] == $mybb->settings['reservations_searchtyp']) {
                $form_container->output_row(
                    $lang->reservations_data_form_wanted,
                    $lang->reservations_data_form_wanted_desc,
                    $form->generate_text_box('wantedUrl', $wantedUrl)
                );
            }

            if (empty(get_user($uid))) {
                $form_container->output_row(
                    $lang->reservations_data_form_user,
                    $lang->reservations_data_form_user_desc,
                    $form->generate_numeric_field('newUID', $uid)
                );
            }

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->reservations_data_edit_button);
            $form->output_submit_wrapper($buttons);
            $form->end();

            $page->output_footer();
            exit;
        }

        // löschen
        if ($mybb->get_input('action') == "delete_data") {
            
            // Get the data
            $rid = $mybb->get_input('rid', MyBB::INPUT_INT);
            $type = $db->fetch_field($db->simple_select("reservations", "type", "rid = ".$rid),"type");       
            $identification = $db->fetch_field($db->simple_select("reservations_types", "identification", "rtid = ".$type),"identification");

			// Error Handling
			if (empty($rid)) {
				flash_message($lang->reservations_error_invalid, 'error');
				admin_redirect("index.php?module=rpgstuff-reservations_data&amp;action=".$identification);
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=rpgstuff-reservations_data&amp;action=".$identification);
			}

			if ($mybb->request_method == "post") {

                $db->delete_query('reservations', "rid = ".$rid);

				flash_message($lang->reservations_data_delete_flash, 'success');
				admin_redirect("index.php?module=rpgstuff-reservations_data&amp;action=".$identification);
			} else {
				$page->output_confirm_action(
					"index.php?module=rpgstuff-reservations_data&amp;action=delete_data&amp;rid=".$rid,
					$lang->reservations_types_delete_data_notice
				);
			}
			exit;
        }
    }
}

// Stylesheet zum Master Style hinzufügen
function reservations_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "reservations") {

        $css = reservations_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "reservations.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Reservierungs-Manager")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'reservations.css' 
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
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=reservations\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function reservations_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "reservations") {

        // Einstellungen überprüfen => Type = update
        reservations_settings('update');
        rebuild_settings();

        // Templates 
        reservations_templates('update');

        // Stylesheet
        $update_data = reservations_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'reservations.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('reservations.css')."' AND tid = ".$theme['tid']);
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

        // Datenbanktabellen & Felder
        reservations_database();

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Reservierungs-Manager")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = reservations_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=reservations\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// FORUM //

// EIGENE SEITE
function reservations_misc() {

    global $db, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $lists_menu, $page, $reservations_output, $infotext, $reservations_form, $reservations_blocked;

    // return if the action key isn't part of the input
    $allowed_actions = [
        'reservations',
        'do_reservations',
        'reservationsBlocked_delete',
        'reservations_delete',
        'reservations_extend'
    ];
    if (!in_array($mybb->get_input('action', MyBB::INPUT_STRING), $allowed_actions)) return;

    $lang->load("reservations");

    // gesperrte löschen
    if ($mybb->get_input('action') == "reservationsBlocked_delete") {
        $rid = $mybb->get_input('rid');
        $db->delete_query('reservations', "rid = '".$rid."'");

        if ($mybb->get_input('return') == 'showthread') {
            $tid = $mybb->settings['reservations_thread'];
            redirect("showthread.php?tid=".$tid, $lang->reservations_redirect_blocked_delete);
        } else if ($mybb->get_input('return') == 'misc') {
            redirect("misc.php?action=reservations", $lang->reservations_redirect_blocked_delete);
        } else if ($mybb->get_input('return') == 'modcp') {
            redirect("modcp.php?action=reservations", $lang->reservations_redirect_blocked_delete);
        }
    }

    // Reservierungen löschen
    if ($mybb->get_input('action') == "reservations_delete") {
        $rid = $mybb->get_input('rid');
        $del = $db->fetch_array($db->simple_select('reservations', '*', 'rid = '.$rid));

        // User => Sperrung überprüfen
        if ($del['uid'] != 0 && !empty(get_user($del['uid']))) {
            $uid = $del['uid'];
            $rtid = $del['type'];

            $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
            // primär [usergroup]            
            if ($grouppermissionSetting == 0) {
                $usergroup = get_user($uid)['usergroup'];

                $lockDays = $db->fetch_field($db->query("SELECT lockcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "lockcount");
            } 
            // sekundär [additionalgroups]    
            else {
                $additionalgroups = get_user($uid)['additionalgroups'];

                if (!empty($additionalgroups)) {
                    $additionalgroups = explode(",", $additionalgroups);
                    foreach ($additionalgroups as $additionalgroup) {
                        $lockDays = $db->fetch_field($db->query("SELECT lockcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$rtid."
                        AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')
                        "), "lockcount");
                            
                        if (!empty($lockDays)) {
                            break;
                        }
                    }
                        
                    if (empty($lockDays)) {
                        $usergroup = get_user($uid)['usergroup'];
                        $lockDays = $db->fetch_field($db->query("SELECT lockcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$rtid."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')                
                        "), "lockcount");
                    }    
                } else {          
                    $usergroup = get_user($uid)['usergroup'];

                    $lockDays = $db->fetch_field($db->query("SELECT lockcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$rtid."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')        
                    "), "lockcount");   
                }
            }

            // keine Sperre => einfach löschen
            if ($lockDays == 0) {
                $db->delete_query('reservations', "rid = '".$rid."'");
            } else {
                $deadline = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
                $deadline->setTime(0, 0, 0);
                $deadline->modify("+{$lockDays} days");

                $blocked_reservations = array(
                    "enddate" => NULL,
                    "lockcheck" => (int)1, 
                    "lockdate" => $db->escape_string($deadline->format("Y-m-d"))
                );
                $db->update_query("reservations", $blocked_reservations, "rid = ".$rid);
            }
        }
        // Gast => einfach löschen
        else {
            $db->delete_query('reservations', "rid = '".$rid."'");
        }

        if ($mybb->get_input('return') == 'showthread') {
            $tid = $mybb->settings['reservations_thread'];
            redirect("showthread.php?tid=".$tid, $lang->reservations_redirect_delete);
        } else {
            redirect("misc.php?action=reservations", $lang->reservations_redirect_delete);
        }
    }

    // Rerservierungen verlängern
    if ($mybb->get_input('action') == "reservations_extend") {
        $rid = $mybb->get_input('rid');
        $ext = $db->fetch_array($db->simple_select('reservations', '*', 'rid = '.$rid));

        if ($ext['uid'] != 0 && !empty(get_user($ex['uid']))) {
            $uid = $ext['uid'];
            $rtid = $ext['type'];

            $extendOptions = reservations_extendOptions($uid, $rtid);

            // Verlängerungslimit noch nicht erreicht
            if ($ext['extension'] < $extendOptions['extendLimit']) {

                $newDate = new DateTime($ext['enddate'], new DateTimeZone('Europe/Berlin'));
                $newDate->setTime(0, 0, 0);
                $newDate->modify("+{$extendOptions['extendDays']} days");

                $extend_reservations = array(
                    "extension" => $ext['extension']+1, 
                    "enddate" => $db->escape_string($newDate->format("Y-m-d"))
                );
                $db->update_query("reservations", $extend_reservations, "rid = ".$rid);
            }
        }

        if ($mybb->get_input('return') == 'showthread') {
            $tid = $mybb->settings['reservations_thread'];
            redirect("showthread.php?tid=".$tid, $lang->reservations_redirect_extend);
        } else {
            redirect("misc.php?action=reservations", $lang->reservations_redirect_extend);
        }
    }

    // Misc Seite
    if ($mybb->settings['reservations_system'] == 2) return;

    // Reservierung eintrag
    if ($mybb->get_input('action') == "do_reservations") {

        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        $errors = reservations_validate_entry();
        
        if (empty($errors)) {

            $rtid = $mybb->get_input('type');
            $uid = $mybb->get_input('uid');

            $deadline = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
            $deadline->setTime(0, 0, 0);

            // User
            if ($uid != 0) {
                $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
                // primär [usergroup]
                if ($grouppermissionSetting == 0) {
                    $usergroup = get_user($uid)['usergroup'];

                    $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$rtid."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                    "), "duration");
                } 
                // sekundär [additionalgroups]
                else {
                    $additionalgroups = get_user($uid)['additionalgroups'];

                    if (!empty($additionalgroups)) {

                        $additionalgroups = explode(",", $additionalgroups);
                        foreach ($additionalgroups as $additionalgroup) {
                            $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$rtid."
                            AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')
                            "), "duration");
                            
                            if (!empty($durationDays)) {
                                break;
                            }
                        }
                        
                        if (empty($durationDays)) {
                            $usergroup = get_user($uid)['usergroup'];

                            $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$rtid."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                            "), "duration");
                        }    
                    } else {          
                        $usergroup = get_user($uid)['usergroup'];

                        $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$rtid."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                        "), "duration");   
                    }
                }

                $playername = reservations_playername($uid);
            } 
            // Gast
            else {
                $usergroup = 1;
                $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "duration");   

                $playername = $mybb->get_input('playername');
            }

            // Endlos
            if ($durationDays == 0) {
                $reservation_deadline = $db->escape_string($deadline->format("Y-m-d"));
                $endlessNote = 1;
            } else {
                $deadline->modify("+{$durationDays} days");
                $reservation_deadline = $db->escape_string($deadline->format("Y-m-d"));
                $endlessNote = 0;
            }

            $insert_reservations = array(
                "uid" => (int)$uid,
                "playername" => $db->escape_string($playername),
                "type" => (int)$rtid,
                "gender" => $db->escape_string($mybb->get_input('gender')),
                "reservation" => $db->escape_string($mybb->get_input('reservation')),
                "enddate" => $reservation_deadline,
                "endlessNote" => (int)$endlessNote,
                "wantedUrl" => $db->escape_string($mybb->get_input('wantedUrl'))
            );
            $db->insert_query("reservations", $insert_reservations);

            redirect("misc.php?action=reservations", $lang->reservations_redirect_add);
        } else {
            $reservations_error = inline_error($errors);
            $mybb->input['action'] = "reservations";
        }
    }

    $listsnav = $mybb->settings['reservations_lists_nav'];
    $listsmenu = $mybb->settings['reservations_lists_menu'];
    $listsmenu_tpl = $mybb->settings['reservations_lists_menu_tpl'];

    // Seite
    if ($mybb->get_input('action') == "reservations") {

		// Listenmenü
		if($listsmenu != 2){
            // Jules Plugin
            if ($listsmenu == 1) {
                $lang->load("lists");
                $query_lists = $db->simple_select("lists", "*");
                $menu_bit = "";
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_tpl)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav)){
            add_breadcrumb("Listen", $listsnav);
            add_breadcrumb($lang->reservations, "misc.php?action=reservations");
		} else{
            add_breadcrumb($lang->reservations, "misc.php?action=reservations");
		}
   
        $infotext = reservations_infotext();
        
        if(!isset($reservations_error)) {
            $reservations_error = "";  
            $type = "";  
            $reservation = ""; 
            $gender = ""; 
            $playername = ""; 
            $wantedUrl = ""; 
        } else {
            $type = $mybb->get_input('type');
            $reservation = $mybb->get_input('reservation');
            $gender = $mybb->get_input('gender');
            $playername = $mybb->get_input('playername');
            $wantedUrl = $mybb->get_input('wantedUrl'); 
        }
        
        // Formular
        $reservations_form = reservations_output_formular_misc($type, $reservation, $gender, $playername, $wantedUrl);

        // ohne Tabs
        if ($mybb->settings['reservations_tab'] == 0) {
            $reservations_output = reservations_output_page('misc');
        }
        // mit Tabs
        else {
            $reservations_output = reservations_output_page_tabs('misc');
        }

        $reservations_blocked = reservations_blocked('misc');

        eval("\$page = \"".$templates->get("reservations_page")."\";");
		output_page($page);
		die();
    }
}

// SHOWTHREAD
// Formular
function reservations_showthread_form() {
    
    global $mybb, $db, $lang, $reservations_error;

    if ($mybb->settings['reservations_system'] == 1) return;

    $lang->load("reservations");

    if ($mybb->request_method == 'post' && isset($mybb->input['reservations_submit'])) {

        verify_post_check($mybb->get_input('my_post_key'));

        $errors = reservations_validate_entry();

        if (empty($errors)) {

            $rtid = $mybb->get_input('type');

            $playerUID = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username = '".$db->escape_string($mybb->get_input('playername'))."'"), "uid");
            if (!empty($playerUID)) {
                $uid = $playerUID;
                $playername = reservations_playername($uid);
            } else {
                $uid = 0;
                $playername = $mybb->get_input('playername');
            }

            $deadline = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
            $deadline->setTime(0, 0, 0);

            // User
            if ($uid != 0) {

                $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
                // primär [usergroup]
                if ($grouppermissionSetting == 0) {
                    $usergroup = get_user($uid)['usergroup'];

                    $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$rtid."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                    "), "duration");
                } 
                // sekundär [additionalgroups]
                else {
                    $additionalgroups = get_user($uid)['additionalgroups'];

                    if (!empty($additionalgroups)) {

                        $additionalgroups = explode(",", $additionalgroups);
                        foreach ($additionalgroups as $additionalgroup) {
                            $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$rtid."
                            AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')
                            "), "duration");
                            
                            if (!empty($durationDays)) {
                                break;
                            }
                        }
                        
                        if (empty($durationDays)) {
                            $usergroup = get_user($uid)['usergroup'];

                            $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$rtid."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                            "), "duration");
                        }    
                    } else {          
                        $usergroup = get_user($uid)['usergroup'];

                        $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$rtid."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                        "), "duration");   
                    }
                }
            } 
            // Gast
            else {
                $usergroup = 1;
                $durationDays = $db->fetch_field($db->query("SELECT duration FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "duration");   
            }

            // Endlos
            if ($durationDays == 0) {
                $reservation_deadline = $db->escape_string($deadline->format("Y-m-d"));
                $endlessNote = 1;
            } else {
                $deadline->modify("+{$durationDays} days");
                $reservation_deadline = $db->escape_string($deadline->format("Y-m-d"));
                $endlessNote = 0;
            }

            $insert_reservations = array(
                "uid" => (int)$uid,
                "playername" => $db->escape_string($playername),
                "type" => (int)$rtid,
                "gender" => $db->escape_string($mybb->get_input('gender')),
                "reservation" => $db->escape_string($mybb->get_input('reservation')),
                "enddate" => $reservation_deadline,
                "endlessNote" => (int)$endlessNote,
                "wantedUrl" => $db->escape_string($mybb->get_input('wantedUrl'))
            );
            $db->insert_query("reservations", $insert_reservations);

            redirect("showthread.php?tid=".$mybb->get_input('tid'), $lang->reservations_redirect_add);
        } else {
            $reservations_error = inline_error($errors);
        }
    }
}

// Ausgabe
function reservations_showthread_output() {

	global $mybb, $theme, $templates, $thread, $lang, $reservations_error, $reservations_form, $reservations_showthread, $infotext, $reservations_blocked;

    if ($mybb->settings['reservations_system'] == 1 || $mybb->settings['reservations_thread'] != $thread['tid']) { 
        $reservations_showthread = "";
        return;
    }

    $lang->load("reservations");

    $infotext = reservations_infotext();

    if(!isset($reservations_error)) {
        $reservations_error = "";  
        $type = "";  
        $reservation = ""; 
        $gender = ""; 
        $playername = ""; 
        $wantedUrl = "";
    } else {
        $type = $mybb->get_input('type');
        $reservation = $mybb->get_input('reservation');
        $gender = $mybb->get_input('gender');    
        $playername = $mybb->get_input('playername');
        $wantedUrl = $mybb->get_input('wantedUrl');
    }

    // Thread-ID
    $tid = $thread['tid'];

    $reservations_form = reservations_output_formular_showthread($type, $reservation, $gender, $playername, $wantedUrl, $tid);

    // ohne Tabs
    if ($mybb->settings['reservations_tab'] == 0) {
        $reservations_output = reservations_output_page('showthread');
    }
    // mit Tabs 
    else {
        $reservations_output = reservations_output_page_tabs('showthread');
    }

    $reservations_blocked = reservations_blocked('showthread');

    eval("\$reservations_showthread = \"".$templates->get('reservations_showthread')."\";");
}

// Index Anzeige & eigene & Teamhinweis (gelöschte Accounts)
function reservations_global() {

    global $db, $lang, $mybb, $templates, $reservations_index, $reservations_team;

    $activeUID = $mybb->user['uid'];

    if ($activeUID == 0) {
        $reservations_index = "";
        $mybb->user['ownreservations'] = "";
        return;
    }

    // Globale eigen Reservierung
    $mybb->user['ownreservations'] = "";
    if ($mybb->settings['reservations_global'] == 1) {
        $mybb->user['ownreservations'] = reservations_ownreservations($activeUID);
    }

    // Hinweis gelöschte Accounts
    $reservations_team = "";
    if($mybb->usergroup['cancp'] == 1) {
        $count_oldAccs = $db->num_rows($db->query("SELECT uid FROM ".TABLE_PREFIX."reservations
        WHERE uid NOT IN (SELECT uid FROM ".TABLE_PREFIX."users)
        AND uid != 0
        "));

        $bannertext = "";
        if ($count_oldAccs > 0) {
            if ($count_oldAccs == 1) {
                $bannertext = $lang->sprintf($lang->reservations_banner_teamSingular, $count_oldAccs);
            } else {
                $bannertext = $lang->sprintf($lang->reservations_banner_teamPlural, $count_oldAccs);
            }
            eval("\$reservations_team = \"".$templates->get("reservations_banner_team")."\";"); 
        }
    }
    
    // Index Anzeige
    if ($mybb->settings['reservations_reminder'] == 0) {
        $reservations_index = "";
        return;
    }

    $lang->load("reservations");

    $today = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
    $today->setTime(0, 0, 0);

    $character_array = array_keys(reservations_get_allchars($activeUID));
    $userids_list = implode(',', $character_array);

    $query_reminder = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations
    WHERE enddate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ".$mybb->settings['reservations_reminder']." DAY)
    AND uid IN (".$userids_list.")
    AND lockcheck = 0
    AND showindex = 1
    ORDER BY enddate ASC;
    ");

    $reservations_index = "";
    while ($reminder = $db->fetch_array($query_reminder)) {

        // Leer laufen lassen
        $rid = "";
        $type = "";
        $reservation = "";
        $endDate = "";
        $remainingDays = "";
        $bannertext = "";

        // Mit Infos füllen
        $rid = $reminder['rid'];
        $type = $db->fetch_field($db->simple_select("reservations_types", "title", "rtid = ".$reminder['type']),"title");
        $reservation = $reminder['reservation'];

        // Enddatum & verbleibende Tage
        $endDate = new DateTime($reminder['enddate']);
        $diff = $endDate->diff($today);
        $remainingDays = (int)$diff->format('%a');

        $bannertext = $lang->sprintf($lang->reservations_banner_reminder, $reservation, $type, $remainingDays);

        eval("\$reservations_index .= \"".$templates->get("reservations_banner_reminder")."\";"); 
    }

    // Banner X
    if($mybb->get_input('action') == 'reservationsHidebanner' && !empty($mybb->get_input('rid'))) {
        $rid = (int)$mybb->get_input('rid');
        $update_showindex = array(
            "showindex" => (int)0   
        );
        $db->update_query("reservations", $update_showindex, "rid = ".(int)$rid);
        exit;
    }
}

// MOD-CP
// Nav
function reservations_modcp_nav() {

    global $mybb, $templates, $theme, $lang, $modcp_nav, $nav_reservations;

    if ($mybb->settings['reservations_blocked'] == 1) {
        $nav_reservations = "";
        return;     
    }

	// SPRACHDATEI
	$lang->load('reservations');

	eval("\$nav_reservations = \"".$templates->get ("reservations_modcp_nav")."\";");
}

// Anzeige
function reservations_modcp() {
   
    global $mybb, $templates, $lang, $theme, $header, $headerinclude, $footer, $db, $page, $modcp_nav,  $modcp_bit;

    if ($mybb->settings['reservations_blocked'] == 1) return;

    // return if the action key isn't part of the input
    if ($mybb->get_input('action', MyBB::INPUT_STRING) != 'reservations') return;

	// SPRACHDATEI
	$lang->load('reservations');

    // Seite
    if($mybb->get_input('action') == 'reservations') {

        // Add a breadcrumb
        add_breadcrumb($lang->nav_modcp, "modcp.php");
        add_breadcrumb($lang->reservations_modcp, "modcp.php?action=reservations");

        $today = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
        $today->setTime(0, 0, 0);

        $character_array = array_keys(reservations_get_allchars($mybb->user['uid']));
    
        $query_types = $db->query("SELECT rtid, title FROM ".TABLE_PREFIX."reservations_types
        ORDER BY disporder ASC, title ASC
        ");

        // Typen auslesen
        $types = "";
        while ($typ = $db->fetch_array($query_types)) {

            $rtid = "";
            $title = "";
            $rtid = $typ['rtid'];    
            $title = $typ['title'];

            $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations
            WHERE type = ".$rtid."
            AND lockcheck = 1
            ORDER BY reservation ASC            
            ");

            $reservations = "";
            while($res = $db->fetch_array($query_reservations)) {

                // Leer laufen lassen
                $rid = "";
                $uid = "";
                $reservation = "";
                $lockdate = "";
                $lockDate = "";    
                $remainingDays = "";
                $profilelink = "";
                $return = "";
                $reservations_entry_endDate = "";
                $reservations_entry_remainingDays = "";
        
                // Mit Infos füllen
                $rid = $res['rid'];
                $uid = $res['uid'];
                $reservation = $res['reservation'];   
                $playername = reservations_playername($uid); 
                $profilelink = build_profile_link($playername, $uid);
                $byUser = $lang->sprintf($lang->reservations_entry_user, $profilelink);

                // Enddatum & verbleibende Tage
                $lockDate = new DateTime($res['lockdate']);
                $lockDate->setTime(0, 0, 0);
                $lockdate = $lockDate->format('d.m.Y');
                $diff = $lockDate->diff($today);
                $remainingDays = (int)$diff->format('%a');
                $reservations_entry_endDate = $lang->sprintf($lang->reservations_entry_endDate, $lockdate);
                $reservations_entry_remainingDays = $lang->sprintf($lang->reservations_entry_remainingDays, $remainingDays);

                // Löschlink eigene Reservierung verstecken 
                $deleteNotice = $lang->sprintf($lang->reservations_blocked_delete_notice, $reservation);
                if (in_array($uid, $character_array)) {
                    $deleteLink = "style=\"display:none;\"";
                } else {
                    $deleteLink = "";
                }

                $return = "modcp";

                eval("\$reservations .= \"".$templates->get("reservations_blocked_reservations")."\";");       
            }

            eval("\$types .= \"".$templates->get("reservations_blocked_types")."\";");
        }
 
        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("reservations_modcp")."\";");
        output_page($page);
        die();
    }
}

// ONLINE LOCATION
function reservations_online_activity($user_activity) {

	global $parameters, $user;

	$split_loc = explode(".php", $user_activity['location']);
	if(isset($user['location']) && $split_loc[0] == $user['location']) { 
		$filename = '';
	} else {
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	switch ($filename) {
		case 'misc':
            if ($parameters['action'] == 'reservations') {
        		$user_activity['activity'] = 'reservations';	
            }
        break;
	}

	return $user_activity;
}
function reservations_online_location($plugin_array) {

	global $lang;
    
    // SPRACHDATEI LADEN
    $lang->load("reservations");

	if ($plugin_array['user_activity']['activity'] == "reservations") {
		$plugin_array['location_name'] = $lang->reservations_online_location;
	}

	return $plugin_array;
}

#########################
### PRIVATE FUNCTIONS ###
#########################

// ACP //

// ERROR ÜBERPRÜFUNGEN
// Typ
function reservations_validate_types($rtid = ''){

    global $mybb, $lang, $db;

    $lang->load('reservations');

    $errors = [];

    // Identifikation - nur bei neu
    if (empty($rtid)) {
        $identification = $mybb->get_input('identification');
        if (empty($identification)) {
            $errors[] = $lang->reservations_types_form_error_identification;
        } else {
            $identificationCheck = $db->fetch_field($db->simple_select("reservations_types", "identification", "identification = '".$db->escape_string($identification)."'"),"identification");
            if (!empty($identificationCheck)) {
                $errors[] = $lang->reservations_types_form_error_identification_prove;
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $identification)) {
                $errors[] = $lang->reservations_types_form_error_identification_wrong;
            }
        }
    }

    // Title
    $title = $mybb->get_input('title');
    if (empty($title)) {
        $errors[] = $lang->reservations_types_form_error_title;
    }

    // Vergleich
    $checking = $mybb->get_input('checking');
    if ($checking == 1) {    
        $checkingField = $mybb->get_input('checking_field');
        if (empty($checkingField)) {
            $errors[] = $lang->reservations_types_form_error_checkingField;
        }
    }

    return $errors;
}

// Reservierung bearbeiten
function reservations_validate_data($rid = ''){

    global $mybb, $lang, $db;

    $lang->load('reservations');

    $errors = [];

    $reservation_query = $db->simple_select("reservations", "*", "rid = '".$rid."'");
    $old = $db->fetch_array($reservation_query);
    $uid = $old['uid'];

    // Type
    $type = $mybb->get_input('type');
    if (empty($type)) {
        $errors[] = $lang->reservations_data_form_error_type;
    } else {
        $type_query = $db->simple_select("reservations_types", "*", "rtid = '".$type."'");        
        $typeData = $db->fetch_array($type_query);

        // Geschlecht
        if ($typeData['gender'] == 1) {
            $gender = $mybb->get_input('gender');
            if (empty($gender)) {
                $errors[] = $lang->reservations_data_form_error_gender;
            }
        }

        // neue Kategorie
        if ($type != $old['type']) {

            // normale User
            if ($uid != 0) {
                // Maximal Anzahl
                $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
            
                // primär [usergroup]
                if ($grouppermissionSetting == 0) {     
                    $usergroup = get_user($uid)['usergroup'];
                
                    $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                    "), "maxcount");
            
                    $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions    
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                    "), "locknote");
                } 
                // sekundär [additionalgroups]
                else {
                    $additionalgroups = get_user($uid)['additionalgroups'];
                
                    if (!empty($additionalgroups)) {
                        $additionalgroups = explode(",", $additionalgroups);

                        foreach ($additionalgroups as $additionalgroup) {
                            $limits_query = $db->fetch_array($db->query("SELECT maxcount, locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."    
                            AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')"));
                    
                            if ($limits_query) {
                                $maxcount = $limits_query['maxcount'];
                                $locknote = $limits_query['locknote'];
                                break;    
                            }                      
                        }
                    
                        if (empty($maxcount)) {      
                            $usergroup = get_user($uid)['usergroup'];
                        
                            $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                            "), "maxcount");
                        }
                    
                        if (empty($locknote)) {  
                            $usergroup = get_user($uid)['usergroup'];

                            $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                            "), "locknote");
                        }                
                    } else {            
                        $usergroup = get_user($uid)['usergroup'];

                        $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')                    
                        "), "maxcount"); 

                        $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                        "), "locknote");
                    }
                }
            
                // Limit vorhanden
                if ($maxcount > 0) {
                    // gesperrte mitbeachten = ALLE
                    if ($locknote == 1) {
                        $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                        WHERE type = ".$type."
                        AND uid = ".$uid."                
                        "));    

                        $lockNote = $lang->reservations_data_form_error_locknote;    
                    } 
                    // nur aktive
                    else {
                        $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                        WHERE type = ".$type."
                        AND uid = ".$uid."
                        AND lockcheck = 0                
                        "));

                        $lockNote = "";    
                    }
                
                    if ($rescount >= $maxcount) {
                        $errors[] = $lang->sprintf($lang->reservations_data_form_error_maxcount, $maxcount, $lockNote);
                    }
                }    
            }

        }

        // Gesuch
        if ($typeData['identification'] == $mybb->settings['reservations_searchtyp']) {
            $wantedUrl = $mybb->get_input('wantedUrl');
            if (empty($wantedUrl)) {
                $errors[] = $lang->reservations_data_form_error_wantedUrl;
            }
        }
    }

    // Reservierung
    $reservation = $mybb->get_input('reservation');
    if (empty($reservation)) {
        $errors[] = $lang->reservations_data_form_error_reservation;
    }

    // Neue UID
    if (empty(get_user($uid))) {
        $newUID = $mybb->get_input('reservation');

        if (empty($newUID)) {
            $errors[] = $lang->reservations_data_form_error_newUID;
        } else {
            if (empty(get_user($newUID))) {
                $errors[] = $lang->reservations_data_form_error_account;
            } else {
                // Maximal Anzahl
                $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
            
                // primär [usergroup]
                if ($grouppermissionSetting == 0) {     
                    $usergroup = get_user($newUID)['usergroup'];
                
                    $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                    "), "maxcount");
            
                    $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions    
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                    "), "locknote");
                } 
                // sekundär [additionalgroups]
                else {
                    $additionalgroups = get_user($newUID)['additionalgroups'];
                
                    if (!empty($additionalgroups)) {
                        $additionalgroups = explode(",", $additionalgroups);

                        foreach ($additionalgroups as $additionalgroup) {
                            $limits_query = $db->fetch_array($db->query("SELECT maxcount, locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."    
                            AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')"));
                    
                            if ($limits_query) {
                                $maxcount = $limits_query['maxcount'];
                                $locknote = $limits_query['locknote'];
                                break;    
                            }                      
                        }
                    
                        if (empty($maxcount)) {      
                            $usergroup = get_user($newUID)['usergroup'];
                        
                            $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                            "), "maxcount");
                        }
                    
                        if (empty($locknote)) {  
                            $usergroup = get_user($newUID)['usergroup'];

                            $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                            WHERE rtid = ".$type."
                            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                            "), "locknote");
                        }                
                    } else {            
                        $usergroup = get_user($newUID)['usergroup'];

                        $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')                    
                        "), "maxcount"); 

                        $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                        "), "locknote");
                    }
                }
            
                // Limit vorhanden
                if ($maxcount > 0) {
                    // gesperrte mitbeachten = ALLE
                    if ($locknote == 1) {
                        $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                        WHERE type = ".$type."
                        AND uid = ".$newUID."                
                        "));    

                        $lockNote = $lang->reservations_data_form_error_locknote;    
                    } 
                    // nur aktive
                    else {
                        $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                        WHERE type = ".$type."
                        AND uid = ".$newUID."
                        AND lockcheck = 0                
                        "));

                        $lockNote = "";    
                    }
                
                    if ($rescount >= $maxcount) {
                        $errors[] = $lang->sprintf($lang->reservations_data_form_error_maxcount, $maxcount, $lockNote);
                    }
                } 
            }
        }
    }

    return $errors;
}

// Gruppenberechtigung
function reservations_validate_grouppermission(){

    global $mybb, $lang, $db;

    $lang->load('reservations');

    $errors = [];

    // Gruppen
    $selected_groups = $mybb->get_input('usergroups', MyBB::INPUT_ARRAY);
    if (count($selected_groups) == 0) {
        $errors[] = $lang->reservations_types_grouppermission_form_error_usergroups;
    }

    // Name
    $name = $mybb->get_input('name');
    if (empty($name)) {
        $errors[] = $lang->reservations_types_grouppermission_form_error_name;
    }

    // Anzahl
    $maxcount = $mybb->get_input('maxcount');
    if ($maxcount == '') {
        $errors[] = $lang->reservations_types_grouppermission_form_error_maxcount;
    }

    // Zeitspanne
    $duration = $mybb->get_input('duration');
    if ($duration == '') {
        $errors[] = $lang->reservations_types_grouppermission_form_error_duration;
    }

    // Verlängerung
    $extend = $mybb->get_input('extend');
    if ($extend == 1) {
        
        // Anzahl
        $extendcount = $mybb->get_input('extendcount');
        if (empty($extendcount)) {
            $errors[] = $lang->reservations_types_grouppermission_form_error_extendcount;
        }
        
        // Zeitspanne
        $extendtime = $mybb->get_input('extendtime');
        if (empty($extendtime)) {
            $errors[] = $lang->reservations_types_grouppermission_form_error_extendtime;
        }
    }

    // Sperre
    $lockcount = $mybb->get_input('lockcount');
    if ($lockcount == '') {
        $errors[] = $lang->reservations_types_grouppermission_form_error_lockcount;
    }

    return $errors;
}

// Benutzergruppen auslesen
function reservations_usergroups_list($rtid, $rgid = '') {

    global $db;

    if (!empty($rgid)) {
        $query_usergroups = $db->query("SELECT usergroups FROM ".TABLE_PREFIX."reservations_grouppermissions WHERE rtid = ".$rtid." AND rgid != ".$rgid);
    } else {
        $query_usergroups = $db->query("SELECT usergroups FROM ".TABLE_PREFIX."reservations_grouppermissions WHERE rtid = ".$rtid);
    }
    
    $usergroups = [];
    while($userg = $db->fetch_array($query_usergroups)) {
        $usergroups[] = $userg['usergroups'];
    }

    $usergroup_sql = "";
    if (count($usergroups) > 0) {
        $usergroup_sql = "WHERE gid NOT IN (".implode(",",$usergroups).")";
    }

    // Benutzergruppen auslesen
    $query_usergroups = $db->query("SELECT gid, title FROM ".TABLE_PREFIX."usergroups
    ".$usergroup_sql."
    ORDER BY disporder ASC   
    ");

    $usergroups_list = [];
    while($group = $db->fetch_array($query_usergroups)) {
        $usergroups_list[$group['gid']] = $group['title'];
    }

    return $usergroups_list;
}

// mögliche Zusammenführungen
function reservations_condensedgroups_query($rtid) {

    global $db;

    $existing_condensed = $db->fetch_field($db->query("SELECT condensedgroups FROM ".TABLE_PREFIX."reservations_types 
    WHERE rtid = ".$rtid
    ),"condensedgroups");

    $excluded_rgids = [];
    $condensed = explode("\n", $existing_condensed);
    foreach($condensed as $con) {
        $ids = array_map('trim', explode(',', $con));
        $excluded_rgids = array_merge($excluded_rgids, $ids);
    }

    $excluded_condition = '';
    if (!empty($excluded_rgids)) {
        $excluded_rgids = array_filter($excluded_rgids, function($id) {
            return is_numeric($id) && $id !== '';
        });
        
        if (!empty($excluded_rgids)) {
            $excluded_condition = "AND rgid NOT IN (".implode(',', $excluded_rgids).")";
        }
    }

    $query_grouppermissions = $db->query("SELECT rgid, name FROM ".TABLE_PREFIX."reservations_grouppermissions
    WHERE rtid = ".$rtid." 
    ".$excluded_condition."
    ORDER BY disporder ASC, name ASC
    ");

    return $query_grouppermissions;
}

// Reservierungen - Menü
function reservations_types_nav_tabs($active = '') {

    global $db, $page, $lang;

    $sub_tabs = [];

    $query_types = $db->query("SELECT identification, title FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC        
    ");

    while ($typ = $db->fetch_array($query_types)) {

        // leer laufen lassen
        $identification = "";
        $title = "";

        // mit Infos füllen
        $identification = $typ['identification'];
        $title = $typ['title'];

        $sub_tabs[$identification] = [
            "title" => $title,
            "link" => "index.php?module=rpgstuff-reservations_data&amp;action=".$identification,
            "description" => $lang->sprintf($lang->reservations_data_tab_desc, $title)
        ];
    }

    $sub_tabs['blocked'] = [
        "title" => $lang->reservations_data_tab_blocked,
        "link" => "index.php?module=rpgstuff-reservations_data&amp;action=blocked",
        "description" => $lang->reservations_data_tab_blocked_desc
    ];

    $page->output_nav_tabs($sub_tabs, $active);
}

// FORUM //

// ohne Tabs
function reservations_output_page($return = '') {

    global $db, $mybb, $lang, $templates, $theme, $reservations_types_bit;

	$lang->load("reservations");

    $genderOptions = $mybb->settings['reservations_gender'];
    $genderArray = array_map('trim', explode(',', $genderOptions));

    // Alle Typen
    $query_types = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC        
    ");

    // Typen auslesen
    $reservations_types = "";
    while ($typ = $db->fetch_array($query_types)) {

        // leer laufen lassen
        $rtid = "";
        $identification = "";
        $title = "";
        $disporder = "";
        $gender = "";
        $checkoption = "";
        $checkfield = "";
        $checkname = "";
        $groupsplit = "";        
        $condensedgroups = "";
        $reservationsbit = "";
        $reservationsPage_typesClass = "";
        $genderflex = "";
        $headline = "";

        // mit Infos füllen
        $rtid = $typ['rtid'];
        $identification = $typ['identification'];
        $title = $typ['title'];
        $disporder = $typ['disporder'];
        $gender = $typ['gender'];
        $checkoption = $typ['checkoption'];
        $checkfield = $typ['checkfield'];
        $checkname = $typ['checkname'];
        $groupsplit = $typ['groupsplit'];
        $condensedgroups = $typ['condensedgroups'];
        $headline = $lang->sprintf($lang->reservations_type_headline, $title);

        $reservations = reservations_get_entry($rtid);

        // Einzelne Gruppen
        if ($groupsplit == 1) {
            
            $groupMap = reservations_usergroups_output($rtid);
            
            $reservations_types_bit = "";
            foreach ($groupMap as $rgidCsv => $label) {

                $groupname = $label; 
                $reservations_bit = "";
                $usergroupIDs = explode(',', reservations_usergroupsIDs($rgidCsv));
            
                if ($gender == 1) {

                    $reservations_bit = "";
                    foreach ($genderArray as $genderOption) {   
                
                        $gendername = $genderOption;

                        $reservations_user = "";
                        foreach ($reservations as $res) {
                            // üperprüfung Gruppe
                            if ($res['gid'] == "" || !in_array($res['gid'], $usergroupIDs)) {
                                continue;
                            }
        
                            // Überprüfung Geschlecht
                            if ($res['gender'] == "" || $res['gender'] != $genderOption) {
                                continue;
                            }

                            $reservations_user .= reservations_user_entry($res, $return);
                        }
                    
                        eval("\$reservations_bit .= \"".$templates->get("reservations_output_types_gender")."\";");
                    }   
                    $genderflex = "reservations_page-genderflex";                 
                } else {

                    $reservations_bit = "";
                    foreach ($reservations as $res) {
                        if ($res['gid'] == "" || !in_array($res['gid'], $usergroupIDs)) {
                            continue;                 
                        }

                        $reservations_bit .= reservations_user_entry($res, $return);              
                    }
                } 
                
                eval("\$reservations_types_bit .= \"".$templates->get("reservations_output_types_groups")."\";");
            }
            $reservationsPage_typesClass = "reservationsGroups";
        } 
        // Großer Block
        else {
            if ($gender == 1) {          
                $reservations_types_bit = "";
                foreach ($genderArray as $genderOption) {   
                                          
                    $gendername = $genderOption;

                    $reservations_user = "";    
                    foreach ($reservations as $res) {
    
                        if ($res['gender'] == "" || $res['gender'] != $genderOption) {
                            continue;            
                        }

                        $reservations_user .= reservations_user_entry($res, $return);
                    }

                    eval("\$reservations_types_bit .= \"".$templates->get("reservations_output_types_gender")."\";");
                }    
                $genderflex = "reservations_page-genderflex";  
            } else {
                $reservations_types_bit = "";
                foreach ($reservations as $res) {
                    $reservations_types_bit .= reservations_user_entry($res, $return);                     
                }
            }

            $reservationsPage_typesClass = "reservationsSingle";
        }
        
        eval("\$reservations_types .= \"".$templates->get("reservations_output_types")."\";");
    }
	
    return $reservations_types;
}
// mit Tabs
function reservations_output_page_tabs($return = '') {

    global $db, $mybb, $lang, $templates, $theme, $reservations_tabs, $tabContent, $tabMenu;

	$lang->load("reservations");

    $genderOptions = $mybb->settings['reservations_gender'];
    $genderArray = array_map('trim', explode(',', $genderOptions));

    // Alle Typen
    $query_types = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC        
    ");

    // Typen auslesen
    $reservations_tabs = "";
    $tabMenu = "";
    $tabContent = "";
    $isFirst = true;
    while ($typ = $db->fetch_array($query_types)) {

        // leer laufen lassen
        $rtid = "";
        $identification = "";
        $title = "";
        $gender = "";
        $groupsplit = "";      
        $reservationsbit = "";
        $defaultTab = "";
        $genderflex = "";
        $reservationsPage_typesClass = "";
        $headline = "";

        // mit Infos füllen
        $rtid = $typ['rtid'];
        $identification = $typ['identification'];
        $title = $typ['title'];
        $gender = $typ['gender'];
        $groupsplit = $typ['groupsplit'];
        $headline = $lang->sprintf($lang->reservations_type_headline, $title);

        $reservations = reservations_get_entry($rtid);

        // Einzelne Gruppen
        if ($groupsplit == 1) {
            
            $groupMap = reservations_usergroups_output($rtid);
            
            $reservations_types_bit = "";
            foreach ($groupMap as $rgidCsv => $label) {

                $groupname = $label; 
                $reservations_bit = "";
                $usergroupIDs = explode(',', reservations_usergroupsIDs($rgidCsv));
            
                if ($gender == 1) {

                    $reservations_bit = "";
                    foreach ($genderArray as $genderOption) {   
                
                        $gendername = $genderOption;

                        $reservations_user = "";
                        foreach ($reservations as $res) {
                            // üperprüfung Gruppe
                            if ($res['gid'] == "" || !in_array($res['gid'], $usergroupIDs)) {
                                continue;
                            }
        
                            // Überprüfung Geschlecht
                            if ($res['gender'] == "" || $res['gender'] != $genderOption) {
                                continue;
                            }

                            $reservations_user .= reservations_user_entry($res, $return);
                        }
                    
                        eval("\$reservations_bit .= \"".$templates->get("reservations_output_types_gender")."\";");
                    }            
                    $genderflex = "reservations_page-genderflex";          
                } else {

                    $reservations_bit = "";
                    foreach ($reservations as $res) {
                        if ($res['gid'] == "" || !in_array($res['gid'], $usergroupIDs)) {
                            continue;                 
                        }

                        $reservations_bit .= reservations_user_entry($res, $return);                
                    }
                } 
                
                eval("\$reservations_types_bit .= \"".$templates->get("reservations_output_types_groups")."\";");
            }

            $reservationsPage_typesClass = "reservationsGroups";
        } 
        // Großer Block
        else {
            if ($gender == 1) {    
                $reservations_types_bit = "";
                foreach ($genderArray as $genderOption) {   
                                          
                    $gendername = $genderOption;

                    $reservations_user = "";    
                    foreach ($reservations as $res) {
    
                        if ($res['gender'] == "" || $res['gender'] != $genderOption) {
                            continue;            
                        }

                        $reservations_user .= reservations_user_entry($res, $return);
                    }

                    eval("\$reservations_types_bit .= \"".$templates->get("reservations_output_types_gender")."\";");
                }
                $genderflex = "reservations_page-genderflex";  
            } else {
                $reservations_types_bit = "";
                foreach ($reservations as $res) {
                    $reservations_types_bit .= reservations_user_entry($res, $return);                     
                }
            }
            
            $reservationsPage_typesClass = "reservationsSingle";
        } 

        eval("\$tabContent .= \"".$templates->get("reservations_output_tabs_content")."\";");      
        
        if ($isFirst) {
            $defaultTab = "id=\"reservations_defaultTab\"";
            $isFirst = false; 
        }

        eval("\$tabMenu .= \"".$templates->get("reservations_output_tabs_menu")."\";");
    }

    eval("\$reservations_tabs = \"".$templates->get("reservations_output_tabs")."\";");
    return $reservations_tabs;
}

// Formular
// Misc
function reservations_output_formular_misc($typeInput = '', $reservationInput = '', $genderInput = '', $playernameinput = '', $wantedUrlInput = '') {

    global  $db, $mybb, $lang, $templates, $theme;

    $reservations_form = "";

    // keine Reservierung möglich - nirgends in den Gruppenberechtigungen
    $query_grouppermissions = $db->query("SELECT usergroups FROM ".TABLE_PREFIX."reservations_grouppermissions
    ORDER BY disporder ASC, name ASC
    ");

    $allusergroupIDs = [];
    while ($permission = $db->fetch_array($query_grouppermissions)) {
        $allusergroupIDs[] = $permission['usergroups'];
    }
    $allusergroupIDs = implode(',', $allusergroupIDs);

    if (!is_member($allusergroupIDs)) {
        $reservations_form = "";
        return $reservations_form;     
    }

    $activeUID = $mybb->user['uid'];

	$lang->load("reservations");

    // darfst reservieren
    $genderOptions = $mybb->settings['reservations_gender'];
    $genderArray = array_map('trim', explode(',', $genderOptions));

    $gender_select = "";
    foreach ($genderArray as $genderOption) {
        if ($genderInput == $genderOption) {
            $gender_select .= "<option value=\"".$genderOption."\" selected>".$genderOption."</option>";
        } else {
            $gender_select .= "<option value=\"".$genderOption."\">".$genderOption."</option>";
        }
    }

    // Typen
    $query_types = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC        
    ");

    $type_select = "";
    while ($typ = $db->fetch_array($query_types)) {

        // leer laufen lassen
        $rtid = "";
        $title = "";
        $description = "";
        $gender = "";
        $identification = "";
        $wantedUrl = 0;

        // mit Infos füllen
        $rtid = $typ['rtid'];
        $title = $typ['title'];
        $description = $typ['description'];
        $gender = $typ['gender'];
        $identification = $typ['identification'];
        if ($identification == $mybb->settings['reservations_searchtyp']) {
            $wantedUrl = 1;
        }
           
        $groupMap = reservations_usergroups_output($rtid);
        
        $usergroupIDs = [];    
        
        foreach (array_keys($groupMap) as $rgidCsv) {
            $ids = reservations_usergroupsIDs($rgidCsv); 
            $usergroupIDs = array_merge($usergroupIDs, explode(',', $ids));    
        }
        
        $usergroupIDs = implode(',', $usergroupIDs);
        
        if (is_member($usergroupIDs)) {
            if ($typeInput == $rtid) {
                $type_select .= "<option value=\"{$rtid}\" data-has-gender=\"{$gender}\" data-has-wanted-url=\"{$wantedUrl}\" data-description=\"{$description}\" selected>{$title}</option>";
                } else {
                $type_select .= "<option value=\"{$rtid}\" data-has-gender=\"{$gender}\" data-has-wanted-url=\"{$wantedUrl}\" data-description=\"{$description}\">{$title}</option>";    
            }                
        }
    }

    // Spitznamenfeld
    if ($activeUID == 0) {
        $playernameDisplay = "";
        $playernameInput = "<input type=\"text\" name=\"playername\" id=\"playername\" placeholder=\"dein Spitzname\" value=\"".$playernameinput."\" class=\"textbox\">";
    } else {
        $playernameDisplay = "style=\"display: none;\"";
        $playernameInput = "";
    }

    eval("\$reservations_form = \"".$templates->get("reservations_page_formular")."\";");

    return $reservations_form;
}
// Showthread
function reservations_output_formular_showthread($typeInput = '', $reservationInput = '', $genderInput = '', $playername = '', $wantedUrlInput = '', $tid = 0) {

    global  $db, $mybb, $lang, $templates, $theme, $reservations_form;

    // nur Teamies sehen es
    $teamgroups = $mybb->settings['reservations_team'];
    if (!is_member($teamgroups)) {
        $reservations_form = "";
        return $reservations_form;     
    }

	$lang->load("reservations");

    $genderOptions = $mybb->settings['reservations_gender'];
    $genderArray = array_map('trim', explode(',', $genderOptions));

    $gender_select = "";
    foreach ($genderArray as $genderOption) {
        if ($genderInput == $genderOption) {
            $gender_select .= "<option value=\"".$genderOption."\" selected>".$genderOption."</option>";
        } else {
            $gender_select .= "<option value=\"".$genderOption."\">".$genderOption."</option>";
        }
    }

    // Typen
    $query_types = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC        
    ");

    $type_select = "";
    while ($typ = $db->fetch_array($query_types)) {

        // leer laufen lassen
        $rtid = "";
        $title = "";
        $description = "";
        $gender = "";
        $identification = "";
        $wantedUrl = 0;

        // mit Infos füllen
        $rtid = $typ['rtid'];
        $title = $typ['title'];
        $gender = $typ['gender'];
        $description = $typ['description'];
        $identification = $typ['identification'];
        if ($identification == $mybb->settings['reservations_searchtyp']) {
            $wantedUrl = 1;
        }

        if ($typeInput == $rtid) {
            $type_select .= "<option value=\"{$rtid}\" data-has-gender=\"{$gender}\" data-has-wanted-url=\"{$wantedUrl}\" data-description=\"{$description}\" selected>{$title}</option>";
        } else {
            $type_select .= "<option value=\"{$rtid}\" data-has-gender=\"{$gender}\" data-has-wanted-url=\"{$wantedUrl}\" data-description=\"{$description}\">{$title}</option>";    
        } 
    }

    eval("\$reservations_form = \"".$templates->get("reservations_showthread_formular")."\";");

    return $reservations_form;
}

// Speichern Errors 
function reservations_validate_entry() {

    global $mybb, $lang, $db;

    $lang->load('reservations');

    $errors = [];

    // misc
    if (!empty($mybb->get_input('uid'))) {

        $uid = $mybb->get_input('uid');

        // Spielername
        if ($uid == 0) {
            $playername = $mybb->get_input('playername');
            if (empty($playername)) {
                $errors[] = $lang->reservations_form_error_playername;
            }
        }
    } 
    // Showthread
    else {
        $playername = $db->escape_string($mybb->get_input('playername'));

        if (empty($playername)) {
            $errors[] = $lang->reservations_form_error_playername;
        } else {
            $playerUID = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users WHERE username = '".$playername."'"), "uid");
            
            if (!empty($playerUID)) {
                $uid = $playerUID;
            } else {
                $uid = 0;
            }
        }
    }

    // Typ
    $type = $mybb->get_input('type');
    if (empty($type)) {
        $errors[] = $lang->reservations_form_error_type;
    } else {
        $type_query = $db->simple_select("reservations_types", "*", "rtid = '".$type."'");        
        $typeData = $db->fetch_array($type_query);

        // Geschlecht
        if ($typeData['gender'] == 1) {
            $gender = $mybb->get_input('gender');
            if (empty($gender)) {
                $errors[] = $lang->reservations_form_error_gender;
            }
        }

        // normale User
        if ($uid != 0) {
            // Maximal Anzahl
            $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];

            // primär [usergroup]
            if ($grouppermissionSetting == 0) {     

                $usergroup = get_user($uid)['usergroup'];

                $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$type."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "maxcount");
            
                $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$type."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                "), "locknote");
            } 
            // sekundär [additionalgroups]
            else {
                $additionalgroups = get_user($uid)['additionalgroups'];
                if (!empty($additionalgroups)) {

                    $additionalgroups = explode(",", $additionalgroups);

                    foreach ($additionalgroups as $additionalgroup) {

                        $limits_query = $db->fetch_array($db->query("SELECT maxcount, locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')"));
                    
                        if ($limits_query) {
                            $maxcount = $limits_query['maxcount'];
                            $locknote = $limits_query['locknote'];
                            break;    
                        }                      
                    }
                    
                    if (empty($maxcount)) {      
                        $usergroup = get_user($uid)['usergroup'];
                        
                        $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                        "), "maxcount");
                    }

                    if (empty($locknote)) {  

                        $usergroup = get_user($uid)['usergroup'];

                        $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                        WHERE rtid = ".$type."
                        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                        "), "locknote");
                    }
    
                } else {            

                    $usergroup = get_user($uid)['usergroup'];

                    $maxcount = $db->fetch_field($db->query("SELECT maxcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')    
                    "), "maxcount"); 

                    $locknote = $db->fetch_field($db->query("SELECT locknote FROM ".TABLE_PREFIX."reservations_grouppermissions
                    WHERE rtid = ".$type."
                    AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')      
                    "), "locknote");
                }
            }
            
            // Limit vorhanden
            if ($maxcount > 0) {
                // gesperrte mitbeachten = ALLE
                if ($locknote == 1) {
                    $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                    WHERE type = ".$type."
                    AND uid = ".$uid."
                    "));    

                    $lockNote = $lang->reservations_form_error_locknote;    
                } 
                // nur aktive
                else {
                    $rescount =  $db->num_rows($db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
                    WHERE type = ".$type."
                    AND uid = ".$uid."
                    AND lockcheck = 0
                    "));

                    $lockNote = "";    
                }
                
                if ($rescount >= $maxcount) {
                    $errors[] = $lang->sprintf($lang->reservations_form_error_maxcount, $maxcount, $lockNote);
                }
            }
        } 

        // Gesuche
        if ($typeData['identification'] == $mybb->settings['reservations_searchtyp']) {
            $wantedUrl = $mybb->get_input('wantedUrl');
            if (empty($wantedUrl)) {
                $errors[] = $lang->reservations_form_error_wantedUrl;
            }
        }
    }

    // Reservierung
    $reservation = strtolower(trim(preg_replace('/\s+/', ' ', $mybb->get_input('reservation'))));
    $reservation = $db->escape_string($reservation);
    if (empty($reservation)) {
        $errors[] = $lang->reservations_form_error_reservation;
    } else {
    
        // Vergleich mit Forendaten
        if (!empty($typeData)) {
            // Profilfeld/Steckbrieffeld
            if ($typeData['checkoption'] == 1) {
                // Profilfeld
                if (is_numeric($typeData['checkfield'])) {  
                    $profilefield = "fid".$typeData['checkfield'];

                    $checkOption = $db->fetch_field($db->query("SELECT ".$profilefield." FROM ".TABLE_PREFIX."userfields 
                    WHERE LOWER(".$profilefield.") = '".$reservation."'
                    LIMIT 1
                    "), $profilefield);
                } 
                // Steckbrieffeld
                else {  
                    $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$typeData['checkfield']."'"), "id");

                    $checkOption = $db->fetch_field($db->query("SELECT value FROM ".TABLE_PREFIX."application_ucp_userfields 
                    WHERE LOWER(value) = '".$reservation."'
                    AND fieldid = ".$fieldid."
                    LIMIT 1
                    "), "value");
                }
            } 
            // Accountname
            else if ($typeData['checkoption'] == 2) {
                // Vorname
                if ($typeData['checkname'] == 0) {
                    $checkOption = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users
                    WHERE LOWER(SUBSTRING_INDEX(username, ' ', 1)) = '".$reservation."'
                    LIMIT 1
                    "), "uid");
                } 
                // Nachanme
                else if ($typeData['checkname'] == 1) {
                    $checkOption = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users
                    WHERE LOWER(SUBSTRING(username, LOCATE(' ', username) + 1)) = '".$reservation."'
                    LIMIT 1
                    "), "uid");
                } 
                // ganzer Name
                else if ($typeData['checkname'] == 2) {
                    $checkOption = $db->fetch_field($db->query("SELECT uid FROM ".TABLE_PREFIX."users
                    WHERE LOWER(username) = '".$reservation."'
                    "), "uid");
                }
            }
        
            if (!empty($checkOption)) {
                $errors[] = $lang->reservations_form_error_check_forum;
            }
        }

        // Vergleich mit Reservierungseinträgen
        $checkRes = $db->fetch_field($db->query("SELECT reservation FROM ".TABLE_PREFIX."reservations 
        WHERE LOWER(reservation) = '".$reservation."'
        "), "reservation");

        if (!empty($checkRes)) {
            $errors[] = $lang->reservations_form_error_check_res;
        }
    }

    return $errors;
}

// einzelne Gruppen Ausgabe
function reservations_usergroups_output($rtid) {

    global $db;

    $rtid = (int)$rtid;

    // Alle Gruppenberechtigungen des Typs
    $query_grouppermissions = $db->query("SELECT rgid, name, disporder FROM ".TABLE_PREFIX."reservations_grouppermissions
    WHERE rtid = ".$rtid."
    ORDER BY disporder ASC, name ASC
    ");

    $permissions = array(); // rgid => ['name' => ..., 'disporder' => ...]
    while ($group = $db->fetch_array($query_grouppermissions)) {
        $permissions[(int)$group['rgid']] = array(
            'name'      => $group['name'],
            'disporder' => (int)$group['disporder']
        );
    }

    if (empty($permissions)) {
        return array();
    }

    $entries = array();
    $used    = array();

    // Condensed-Gruppen
    $typeCondensed = $db->fetch_field($db->simple_select("reservations_types", "condensedgroups", "rtid = ".$rtid), "condensedgroups");

    if (!empty($typeCondensed)) {
        $lines = preg_split('/\r\n|\r|\n/', trim($typeCondensed));

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode(',', $line);
            $ids   = array();

            foreach ($parts as $part) {
                $id = (int)trim($part);
                if (isset($permissions[$id])) {
                    $ids[] = $id;
                }
            }

            if (empty($ids)) {
                continue;
            }

            $ids = array_values(array_unique($ids));

            usort($ids, function($a, $b) use ($permissions) {
                $dispA = $permissions[$a]['disporder'];
                $dispB = $permissions[$b]['disporder'];

                if ($dispA == $dispB) {
                    return strcasecmp($permissions[$a]['name'], $permissions[$b]['name']);
                }

                return $dispA - $dispB;
            });

            $names = array();
            foreach ($ids as $id) {
                $names[]   = $permissions[$id]['name'];
                $used[$id] = true;
            }

            $sortorder = $permissions[$ids[0]]['disporder'];

            $entries[] = array(
                'key'       => implode(',', $ids),
                'label'     => implode(' &amp; ', $names),
                'disporder' => $sortorder,
                'name'      => $permissions[$ids[0]]['name']
            );
        }
    }

    // übrige Einzelgruppen
    foreach ($permissions as $rgid => $perm) {
        if (!isset($used[$rgid])) {
            $entries[] = array(
                'key'       => (string)$rgid,
                'label'     => $perm['name'],
                'disporder' => $perm['disporder'],
                'name'      => $perm['name']
            );
        }
    }

    // Gesamtausgabe sortieren
    usort($entries, function($a, $b) {
        if ($a['disporder'] == $b['disporder']) {
            return strcasecmp($a['name'], $b['name']);
        }

        return $a['disporder'] - $b['disporder'];
    });

    $map = array();
    foreach ($entries as $entry) {
        $map[$entry['key']] = $entry['label'];
    }

    return $map;
}

// Usergruppen IDs der einzelne Gruppen
function reservations_usergroupsIDs($rgidCsv) {

    global $db;

    $rgids = array_map('trim', explode(',', $rgidCsv));

    $usergroupIDs = array();
    foreach ($rgids as $rgid) {
         $usergroupIDs[] = $db->fetch_field($db->write_query("SELECT usergroups FROM ".TABLE_PREFIX."reservations_grouppermissions WHERE rgid = ".$rgid),"usergroups");
    }

    $all = implode(",", $usergroupIDs);

    return $all;
}

// Einzelne Reservierungen pro Typ
function reservations_get_entry($rtid) {

    global $db;

    $output = array();

    $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations 
    WHERE type = ".$rtid."
    AND lockcheck = 0
    ORDER BY reservation ASC");

    while ($res = $db->fetch_array($query_reservations)) {

        if ($res['uid'] != 0 && !empty(get_user($res['uid']))) {
            $res['gid'] = get_user($res['uid'])['usergroup'];
        } else {
            $res['gid'] = 1;
        }

        $output[] = $res;
    }

    return $output;
}

// Ausgabe Reservierungen
function reservations_user_entry($res, $return = '') {

    global $db, $mybb, $lang, $templates, $theme;

    $reservations_user = "";
    
	$activeUID = $mybb->user['uid'];
    $today = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
    $today->setTime(0, 0, 0);

    // Leer laufen lassen
    $rid = "";
    $uid = "";
    $playername = "";
    $type = "";
    $gender = "";
    $reservation = "";
    $enddate = "";
    $extension = "";
    $lockcheck = "";
    $lockdate = "";
    $member = "";
    $endDate = "";
    $remainingDays = "";
    $wantedUrl = "";
    $wanted = "";
    $endlessNote = "";
    $byUser = "";
    $optionExtend = "";
    $optionDelete = "";
    $reservations_entry_endDate = "";
    $reservations_entry_remainingDays = "";

    // Mit Infos füllen
    $rid = $res['rid'];
    $uid = $res['uid'];
    $type = $res['type'];
    $gender = $res['gender'];
    $reservation = $res['reservation'];
    $extension = $res['extension'];
    $lockcheck = $res['lockcheck'];
    $lockdate = $res['lockdate'];
    $endlessNote = $res['endlessNote'];
    $wantedUrl = $res['wantedUrl'];

    if (!empty($wantedUrl)) {
        $wanted = "<a href\"".$wantedUrl."\">".$lang->reservations_entry_wanted."</a>";
    }

    // endlosse reservierung
    if ($endlessNote == 1) {
        $reservations_entry_endDate = $lang->sprintf($lang->reservations_entry_endDate, $lang->reservations_entry_openend);
        $remainingDays = "";
    } else {
        // Enddatum & verbleibende Tage
        $endDate = new DateTime($res['enddate']);
        $endDate->setTime(0, 0, 0);
        $enddate = $endDate->format('d.m.Y');
        $diff = $endDate->diff($today);
        $remainingDays = (int)$diff->format('%a');

        $reservations_entry_endDate = $lang->sprintf($lang->reservations_entry_endDate, $enddate);
        $reservations_entry_remainingDays = $lang->sprintf($lang->reservations_entry_remainingDays, $remainingDays);
    }

    // User bauen
    if ($uid == 0) {
        $playername = $res['playername'];
        $member = $playername.$lang->reservations_guest;
    } else {
        if (!empty(get_user($uid))) {
            $playername = reservations_playername($uid);
            $member = build_profile_link($playername, $uid);
        } else {
            $playername = $res['playername'];
            $member = $playername;
        }
    }
    $byUser = $lang->sprintf($lang->reservations_entry_user, $member);

    // OPTIONEN
    if ($activeUID == 0) {
        $optionExtend = "";
        $optionDelete = "";
    } else {
        $character_array = array_keys(reservations_get_allchars($mybb->user['uid']));
        $teamgroups = $mybb->settings['reservations_team'];
        if (in_array($uid, $character_array) || is_member($teamgroups)) {

            $extendOptions = reservations_extendOptions($uid, $type);
            if (($extension < $extendOptions['extendLimit']) && $uid != 0) {
                $optionExtend = "<a href=\"misc.php?action=reservations_extend&rid=".$rid."&return=".$return."\">".$lang->reservations_entry_extend."</a>";
            } else {
                $optionExtend = "";
            }

            $deleteNotice = $lang->sprintf($lang->reservations_entry_delete_notice, $reservation);
            $optionDelete = "<a href=\"misc.php?action=reservations_delete&rid=".$rid."&return=".$return."\" onclick=\"return confirm('".$deleteNotice."');\">".$lang->reservations_entry_delete."</a>";
        } else {
            $optionExtend = "";
            $optionDelete = "";
        }
    }
                                
    eval("\$reservations_user .= \"".$templates->get("reservations_output_entry")."\";");

    return $reservations_user;
}

// Spitzname
function reservations_playername($uid) {
    
    global $db, $mybb;

    $playername_setting = $mybb->settings['reservations_playername'];

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

// Infotext
function reservations_infotext() {

    global $mybb;

    $infotext = "";

    if (!empty($mybb->settings['reservations_infotext'])) {

        require_once MYBB_ROOT."inc/class_parser.php";
        $parser = new postParser;
        $parser_array = array(
            "allow_html" => 1,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 0,
            "filter_badwords" => 0,
            "nl2br" => 1,
            "allow_videocode" => 0       
        );

        $infotext = $parser->parse_message($mybb->settings['reservations_infotext'], $parser_array);    
    }
    
    return $infotext;
}

// Gesperrte Reservierungen
function reservations_blocked($label = 'showthread') {

    global $db, $mybb, $lang, $templates, $theme, $blockedReservations;

    if ($mybb->settings['reservations_blocked'] == 0) {
        $blockedReservations = "";
        return $blockedReservations;     
    }

    $teamgroups = $mybb->settings['reservations_team'];
    if (!is_member($teamgroups)) {
        $blockedReservations = "";
        return $blockedReservations;     
    }

    $lang->load("reservations");

    $today = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
    $today->setTime(0, 0, 0);

    $character_array = array_keys(reservations_get_allchars($mybb->user['uid']));

    $query_types = $db->query("SELECT rtid, title FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC
    ");

    // Typen auslesen
    $types = "";
	while ($typ = $db->fetch_array($query_types)) {

        $rtid = "";
        $title = "";
        $rtid = $typ['rtid'];
        $title = $typ['title'];

        $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations
        WHERE type = ".$rtid."
        AND lockcheck = 1
        ORDER BY reservation ASC        
        ");

        $reservations = "";
        while($res = $db->fetch_array($query_reservations)) {

            // Leer laufen lassen
            $rid = "";
            $uid = "";
            $reservation = "";
            $lockdate = "";
            $lockDate = "";    
            $remainingDays = "";
            $profilelink = "";
            $return = "";
            $reservations_entry_endDate = "";
            $reservations_entry_remainingDays = "";
        
            // Mit Infos füllen
            $rid = $res['rid'];
            $uid = $res['uid'];
            $reservation = $res['reservation'];   
            $playername = reservations_playername($uid);
            $profilelink = build_profile_link($playername, $uid);
            $byUser = $lang->sprintf($lang->reservations_entry_user, $profilelink);

            // Enddatum & verbleibende Tage
            $lockDate = new DateTime($res['lockdate']);
            $lockDate->setTime(0, 0, 0);
            $lockdate = $lockDate->format('d.m.Y');
            $diff = $lockDate->diff($today);
            $remainingDays = (int)$diff->format('%a');
            $reservations_entry_endDate = $lang->sprintf($lang->reservations_entry_endDate, $lockdate);
            $reservations_entry_remainingDays = $lang->sprintf($lang->reservations_entry_remainingDays, $remainingDays);

            // Löschlink eigene Reservierung verstecken
            $deleteNotice = $lang->sprintf($lang->reservations_blocked_delete_notice, $reservation);
            if (in_array($uid, $character_array)) {
                $deleteLink = "style=\"display:none;\"";
            } else {
                $deleteLink = "";
            }

            if ($label == 'showthread') {
                $return = "showthread";
            } else {
                $return = "misc";
            }

           eval("\$reservations .= \"".$templates->get("reservations_blocked_reservations")."\";");       
        }

        eval("\$types .= \"".$templates->get("reservations_blocked_types")."\";");
    }

    if ($label == 'showthread') {
        eval("\$blockedReservations = \"".$templates->get("reservations_blocked_showthread")."\";");
    } else {
        eval("\$blockedReservations = \"".$templates->get("reservations_blocked_page")."\";");
    }

    return $blockedReservations;
}

// ACCOUNTSWITCHER HILFSFUNKTION => Danke, Katja <3
function reservations_get_allchars($user_id) {

	global $db;

    if (intval($user_id) === 0) {
        return array();
    }

	//für den fall nicht mit hauptaccount online
	if (isset(get_user($user_id)['as_uid'])) {
        $as_uid = intval(get_user($user_id)['as_uid']);
    } else {
        $as_uid = 0;
    }

	$charas = array();
	if ($as_uid == 0) {
	  // as_uid = 0 wenn hauptaccount oder keiner angehangen
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$user_id.") OR (uid = ".$user_id.") ORDER BY username");
	} else if ($as_uid != 0) {
	  //id des users holen wo alle an gehangen sind 
	  $get_all_users = $db->query("SELECT uid,username FROM ".TABLE_PREFIX."users WHERE (as_uid = ".$as_uid.") OR (uid = ".$user_id.") OR (uid = ".$as_uid.") ORDER BY username");
	}
	while ($users = $db->fetch_array($get_all_users)) {
        $uid = $users['uid'];
        $charas[$uid] = $users['username'];
	}
    // $charas => ['uid' => 'username, '4' => 'Vorname Nachname',...]
	return $charas;  
}

// Verlängerungen Daten
function reservations_extendOptions($uid, $rtid) {

	global $db, $mybb;

    $user = get_user($uid);
    if(!$user) {
        return [
            "extendDays" => 0,
            "extendLimit" => 0
        ];
    }
            
    $grouppermissionSetting = $mybb->settings['reservations_grouppermission'];
    // primär [usergroup]            
    if ($grouppermissionSetting == 0) {
        $usergroup = $user['usergroup'];

        $extendDays = $db->fetch_field($db->query("SELECT extendtime FROM ".TABLE_PREFIX."reservations_grouppermissions
        WHERE rtid = ".$rtid."
        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')        
        "), "extendtime");

        $extendLimit = $db->fetch_field($db->query("SELECT extendcount FROM ".TABLE_PREFIX."reservations_grouppermissions
        WHERE rtid = ".$rtid."
        AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
        "), "extendcount");    
    } 
    // sekundär [additionalgroups]    
    else {
        $additionalgroups = $user['additionalgroups'];

        if (!empty($additionalgroups)) {
            $additionalgroups = explode(",", $additionalgroups);
            foreach ($additionalgroups as $additionalgroup) {
                $extendDays = $db->fetch_field($db->query("SELECT extendtime FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')
                "), "extendtime");
                            
                if (!empty($extendDays)) {
                    break;    
                }

                $extendLimit = $db->fetch_field($db->query("SELECT extendcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$additionalgroup.",%')
                "), "extendcount");
                            
                if (!empty($extendLimit)) {
                    break;
                }
            }
                        
            if (empty($extendDays)) {
                $usergroup = get_user($uid)['usergroup'];
    
                $extendDays = $db->fetch_field($db->query("SELECT extendtime FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "extendtime");
            }   
                        
            if (empty($extendLimit)) {
                $usergroup = get_user($uid)['usergroup'];

                $extendLimit = $db->fetch_field($db->query("SELECT extendcount FROM ".TABLE_PREFIX."reservations_grouppermissions
                WHERE rtid = ".$rtid."
                AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
                "), "extendcount");
            }    
        } else {          
            $usergroup = $user['usergroup'];

            $extendDays = $db->fetch_field($db->query("SELECT extendtime FROM ".TABLE_PREFIX."reservations_grouppermissions
            WHERE rtid = ".$rtid."
            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')            
            "), "extendtime"); 

            $extendLimit = $db->fetch_field($db->query("SELECT extendcount FROM ".TABLE_PREFIX."reservations_grouppermissions
            WHERE rtid = ".$rtid."
            AND (concat(',',usergroups,',') LIKE '%,".$usergroup.",%')
            "), "extendcount");            
        }
    }

    return array(
        "extendDays" => $extendDays,
        "extendLimit" => $extendLimit
    );
}

// Eigene Reservierungen
function reservations_ownreservations($uid) {

    global $mybb, $db, $lang, $templates, $ownreservations;

    if($uid == 0) {
        $ownreservations = "";
        return;
    }
    
    $lang->load('reservations');

    $today = new DateTime('now', new DateTimeZone('Europe/Berlin')); 
    $today->setTime(0, 0, 0);

    $character_array = array_keys(reservations_get_allchars($uid));
    $userids_list = implode(',', $character_array);

    $query_types = $db->query("SELECT rtid, title FROM ".TABLE_PREFIX."reservations_types
    ORDER BY disporder ASC, title ASC
    ");

    // Typen auslesen
    $types = "";
	while ($typ = $db->fetch_array($query_types)) {

        $rtid = "";
        $title = "";
        $rtid = $typ['rtid'];
        $title = $typ['title'];

        $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations
        WHERE uid IN (".$userids_list.")
        AND type = ".$rtid."
        AND lockcheck = 0
        ORDER BY reservation ASC        
        ");

        $reservations = "";
        while($res = $db->fetch_array($query_reservations)) {

            // Leer laufen lassen
            $rid = "";
            $reservation = "";
            $enddate = "";
            $extension = "";
            $endDate = "";    
            $remainingDays = "";
            $reservations_entry_endDate = "";
            $reservations_entry_remainingDays = "";

            // Mit Infos füllen
            $rid = $res['rid'];
            $reservation = $res['reservation'];
            $extension = $res['extension'];

            // Enddatum & verbleibende Tage
            $endDate = new DateTime($res['enddate']);
            $endDate->setTime(0, 0, 0);
            $enddate = $endDate->format('d.m.Y');
            $diff = $endDate->diff($today);
            $remainingDays = (int)$diff->format('%a');
            $reservations_entry_endDate = $lang->sprintf($lang->reservations_entry_endDate, $enddate);
            $reservations_entry_remainingDays = $lang->sprintf($lang->reservations_entry_remainingDays, $remainingDays);

           eval("\$reservations .= \"".$templates->get("reservations_own_reservations")."\";");       
        }

        eval("\$types .= \"".$templates->get("reservations_own_types")."\";");
    }

    $query_reservations = $db->query("SELECT * FROM ".TABLE_PREFIX."reservations
    WHERE uid IN (".$userids_list.")
    AND lockcheck = 1
    ORDER BY reservation ASC        
    ");

    $blocked = "";
    while($res = $db->fetch_array($query_reservations)) {

        // Leer laufen lassen
        $rid = "";
        $reservation = "";
        $lockdate = "";
        $lockDate = "";    
        $remainingDays = "";
        
        // Mit Infos füllen
        $rid = $res['rid'];
        $reservation = $res['reservation'];   

        // Enddatum & verbleibende Tage
        $lockDate = new DateTime($res['lockdate']);
        $lockDate->setTime(0, 0, 0);
        $lockdate = $lockDate->format('d.m.Y');
        $diff = $lockDate->diff($today);
        $remainingDays = (int)$diff->format('%a');
        
        eval("\$blocked .= \"".$templates->get("reservations_own_reservations")."\";");           
    }

    eval("\$ownreservations = \"".$templates->get("reservations_own")."\";");
    return $ownreservations;
}

#####################################################
### DATABASE | SETTINGS | TEMPLATES | STYLESHEETS ###
#####################################################

// DATENBANKTABELLEN
function reservations_database() {

    global $db;

    // Typen
    if (!$db->table_exists("reservations_types")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."reservations_types(
            `rtid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `identification` VARCHAR(500) NOT NULL,
            `title` varchar(500) NOT NULL,
            `description` varchar(500) NOT NULL,
            `disporder` int(10) unsigned NOT NULL DEFAULT '0',
            `gender` int(1) unsigned NOT NULL DEFAULT '0',
            `checkoption` int(1) unsigned NOT NULL DEFAULT '0',
            `checkfield` VARCHAR(500) NOT NULL DEFAULT '',
            `checkname` int(1) unsigned NOT NULL DEFAULT '0',
            `groupsplit` int(1) unsigned NOT NULL DEFAULT '0',
            `condensedgroups` varchar(500) NOT NULL DEFAULT '', 
            PRIMARY KEY(`rtid`),
            KEY `rtid` (`rtid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }

    // Gruppenberechtigungen
    if (!$db->table_exists("reservations_grouppermissions")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."reservations_grouppermissions(
            `rgid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `rtid` int(10) unsigned NOT NULL,
            `usergroups` VARCHAR(200) NOT NULL,
            `name` VARCHAR(500) NOT NULL,
            `disporder` int(10) unsigned NOT NULL DEFAULT '0',
            `maxcount` int(20) unsigned NOT NULL DEFAULT '0',
            `duration` int(20) unsigned NOT NULL DEFAULT '0',
            `extend` int(20) unsigned NOT NULL DEFAULT '0',
            `extendtime` int(20) unsigned NOT NULL DEFAULT '0',
            `extendcount` int(20) unsigned NOT NULL DEFAULT '0',
            `lockcount` int(20) unsigned NOT NULL DEFAULT '0',
            `locknote` int(20) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY(`rgid`),
            KEY `rgid` (`rgid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }
    
    // Reservierungen
    if (!$db->table_exists("reservations")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."reservations(
            `rid` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `uid` int(10) unsigned NOT NULL,
            `playername` VARCHAR(200) NOT NULL,
            `type` int(10) unsigned NOT NULL,
            `gender` VARCHAR(200) NOT NULL,
            `reservation` VARCHAR(500) NOT NULL,
            `enddate` date,
            `extension` int(10) unsigned NOT NULL DEFAULT '0',
            `lockcheck` int(1) unsigned NOT NULL DEFAULT '0',
            `lockdate` date,
            `showindex` int(1) unsigned NOT NULL DEFAULT '1',
            `endlessNote` int(1) unsigned NOT NULL DEFAULT '0',
            `wantedUrl` VARCHAR(1000) NOT NULL,
            PRIMARY KEY(`rid`),
            KEY `rid` (`rid`)
            ) ENGINE=InnoDB ".$db->build_create_table_collation().";"
        );
    }
}

// EINSTELLUNGEN
function reservations_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'reservations_system' => array(
			'title' => 'Reservierungssystem',
            'description' => 'In welcher Form sollen die Reservierungen im Forum erfolgen?<br><b>eigene Seite:</b> Reservierungen befinden sich auf einer eigenen Seite und jede:r User:in/Gast kann sich selbstständig eintragen.<br><b>eigenes Thema:</b> Es gibt ein Reservierungsthema in dem sich User/Gäste per Beitrag melden müssen und Teamies tragen die Reservierungen manuell ein.',
            'optionscode' => 'radio\n1=Seite\n2=Thema',
            'value' => '2', // Default
            'disporder' => 1
		),
        'reservations_thread' => array(
			'title' => 'Reservierungsthema',
            'description' => 'Wie lautet die TID vom Reservierungsthema?',
            'optionscode' => 'numeric',
            'value' => '0', // Default
            'disporder' => 2
		),
        'reservations_team' => array(
            'title' => 'Teamgruppen',
            'description' => 'Welche Gruppen dürfen die Reservierungen verwalten?',
            'optionscode' => 'groupselect',
            'value' => '4', // Default
            'disporder' => 3
        ),
		'reservations_grouppermission' => array(
			'title' => 'Gruppenberechtigung',
            'description' => 'Welche Reservierungsberechtigungen sollen bevorzugt werden, wenn der Account primär und sekundär verschiedene Gruppen mit Berechtigungen besitzt?',
            'optionscode' => 'radio\n0=primär\n1=sekundär',
            'value' => '1', // Default
            'disporder' => 4
		),
		'reservations_playername' => array(
			'title' => 'Spitzname',
            'description' => 'Wie lautet die FID / der Identifikator von dem Profilfeld/Steckbrieffeld für den Spitznamen?<br><b>Hinweis:</b> Bei klassischen Profilfeldern muss eine Zahl eintragen werden. Bei dem Steckbrief-Plugin von Risuena muss der Name/Identifikator des Felds eingetragen werden.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 5
		),
		'reservations_gender' => array(
			'title' => 'Geschlechtsoptionen',
            'description' => 'Welche Optionen soll es geben, wenn bei einem Reservierungstyp die Unterteilung in Geschlechter aktiviert ist?',
            'optionscode' => 'text',
            'value' => 'Weiblich, Männlich, Non-Binär', // Default
            'disporder' => 6
		),
		'reservations_infotext' => array(
			'title' => 'Infotext',
            'description' => 'Hier können Informationen und/oder Regeln rund um die Reservierungen festgehalten werden. Diese werden am Ende im Forum ausgegeben. HTML und BBCode sind aktiviert.',
            'optionscode' => 'textarea',
            'value' => '', // Default
            'disporder' => 7
		),
		'reservations_searchtyp' => array(
			'title' => 'Reservierungen für Gesuche',
            'description' => 'Sollten Reservierungen für Gesuche möglich sein und es ein Textfeld geben soll für den Link zum Gesuch trage hier den Identifikator von dem entsprechendem Typ ein. Wenn nicht benötigt, einfach frei lassen.',
            'optionscode' => 'text',
            'value' => '', // Default
            'disporder' => 8
		),
        'reservations_tab' => array(
            'title' => 'Tabs',
            'description' => 'Sollen die Reservierungen in Tabs (horizontal) dargestellt werden?',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 9
        ),
        'reservations_blocked' => array(
            'title' => 'Anzeige gesperrter Reservierungen',
            'description' => 'Sollen gesperrte Reservierungen für das Team direkt auf der Seite/im Thread angezeigt werden? Sonst gibt es eine Auflistung im ModCP. Im ACP ist diese immer verfügbar.',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 10
        ),
        'reservations_reminder' => array(
            'title' => 'Index Anzeige',
            'description' => 'Wie viele Tage vor Ablauf einer Reservierung bekommt man einen Banner als Erinnerung? 0 deaktiviert diese Funktion.',
            'optionscode' => 'numeric',
            'value' => '5', // Default
            'disporder' => 11
        ),
        'reservations_global' => array(
            'title' => 'Anzeige eigener Reservierungen',
            'description' => 'Soll es eine globale Variable geben, die die eigenen Reservierungen ausliest?<br>{$mybb->user[\'ownreservations\']}',
            'optionscode' => 'yesno',
            'value' => '0', // Default
            'disporder' => 12
        ),
		'reservations_lists_nav' => array(
			'title' => "Listen PHP",
			'description' => "Wie heißt die Hauptseite der Listen-Seite? Dies dient zur Ergänzung der Navigation. Falls nicht gewünscht einfach leer lassen.",
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 13
		),
		'reservations_lists_menu' => array(
			'title' => 'Listen Menü',
			'description' => 'Soll über die Variable {$lists_menu} das Menü der Listen aufgerufen werden?<br>Wenn ja, muss noch angegeben werden, ob eine eigene PHP-Datei oder das Automatische Listen-Plugin von sparks fly genutzt?',
			'optionscode' => 'select\n0=eigene Listen/PHP-Datei\n1=Automatische Listen-Plugin\n2=keine Menü-Anzeige',
			'value' => '0', // Default
			'disporder' => 14
		),
        'reservations_lists_menu_tpl' => array(
            'title' => 'Listen Menü Template',
            'description' => 'Damit das Listen Menü richtig angezeigt werden kann, muss hier einmal der Name von dem Tpl von dem Listen-Menü angegeben werden.',
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 15
        ),
    );

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'reservations' LIMIT 1;"), "gid");

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
function reservations_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'reservations_banner_reminder',
        'template'	=> $db->escape_string('<div class="reservations_banner red_alert">{$bannertext} <span id="reservationsBanner-{$rid}" class="reservationsBanner" style="cursor: pointer;" onclick="hideReservationBanner({$rid})">✕</span></div>
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/reservations/reservations_banner.js"></script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_banner_team',
        'template'	=> $db->escape_string('<div class="red_alert">{$bannertext}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_blocked_page',
        'template'	=> $db->escape_string('<br>
        <div class="reservations_page tborder">
        <div class="reservations-headline thead"><b>{$lang->reservations_blocked}</b></div>
        <div class="reservations_page-container trow1">
        <div class="reservations_blockedreservations-types">
			{$types}
		</div>
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_blocked_reservations',
        'template'	=> $db->escape_string('<div class="reservations_entry">
        <b>{$reservation}</b> {$byUser}<br>
        {$reservations_entry_endDate} » {$reservations_entry_remainingDays}
        <a href="misc.php?action=reservationsBlocked_delete&rid={$rid}&return={$return}" onclick="return confirm(\'{$deleteNotice}\');" {$deleteLink}>{$lang->reservations_blocked_delete}</a>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_blocked_showthread',
        'template'	=> $db->escape_string('<tr>
        <td class="trow1">
		<div class="reservations_headline thead">{$lang->reservations_blocked}</div>
		<div class="reservations_blockedreservations-types">
			{$types}
		</div>
        </td>
        </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_blocked_types',
        'template'	=> $db->escape_string('<div class="reservations_blockedreservationsBit">
        <div class="reservations_blockedreservations-title trow2">{$title}</div>
        {$reservations}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_modcp',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->reservations_modcp}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		<table width="100%" border="0" align="center">
			<tr>
				{$modcp_nav}
				<td valign="top">
					<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
						<tr>
							<td class="thead"><strong>{$lang->reservations_modcp}</strong></td>
						</tr>
						<tr>
							<td class="trow1">
								<div class="reservations_blockedreservations-types">{$types}</div>
							</td>
						</tr>
					</table>
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
        'title'		=> 'reservations_modcp_nav',
        'template'	=> $db->escape_string('<tr><td class="trow1 smalltext"><a href="modcp.php?action=reservations" class="modcp_nav_item modcp_nav_modqueue">{$lang->reservations_modcp}</td></tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_entry',
        'template'	=> $db->escape_string('<div class="reservations_entry">
        <div class="reservations_entry-res"><b>{$reservation}</b> {$byUser} <span class="smalltext">{$optionExtend} {$optionDelete}</span></div>
        <div class="reservations_entry-info">{$reservations_entry_endDate} » {$reservations_entry_remainingDays}<br>{$wanted}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_tabs',
        'template'	=> $db->escape_string('<div class="reservationTab">{$tabMenu}</div> {$tabContent}
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/reservations/reservations_tabs.js"></script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_tabs_content',
        'template'	=> $db->escape_string('<div id="{$identification}" class="reservationTabcontent">
        <div class="reservations_headline thead">{$headline}</div>
        <div class="{$reservationsPage_typesClass}">{$reservations_types_bit}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_tabs_menu',
        'template'	=> $db->escape_string('<div class="reservationTablinks" onclick="openReservationTab(event, \'{$identification}\')" {$defaultTab}>{$title}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_types',
        'template'	=> $db->escape_string('<div class="reservations_types">
        <div class="reservations_headline thead">{$headline}</div>
        <div class="{$reservationsPage_typesClass}">{$reservations_types_bit}</div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_types_gender',
        'template'	=> $db->escape_string('<div class="reservations-reservation">
        <div class="reservations-genderline trow2">{$gendername}</div>
        {$reservations_user}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_output_types_groups',
        'template'	=> $db->escape_string('<div class="reservations-subline tcat">{$groupname}</div>
        <div class="{$genderflex}">{$reservations_bit}</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_own',
        'template'	=> $db->escape_string('<div class="reservations_ownreservations tborder">
        <div class="reservations_headline thead">{$lang->reservations_own}</div>
        <div class="reservations_ownreservations-types trow1">
		{$types}
		<div class="reservations_ownreservationsBit">
			<div class="reservations_ownreservations-title trow2">{$lang->reservations_own_blocked}</div>
			{$blocked}
		</div>
        </div>
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_own_reservations',
        'template'	=> $db->escape_string('<div class="reservations_entry">
        <b>{$reservation}</b><br>
        {$reservations_entry_endDate} » {$reservations_entry_remainingDays}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_own_types',
        'template'	=> $db->escape_string('<div class="reservations_ownreservationsBit">
        <div class="reservations_ownreservations-title trow2">{$title}</div>
        {$reservations}
        </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_page',
        'template'	=> $db->escape_string('<html>
        <head>
		<title>{$mybb->settings[\'bbname\']} - {$lang->reservations}</title>
		{$headerinclude}
        </head>
        <body>
		{$header}
		{$reservations_error}
		<div class="reservations_page tborder">
			<div class="reservations-headline thead"><b>{$lang->reservations}</b></div>
			<div class="reservations-desc trow1">{$infotext}</div>
			{$reservations_form}
			<div class="reservations_page-container trow1">
				{$reservations_output}
			</div>
		</div>
		{$reservations_blocked}
		{$footer}
        </body>
        </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_page_formular',
        'template'	=> $db->escape_string('<div class="reservations_formularPage trow1">
        <form action="misc.php" method="post">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

		<div class="reservations_formular-input">
			<div class="reservations_formular-label">{$lang->reservations_form_type}</div>
			<div class="reservations_formular-select">
				<select id="type" name="type">
					<option value="">{$lang->reservations_form_type_select}</option>
					{$type_select}
				</select>
			</div>
		</div>

		<div class="reservations_formular-input">
			<div class="reservations_formular-label">{$lang->reservations_form_reservation}</div>
			<div class="reservations_formular-field">
				<input type="text" name="reservation" id="reservation" placeholder="{$lang->reservations_form_reservation_placeholder}" value="{$reservationInput}" class="textbox">
				<div class="smalltext" id="typeDescription">{$description}</div>
			</div>
		</div>

		<div class="reservations_formular-input" id="genderFieldWrapper" style="display: none;">
			<div class="reservations_formular-label">{$lang->reservations_form_gender}</div>
			<div class="reservations_formular-select">
				<select id="gender" name="gender">
					<option value="">{$lang->reservations_form_gender_select}</option>
					{$gender_select}
				</select>
			</div>
		</div>

		<div class="reservations_formular-input" id="wantedUrlFieldWrapper" style="display: none;">
			<div class="reservations_formular-label">Link zum Gesuch:</div>
			<div class="reservations_formular-field">
				<input type="url" name="wantedUrl" id="wantedUrl" placeholder="https://" value="{$wantedUrlInput}" pattern="https://.*" class="textbox" />
			</div>
		</div>

		<div class="reservations_formular-input" {$playernameDisplay}>
			<div class="reservations_formular-label">{$lang->reservations_form_player_misc}</div>
			<div class="reservations_formular-field">{$playernameInput}</div>
		</div>

		<div align="center">
			<input type="hidden" name="action" value="do_reservations" />
			<input type="hidden" name="uid" value="{$activeUID}" />
			<input type="submit" class="button" name="submit" value="{$lang->reservations_form_button}" />
		</div>
        </form>
        </div>
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/reservations/reservations_form.js"></script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_showthread',
        'template'	=> $db->escape_string('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder tfixed clear">
        <tr>
		<td class="thead"><strong>{$lang->reservations}</strong></td>
        </tr>
        {$reservations_error}
        <tr>
		<td class="trow1">
			<div class="reservations_showthread">
				<div class="reservations_showthread-guide">
					<div class="reservations-desc">{$infotext}</div>
					{$reservations_form}
				</div>
				<div class="reservations_showthread-output">
					{$reservations_output}
				</div>
			</div>
		</td>
        </tr>
        {$reservations_blocked}
        </table>
        <br>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'reservations_showthread_formular',
        'template'	=> $db->escape_string('<div class="reservations_formularShowthread"> 
        <form action="showthread.php?tid={$tid}" method="post">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

		<div class="reservations_formular-input">
			<div class="reservations_formular-label">{$lang->reservations_form_type}</div>
			<div class="reservations_formular-select">
				<select id="type" name="type">
					<option value="">{$lang->reservations_form_type_select}</option>
					{$type_select}
				</select>
			</div>
		</div>

		<div class="reservations_formular-input">
			<div class="reservations_formular-label">{$lang->reservations_form_reservation}</div>
			<div class="reservations_formular-field">
				<input type="text" name="reservation" id="reservation" value="{$reservationInput}" placeholder="{$lang->reservations_form_reservation_placeholder}" class="textbox">
				<div class="smalltext" id="typeDescription">{$description}</div>
			</div>
		</div>

		<div class="reservations_formular-input" id="genderFieldWrapper" style="display: none;">
			<div class="reservations_formular-label">{$lang->reservations_form_gender}</div>
			<div class="reservations_formular-select">
				<select id="gender" name="gender">
					<option value="">{$lang->reservations_form_gender_select}</option>
					{$gender_select}
				</select>
			</div>
		</div>

		<div class="reservations_formular-input" id="wantedUrlFieldWrapper" style="display: none;">
			<div class="reservations_formular-label">Link zum Gesuch:</div>
			<div class="reservations_formular-field">
				<input type="url" name="wantedUrl" id="wantedUrl" placeholder="https://" value="{$wantedUrlInput}" pattern="https://.*" class="textbox" />
			</div>
		</div>

		<div class="reservations_formular-input">
			<div class="reservations_formular-label">{$lang->reservations_form_player_showthread}</div>
			<div class="reservations_formular-field">
				<input type="text" class="textbox" name="playername" id="playername" value="{$playername}" />
			</div>
		</div>

		<div align="center">
			<input type="hidden" name="tid" value="{$tid}">
			<input type="submit" class="button" name="reservations_submit" value="{$lang->reservations_form_button}" />
		</div>
        </form>
        </div>
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/reservations/reservations_form.js"></script>
        <link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css?ver=1807">
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1806"></script>
        <script type="text/javascript" src="{$mybb->asset_url}/jscripts/reservations/reservations_selectname.js"></script>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

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
function reservations_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'reservations.css',
		'tid' => 1,
		'attachedto' => '',
		'stylesheet' =>	'.reservations-desc {
        text-align: justify;
        padding: 20px 40px;
        }

        .reservations_formularPage form {
        width: 30%;
        margin: 10px auto;
        }

        .reservations_formularShowthread form {
        width: 84%;
        margin: 10px auto;
        }

        .reservations_formular-label {
        font-weight: bold;
        width: 100%;
        }

        .reservations_formular-input {
        margin-bottom: 8px;
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        justify-content: space-between;
        }

        .reservations_formular-select, 
        .reservations_formular-select select,
        .reservations_formular-field,
        .reservations_formular-field input.textbox,
        .reservations_formular-field .select2-container {
        width: 100%;
        box-sizing: border-box;
        }

        .reservationsSingle, 
        .reservations-genderflex {
        display: flex;
        flex-wrap: nowrap;
        justify-content: flex-start;
        }

        .reservations-reservation {
        width: 100%;
        }

        .reservations-genderline {
        font-weight: bold;
        padding: 3px;
        }

        .reservations_entry {
        padding: 5px;
        }

        .reservations_types {
        margin-bottom: 10px;
        }

        /* SHOWTHREAD */
        .reservations_showthread {
        display: flex;
        flex-wrap: nowrap;
        gap: 10px;
        align-items: flex-start;
        }

        .reservations_showthread-guide {
        width: 40%;
        }

        .reservations_showthread-output {
        width: 60%;
        }

        .reservations_showthread-desc {
        text-align: justify;
        padding: 20px 40px;
        }

        /* TABS */
        .reservationTab {
        display: flex;
        flex-wrap: nowrap;
        }

        .reservationTablinks {
        padding: 10px;
        transition: 0.3s;
        cursor: pointer;
        }

        .reservationTablinks:hover {
        background-color: #ddd;
        }

        .reservationTablinks.active {
        background-color: #ccc;
        font-weight: bold;
        }

        .reservationTabcontent {
        display: none;
        }

        .reservations_ownreservations {
        margin-bottom: 20px;
        }

        .reservations_ownreservations-types,
        .reservations_blockedreservations-types {
        display: flex;
        }

        .reservations_ownreservationsBit,
        .reservations_blockedreservationsBit{
        width: 100%;
        }

        .reservations_ownreservations-title,
        .reservations_blockedreservations-title{
        font-weight: bold;
        padding: 3px;
        }

        .reservationsBanner {
        font-size: 14px;
        margin-top: -2px;
        float: right;
        }',
		'cachefile' => 'reservations.css',
		'lastmodified' => TIME_NOW
	);

    return $css;
}

// STYLESHEET UPDATE
function reservations_stylesheet_update() {

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
function reservations_is_updated(){

    global $db, $mybb;

	if ($db->table_exists("reservations")) {
        return true;
    }
    return false;
}
