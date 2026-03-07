<?php
function task_reservations($task){

    global $db, $mybb;

    // abgelaufene Reservierungen
    $query_reservations = $db->query("SELECT uid, type, rid  FROM ".TABLE_PREFIX."reservations
    WHERE enddate < CURDATE()
    AND lockcheck = 0
    ");

    while($res = $db->fetch_array($query_reservations)) {
        
        $uid = $res['uid'];
        $rtid = $res['type'];
        $rid = $res['rid'];

        // Gäste => einfach löschen
        if ($uid == 0 || empty(get_user($uid))) {
            $db->delete_query("reservations", "rid = ".$rid);
        } else {

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
                $db->delete_query('reservations', "rid = ".$rid);
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
    }

    // gesperrte abgelaufene Reservierungen
    $query_blocked = $db->query("SELECT rid FROM ".TABLE_PREFIX."reservations
    WHERE lockdate < CURDATE()
    AND lockcheck = 1
    ");

    while($block = $db->fetch_array($query_blocked)) {
        $db->delete_query("reservations", "rid = ".$block['rid']);
    }

    add_task_log($task, "Reservierungs-Task erfolgreich ausgeführt.");
}