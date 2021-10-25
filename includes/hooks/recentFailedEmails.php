<?php
// https://whmcs.community/topic/296515-hook-to-show-recent-imported-ticket-failures-on-support-tickets-page/
// version 1.2

use Illuminate\Database\Capsule\Manager as Capsule; 

add_hook('AdminSupportTicketPagePreTickets', 1, function($vars) {
	
	$output = "<div id='recently-blocked'><h2>Recently Blocked Messages   <small><i class='far fa-eye'></i> <a href='systemmailimportlog.php' target='_blank'>SEE ALL</a></small></h2>
		<table id='sortabletbl1' class='datatable' style='width:100%'>
		<tr><th>Date</th><th>Name/Email</th><th>Subject</th><th>Status</th><th></th></tr>";
	
	foreach (Capsule::table('tblticketmaillog')->where('status', 'like', 'Blocked%')->where('email', 'not like', 'mailer-daemon%')->orderBy('id', 'desc')->limit(10)->get() as $msg){

		/* Name */
		if ($msg->name === $msg->email) $name = $msg->name;
		else $name = "{$msg->name} <{$msg->email}>";
		
		/* Date */
		$today = new DateTime();
		$msg_date = new DateTime($msg->date);
		$interval = $today->diff($msg_date);
		$date_interval = abs($interval->format('%R%a'));
		
		if ($date_interval == 0) $date_interval = 'Today';
		else if ($date_interval == 1) $date_interval = 'Yesterday';
		else $date_interval .= ' days ago';

		$output .= "
		<tr>
			<td>$date_interval</td><td>$name</td><td>{$msg->subject}</td><td>{$msg->status}</td>
			<td><button class='btn' style='padding:1px 5px;' onclick=\"window.open('/" . $GLOBALS['customadminpath'] . "/systemmailimportlog.php?display=true&id={$msg->id}','','width=650,height=400,scrollbars=yes');return false\">VIEW</button></td>
		</tr>";
		
	}
	
	return  "$output</table><br /></div>
	<script>
        jQuery(document).ready(function($){ /* Move to bottom of page */
        $('#recently-blocked').appendTo('#contentarea > div:first-child');
        });
    </script>";
	
});