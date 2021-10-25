<?php 

use Illuminate\Database\Capsule\Manager as Capsule;

function hook_ticket_status_active($vars) 
{
   $ticketsactive = Capsule::table('tbltickets')
               ->whereIn('status', array('Answered'))
               ->count();
    $ticketsactive = $ticketsactive + Capsule::table('tbltickets')
               ->whereIn('status', array('In Progress'))
               ->count();
    $ticketsactive = $ticketsactive + Capsule::table('tbltickets')
               ->whereIn('status', array('On Hold'))
               ->count();
    $ticketsactive = $ticketsactive + Capsule::table('tbltickets')
               ->whereIn('status', array('Escalated'))
               ->count(); 
    $ticketsactive = $ticketsactive + Capsule::table('tbltickets')
               ->whereIn('status', array('Open'))
               ->count();
               
   return array("ticketsactive" => $ticketsactive);
}
add_hook("AdminAreaPage", 1, "hook_ticket_status_active");
?>
