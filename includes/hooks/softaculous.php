<?php

//////////////////////////////////////////////////////////////
//===========================================================
// software_theme.php
//===========================================================
// SOFTACULOUS 
// Version : 1.1
// Inspired by the DESIRE to be the BEST OF ALL
// ----------------------------------------------------------
// Started by: Alons
// Date:       10th Jan 2009
// Time:       21:00 hrs
// Site:       http://www.softaculous.com/ (SOFTACULOUS)
// ----------------------------------------------------------
// Please Read the Terms of use at http://www.softaculous.com
// ----------------------------------------------------------
//===========================================================
// (c)Softaculous Inc.
//===========================================================
//////////////////////////////////////////////////////////////

define('SOFTACULOUS', 1);
define('SOFTMODULEVER', 1);

function s_fn($f){
	global $softaculous_conf;
	
	if(empty($softaculous_conf['fields'][$f])){
		$r = $f;
	}else{
		$r = $softaculous_conf['fields'][$f];
	}
	
	return $r;	
}

function softaculous_scripts(){

global $softaculous_scripts, $add_softaculous_scripts;
	
	if(!empty($softaculous_scripts)){
		return $softaculous_scripts;
	}
	
	// Set the curl parameters.
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://api.softaculous.com/scripts.php?in=serialize');

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'RC4-SHA:RC4-MD5'); // This is becuase some servers cannot access https without this

	// Get response from the server
	$resp = curl_exec($ch);
	$scripts = unserialize($resp);
	
	if(!is_array($scripts)){
		report_soft_message('Could not download list of scripts. '.curl_error($ch));
	}
	
	$softaculous_scripts = $scripts;
	
	if(is_array($add_softaculous_scripts)){
		foreach($add_softaculous_scripts as $k => $v){
			$softaculous_scripts[$k] = $v;
		}
	}
	
	return $softaculous_scripts;
	
}

// Report Success
function report_soft_message($message){
	global $softaculous_conf;
	
	$message = 'Softaculous : '.$message;
	
	if(!empty($softaculous_conf['echo_success'])){
		echo $message;
	}
	
	// Log Activity in WHMCS	
	if(!empty($softaculous_conf['logActivity'])){
		logActivity($message);
	}
}

// Reports the error
function report_soft_error($err){
	global $softaculous_conf;
	
	$err = 'Softaculous : '.$err;
	
	if(!empty($softaculous_conf['debug_echo'])){
		echo $err.'<br>';
	}
	
	// Write to the file
	if(!empty($softaculous_conf['debug_file'])){
		$fp = @fopen($softaculous_conf['debug_file'], 'a');
		if($fp){
			if(@fwrite($fp, $err."\n") === FALSE){
				// Wrote to the file
			}else{				
				@fclose($fp);				
			}
		}
	}
	
	if(!empty($softaculous_conf['log_error'])){
		error_log($err);
	}
	
	// Log Activity in WHMCS	
	if(!empty($softaculous_conf['logActivity'])){
		logActivity($err);
	}
}


class Soft_Install{

	// The Login URL
	var $login = '';
	
	var $debug = 0;
	
	var $cookie;

	// THE POST DATA
	var $data = array();
	
	function install($sid){
		
		@define('SOFTACULOUS', 1);
		
		$scripts = softaculous_scripts();
		
		if(empty($scripts[$sid])){
			return 'List of scripts not loaded. Aborting Installation attempt!';
		}
		
		// Add a Question mark if necessary
		if(substr_count($this->login, '?') < 1){
			$this->login = $this->login.'?';
		}
		
		// Login PAGE
		if($scripts[$sid]['type'] == 'js'){
			$this->login = $this->login.'act=js&soft='.$sid;
		}elseif($scripts[$sid]['type'] == 'perl'){
			$this->login = $this->login.'act=perl&soft='.$sid;
		}elseif($scripts[$sid]['type'] == 'java'){
			$this->login = $this->login.'act=java&soft='.$sid;
		}else{
			$this->login = $this->login.'act=software&soft='.$sid;
		}
		
		// Give an Overwrite signal for existing files and folders
		if(!isset($this->data['overwrite_existing'])){
			$this->data['overwrite_existing'] = 1;
		}

		$this->login = $this->login.'&autoinstall='.rawurlencode(base64_encode(serialize($this->data)));
	
		if(!empty($this->debug)){
			return $this->data;
		}

		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->login);
	
		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    	curl_setopt($ch, CURLOPT_HEADER, FALSE);
		
		// Is there a Cookie
		if(!empty($this->cookie)){
			curl_setopt($ch, CURLOPT_COOKIESESSION, true);
			curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
		}
	
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
		// Get response from the server.
		$resp = curl_exec($ch);
		
		// Did we reach out to that place ?
		if($resp === false){
			report_soft_error('Installation not completed. cURL Error : '.curl_error($ch));
		}
		
		curl_close($ch);
		
		// Was there any error ?
		if($resp != 'installed') {
			return $resp;
		}
		
		return 'installed';
		
	}

}

// cPanel installation function
function soft_cpanel($par){
	
	global $softaculous_conf;
	// Initialize our class
	$new = new Soft_Install();
	
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://'.$par['serverip'].':2083/login/');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('user' => $par['username'],
			'pass' => $par['password'],
			'goto_uri' => '/');
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	$no_follow_location = 0;
	if(function_exists('ini_get')){
		$open_basedir = ini_get('open_basedir'); // Followlocation does not work if open_basedir is enabled
		if(!empty($open_basedir)){
			$no_follow_location = 1;
		}
	}

	if(empty($no_follow_location)){		
		// Follow redirects
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);		
	}
	
	//curl_setopt($ch, CURLOPT_COOKIEJAR, '-');
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	// Get the cpsess and path to frontend theme
	$curl_info = curl_getinfo($ch);

	if(!empty($curl_info['redirect_url'])){
		$parsed = parse_url($curl_info['redirect_url']);
	}else{
		$parsed = parse_url($curl_info['url']);
	}
	
	$path = trim(dirname($parsed['path']));
	$path = ($path{0} == '/' ? $path : '/'.$path);
		
	curl_close($ch);
	
	// Did we login ?
	if(empty($path)){
		report_soft_error('Could not determine the location of the Softaculous on the remote server. There could be a firewall preventing access.');
		return false;
	}
	
	// Make the Login system
	$new->login = 'https://'.rawurlencode($par['username']) . ':' . rawurlencode($par['password']) . '@' . $par['serverip'] . ':2083'.$path.'/softaculous/index.live.php';
	
	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);

	//$new->data['protocol'] = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	//print_r($softaculous_conf);
	//$new->data['softdomain'] = $par['domain'];
	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $par['serverip'].'/~'.$par['username'] : (!empty($softaculous_conf['domain_prefix']) ? $softaculous_conf['domain_prefix'].'.' : '').$par['domain']); // OPTIONAL - By default will install on primary domain name or sels on IP/~user
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
		
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct. Script Name : '.$ins_script);
		return false;
	}
	
	$res = $new->install($sid); // Will install the script
	$res = trim($res);
	if(preg_match('/installed/is',$res)){
		report_soft_message('Script Installed successfully');
		return true;
	}else{
		report_soft_error('The following errors occured : 
'.$res);
		return false;
	}

}

function soft_directadmin($par){
	global $softaculous_conf;
	
	$new = new Soft_Install();
	
	if(!empty($par['serverhostname'])){
		$host = $par['serverhostname'];
	}else{
		$host = $par['serverip'];
	}
	
	// This is just a check if the admin has not set da_proto do we need to use https://
	if(empty($softaculous_conf['da_proto'])){
	
		// Login and get the cookies
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'http://'.$host.':2222/CMD_LOGIN');
		curl_setopt($ch, CURLOPT_VERBOSE, 1);

		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		
		$post = array('username' => $par['username'],
				'password' => $par['password'],
				'referer' => '/');
		
		curl_setopt($ch, CURLOPT_POST, 1);
		$nvpreq = http_build_query($post);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
		
		// Check the Header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		// Get response from the server.
		$resp = curl_exec($ch);
		
		// This is the response from Directadmin to use https for control panel
		if(trim($resp) == 'use https'){
			$softaculous_conf['da_proto'] = 'https://';
		}
		
		curl_close($ch);
		
	}
	
	$protocol = (empty($softaculous_conf['da_proto']) ? 'http://' : $softaculous_conf['da_proto']);
	
	$new->login = $protocol.$host.':'.(!empty($softaculous_conf['da_port']) ? $softaculous_conf['da_port'] : '2222').'/CMD_PLUGINS/softaculous/index.raw';
	
	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);

	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $host.'/~'.$par['username'] : $par['domain']); // OPTIONAL - By default will install on primary domain name or sels on IP/~user
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));	
	
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $protocol.$host.':2222/CMD_LOGIN');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('username' => $par['username'],
			'password' => $par['password'],
			'referer' => '/');
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
	
	$resp = explode("\n", $resp);
	
	// Find the cookies
	foreach($resp as $k => $v){
		if(preg_match('/^'.preg_quote('set-cookie:', '/').'(.*?)$/is', $v, $mat)){
			$new->cookie= trim($mat[1]);
		}
	}
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
	
	// Add a Question mark if necessary
	if(substr_count($new->login, '?') < 1){
		$new->login = $new->login.'?';
	}
	
	// Login PAGE
	if($scripts[$sid]['type'] == 'js'){
		$new->login = $new->login.'act=js&soft='.$sid;
	}elseif($scripts[$sid]['type'] == 'perl'){
		$new->login = $new->login.'act=perl&soft='.$sid;
	}else{
		$new->login = $new->login.'act=software&soft='.$sid;
	}
	
	// Give an Overwrite signal for existing files and folders
	if(!isset($new->data['overwrite_existing'])){
		$new->data['overwrite_existing'] = 1;
	}

	$new->login = $new->login.'&autoinstall='.rawurlencode(base64_encode(serialize($new->data)));

	if(!empty($new->debug)){
		return $new->data;
	}
	
	$resp = '';
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $new->login);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		
	// Is there a Cookie
	if(!empty($new->cookie)){
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIE, $new->cookie);
	}
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	//DA has enabled referrer by default hence we need to pass it.
	curl_setopt($ch, CURLOPT_REFERER, $protocol.$host.':2222/');
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
		
	if($resp == 'installed'){
		report_soft_message('Script Installed successfully');
		return true;
	}else{
		report_soft_error('The following errors occured : 
'.$resp);
		return false;
	}

}

// PLESK starts
function soft_plesk($par){
	
	global $softaculous_conf;
	$new = new Soft_Install();

	//http://download1.parallels.com/Plesk/Plesk8.0/Doc/plesk-8-api-rpc/28727.htm
	$new->login = 'https://'.$par['serverip'].':8443/modules/softaculous/index.php';
	
	//Only one installation was made per Client because only one customer/client account is created in Plesk. When the second order was placed, new login details were generated in WHMCS, but new customer/client was not created, only the new domain was created for the Customer, hence login failed.
	$clientid = $par['userid'];	
	$command = 'GetClientsProducts';
	$postData = array(
		'clientid' => $clientid,
		'stats' => true,
	);
	$results = localAPI($command, $postData);

	foreach($results['products']['product'] as $order){		
		if($order['serverip'] == $par['serverip'] && $order['status'] == 'Active'){
			$par['username'] = (!empty($order['username']) ? $order['username'] : $par['username']);
			$par['password'] = (!empty($order['password']) ? $order['password'] : $par['password']);
			
			//We require the login details of the current user's current product's first order only.
			break;
		}
	}
	
	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);
	
	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $par['serverip'].'/~'.$par['username'] : (!empty($softaculous_conf['domain_prefix']) ? $softaculous_conf['domain_prefix'].'.' : '').$par['domain']); // OPTIONAL - By default will install on primary domain name or sels on IP/~user
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://'.$par['serverip'].':8443/login_up.php3');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('login_name' => $par['username'],
			'passwd' => $par['password']);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	curl_close($ch);
	
	$resp = explode("\n", $resp);
	
	// Find the cookies
	foreach($resp as $k => $v){
		if(preg_match('/^'.preg_quote('set-cookie:', '/').'(.*?)$/is', $v, $mat)){
			$new->cookie = $mat[1];
		}
	}

	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
	
	$res = $new->install($sid); // Will install the script
	$res = trim($res);
	
	if($res == 'installed'){
		return true;
	}else{
		return false;
	}

}
// PLESK ends

// Webuzo starts
function soft_webuzo($par){
	
	global $softaculous_conf;
	$new = new Soft_Install();
	
	$new->login = 'https://'.$par['username'].':'.$par['password'].'@'.$par['serverip'].':2003';
	
	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);
	
	$new->data['softdomain'] = $par['domain'];
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
		
	$res = $new->install($sid); // Will install the script
	$res = trim($res);
	
	if($res == 'installed'){
		return true;
	}else{
		return false;
	}

}
// Webuzo ends

// Interworx Starts
function soft_interworx($par){
	global $softaculous_conf;
	
	$new = new Soft_Install();
	
	$protocol = (empty($softaculous_conf['iwx_proto']) ? 'https://' : $softaculous_conf['iwx_proto']);
	
	$new->login = $protocol.$par['serverip'].':2443/siteworx/softaculous';

	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);
	
	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $par['serverip'].'/~'.$par['username'] : (!empty($softaculous_conf['domain_prefix']) ? $softaculous_conf['domain_prefix'].'.' : '').$par['domain']); // OPTIONAL - By default will install on primary domain name or sels on IP/~user
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));
	
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $protocol.$par['serverip'].':2443/siteworx/index?action=login');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('email' => $par['clientsdetails']['email'],
			'password' => $par['password'],
			'domain' => $par['domain']);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
	
	$resp = explode("\n", $resp);
	
	// Find the cookies
	foreach($resp as $k => $v){
		if(preg_match('/^'.preg_quote('set-cookie:', '/').'(.*?)$/is', $v, $mat)){
			$new->cookie= trim($mat[1]);
		}
	}
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
	
	// Add a Question mark if necessary
	if(substr_count($new->login, '?') < 1){
		$new->login = $new->login.'?';
	}
	
	// Login PAGE
	if($scripts[$sid]['type'] == 'js'){
		$new->login = $new->login.'act=js&soft='.$sid;
	}elseif($scripts[$sid]['type'] == 'perl'){
		$new->login = $new->login.'act=perl&soft='.$sid;
	}else{
		$new->login = $new->login.'act=software&soft='.$sid;
	}
	
	// Give an Overwrite signal for existing files and folders
	if(!isset($new->data['overwrite_existing'])){
		$new->data['overwrite_existing'] = 1;
	}

	$new->login = $new->login.'&autoinstall='.rawurlencode(base64_encode(serialize($new->data)));

	if(!empty($new->debug)){
		return $new->data;
	}
	
	$resp = '';
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $new->login);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	// Is there a Cookie
	if(!empty($new->cookie)){
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIE, $new->cookie);
	}
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = trim(curl_exec($ch));
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
		
	if($resp == 'installed'){
		report_soft_message('Script Installed successfully');
		return true;
	}else{
		report_soft_error('The following errors occured : '.$resp);
		return false;
	}

}

// CWP installation function
function soft_cwp($par){
	
	global $softaculous_conf;
	
	$new = new Soft_Install();
	
	$protocol = (empty($softaculous_conf['cwp_proto']) ? 'http://' : $softaculous_conf['cwp_proto']);
	
	if(empty($par['serverip'])){
		$host = $par['serverhostname'];
	}else{
		$host = $par['serverip'];
	}
	
	if(substr($protocol, 0, 5) == 'https'){
		$port = '2031';
	}else{
		$port = '2030';
	}
	
	$new->login = $protocol.$host.':'.$port.'/softaculous/index.php/';
	
	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);

	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $host.'/~'.$par['username'] : (!empty($softaculous_conf['domain_prefix']) ? $softaculous_conf['domain_prefix'].'.' : '').$par['domain']); // OPTIONAL - By default will install on primary domain name or sels on IP/~user
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));	
	
	// Login and get the cookies
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $protocol.$host.':'.$port.'/login.php');
	
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('username' => $par['username'],
			'password' => $par['password'],
			'commit' => 'Login');
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
	
	$resp = explode("\n", $resp);
	
	// Find the cookies
	foreach($resp as $k => $v){
		if(preg_match('/^'.preg_quote('set-cookie:', '/').'(.*?)$/is', $v, $mat)){
			$new->cookie= trim($mat[1]);
		}
	}
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
	
	// Add a Question mark if necessary
	if(substr_count($new->login, '?') < 1){
		$new->login = $new->login.'?';
	}
	
	// Login PAGE
	if($scripts[$sid]['type'] == 'js'){
		$new->login = $new->login.'act=js&soft='.$sid;
	}elseif($scripts[$sid]['type'] == 'perl'){
		$new->login = $new->login.'act=perl&soft='.$sid;
	}else{
		$new->login = $new->login.'act=software&soft='.$sid;
	}
	
	// Give an Overwrite signal for existing files and folders
	if(!isset($new->data['overwrite_existing'])){
		$new->data['overwrite_existing'] = 1;
	}

	$new->login = $new->login.'&autoinstall='.rawurlencode(base64_encode(serialize($new->data)));

	if(!empty($new->debug)){
		return $new->data;
	}
	
	$resp = '';
	// Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $new->login);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		
	// Is there a Cookie
	if(!empty($new->cookie)){
		curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_COOKIE, $new->cookie);
	}
	
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
		
	if($resp == 'installed'){
		report_soft_message('Script Installed successfully');
		return true;
	}else{
		report_soft_error('The following errors occured : 
'.$resp);
		return false;
	}


}

//For vesta
function soft_vestacp($par){
	global $softaculous_conf;
	
	$new = new Soft_Install();
	
	$protocol = (empty($softaculous_conf['vesta_proto']) ? 'https://' : $softaculous_conf['vesta_proto']);
	
	$new->login = $protocol.$par['serverhostname'].':8083/softaculous/';

	$protocols = array();
	$protocols['http://'] = 1;
	$protocols['http://www.'] = 2;
	$protocols['https://'] = 3;
	$protocols['https://www.'] = 4;
	
	$softproto = (!empty($softaculous_conf['protocol']) ? $softaculous_conf['protocol'] : '');
	$new->data['softproto'] = (!in_array($softproto, array_keys($protocols)) ? $protocols['http://'] : $protocols[$softproto]);
	
	$new->data['softdomain'] = (!empty($softaculous_conf['user_mod_dir']) ? $par['serverip'].'/~'.$par['username'] : (!empty($softaculous_conf['domain_prefix']) ? $softaculous_conf['domain_prefix'].'.' : '').$par['domain']); // OPTIONAL - By default 
	//will install on primary domain name or sels on IP/~user
	$new->data['softdomain'] = strtolower($new->data['softdomain']);
	$new->data['softdirectory'] = $par['customfields'][s_fn('Directory')]; // OPTIONAL - By default it will be installed in the /public_html folder
	
	$special_chars_username = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_username']) ? 1 : 0);
	$special_chars_password = (!empty($softaculous_conf['use_special_chars']) || !empty($softaculous_conf['use_special_chars_password']) ? 1 : 0);
	$rand_pass_length = (!empty($softaculous_conf['rand_pass_length']) ? $softaculous_conf['rand_pass_length'] : 12);
	
	if(!empty($GLOBALS['softaculous_conf']['use_panel_login'])){
		$new->data['admin_username'] = $par['username'];
		$new->data['admin_pass'] = $par['password'];
	}else{
		$new->data['admin_username'] = $par['customfields'][s_fn('Admin Name')];
		$new->data['admin_pass'] = $par['customfields'][s_fn('Admin Pass')];
	}
	
	$new->data['admin_email'] = $par['clientsdetails']['email']; //'admin@domain.com';
	
	// Do we have to overwrite the existing files ??
	if(!empty($softaculous_conf['overwrite_existing'])){
		$new->data['overwrite_existing'] = true;
	}
	
	// Does the User have any Custom Fields ??
	foreach($softaculous_conf['custom_fields'] as $ck => $cv){
		$new->data[$ck] = $par['customfields'][$cv];
	}
	
	// Does the User want to load the default values ??
	foreach($softaculous_conf['defaults'] as $dk => $dv){
		if(empty($new->data[$dk])){
			$new->data[$dk] = $dv;
		}
	}
	
	// Does the User want to load the default values by product id ??
	foreach($softaculous_conf['defaults_by_pid'][$par['pid']] as $pk => $pv){
		if(empty($new->data[$pk])){
			$new->data[$pk] = $pv;
		}
	}
	
	// If we still have username and password empty we will generate random values
	$new->data['admin_username'] = (!empty($new->data['admin_username']) ? $new->data['admin_username'] : __srandstr(12, $special_chars_username));
	$new->data['admin_pass'] = (!empty($new->data['admin_pass']) ? $new->data['admin_pass'] : __srandstr($rand_pass_length, $special_chars_password));	
	
	// Login and get the cookies
	$ch = curl_init();
	$cookie_jar = fopen('php://temp', 'w');
	curl_setopt($ch, CURLOPT_URL, $protocol.$par['serverhostname'].':8083/login/');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('user' => $par['username'],
			'password' => $par['password']);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	// Check the Header
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
	
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	if(preg_match('/type="hidden" name="token" value=("|\')(.*?)("|\')>/is', $resp, $mat)){
		$token = trim($mat[2]);	
	}
	
	// List of Scripts
	$scripts = softaculous_scripts();
	$ins_script = !empty($softaculous_conf['custom_fields']['Script']) ? $softaculous_conf['custom_fields']['Script'] : $par['customfields'][s_fn('Script')];
	
	// Which Script are we to install ?
	foreach($scripts as $key => $value){				
		if(trim(strtolower($value['name'])) == trim(strtolower($ins_script))){
			$sid = $key;
			break;
		}
	}
	
	// Did we find the Script ?
	if(empty($sid)){
		report_soft_error('Could not determine the script to be installed. Please make sure the script name is correct');
		return false;
	}
	
	// Add a Question mark if necessary
	if(substr_count($new->login, '?') < 1){
		$new->login = $new->login.'?';
	}
	
	// Login PAGE
	if($scripts[$sid]['type'] == 'js'){
		$new->login = $new->login.'act=js&soft='.$sid;
	}elseif($scripts[$sid]['type'] == 'perl'){
		$new->login = $new->login.'act=perl&soft='.$sid;
	}else{
		$new->login = $new->login.'act=software&soft='.$sid;
	}
	
	// Give an Overwrite signal for existing files and folders
	if(!isset($new->data['overwrite_existing'])){
		$new->data['overwrite_existing'] = 1;
	}

	$new->login = $new->login.'&autoinstall='.rawurlencode(base64_encode(serialize($new->data)));

	if(!empty($new->debug)){
		return $new->data;
	}
	
	$resp = '';
	
	// Login and get the cookies
	$ch = curl_init(); //$new->login
	curl_setopt($ch, CURLOPT_URL, $protocol.$par['serverhostname'].':8083/login/');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	$post = array('user' => $par['username'],
			'password' => $par['password'],
			'token' => $token);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	$nvpreq = http_build_query($post);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);
	
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
	
	// Follow redirects
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);		
	
	// Get response from the server.
	$resp = curl_exec($ch);

	 // Login and get the cookies
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $new->login);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);

	// Turn off the server and peer verification (TrustManager Concept).
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
	
	// Get response from the server.
	$resp = curl_exec($ch);
	
	// Did we login ?
	if($resp === false){
		report_soft_error('Could not login to the remote server. cURL Error : '.curl_error($ch));
		return false;
	}
	
	curl_close($ch);
		
	if($resp == 'installed'){
		report_soft_message('Script Installed successfully');
		return true;
	}else{
		report_soft_error('The following errors occured : 
'.$resp);
		return false;
	}

}

// Interworx Ends

function Soft_Auto_Install($params){

	global $softaculous_conf;
	
	$par = $params['params'];
	
	if(empty($GLOBALS['softaculous_conf']['custom_fields']['Script']) && !empty($GLOBALS['softaculous_conf']['install']['pid'][$par['pid']])){
		$GLOBALS['softaculous_conf']['custom_fields']['Script'] = $GLOBALS['softaculous_conf']['install']['pid'][$par['pid']];
	}
	
	// If the user has defined list of pids then we need to check if this is that pid
	if(!empty($GLOBALS['softaculous_conf']['pid']) && is_array($GLOBALS['softaculous_conf']['pid']) && !in_array($par['pid'], $GLOBALS['softaculous_conf']['pid'])){
		//report_soft_message('Auto installer is allowed for only following products :'.$GLOBALS['softaculous_conf']['pid']);
		return true;
	}
	
	// We dont have to install at the moment as this product is not to be auto installed
	if((empty($par['customfields'][s_fn('Script')]) || strtolower(trim($par['customfields'][s_fn('Script')])) == 'none') && empty($GLOBALS['softaculous_conf']['custom_fields']['Script'])){
		report_soft_message('Script name was not posted');
		return true;
	}
	
	// Is it a cPanel server ?
	if(strtolower($par['moduletype']) == 'cpanel' || $par['moduletype'] == 'cpanel_extended' || $par['moduletype'] == 'cpanelExtended'){
		soft_cpanel($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'directadmin' || strtolower($par['moduletype']) == 'directadmin_extended' || strtolower($par['moduletype']) == 'directadminextended'){
		soft_directadmin($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'webuzo'){
		
		soft_webuzo($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'plesk'){
		soft_plesk($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'interworx'){
		soft_interworx($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'centoswebpanel' || strtolower($par['moduletype']) == 'cwp7'){
		soft_cwp($par);
		return true;
	}
	
	if(strtolower($par['moduletype']) == 'vesta'){
		soft_vestacp($par);
		return true;
	}
	
	// Wrong Module
	report_soft_error('The Package Module type is wrong meaning its not cPanel, cPanel Extended nor Direct Admin. This Softaculous hook will only work with cPanel, cPanel Extended and Direct Admin module types');

}

function __srandstr($length, $special = 0){
	
	$randstr = "";
	
	// Uppercase
	$randstr .= strtoupper(chr(97 + mt_rand(0, 25)));
	
	// Number
	$randstr .= mt_rand(0, 9);

	if(!empty($special)){
		// Special Character
		$sp_chars = '!@#$%&*?';
		$randstr .= $sp_chars[rand(0, strlen($sp_chars) - 1)];
	}
	
	$newlength = ($length - strlen($randstr));
	
	for($i = 0; $i < $newlength; $i++){	
		$randnum = mt_rand(0,61);		
		if($randnum < 10){		
			$randstr .= chr($randnum+48);			
		}elseif($randnum < 36){		
			$randstr .= chr($randnum+55);			
		}else{		
			$randstr .= chr($randnum+61);			
		}		
	}
	return str_shuffle($randstr);
}

function soft_login($vars){

	if(empty($_REQUEST['softa_login'])){
		return true;
	}
	
	if(!empty($GLOBALS['softaculous_conf']['pid']) && !in_array($vars['pid'], $GLOBALS['softaculous_conf']['pid'])){
		return true;
	}
	
	$moduletype = strtolower($GLOBALS['moduleparams']['moduletype']);
	
	// Is it a cPanel server ?
	if($moduletype == 'cpanel' || $moduletype == 'cpanel_extended' || $moduletype == 'cpanelextended'){
		softaculous_redirect_cpanel($vars);
		return true;
	}
	
} 

function softaculous_redirect_cpanel($par){
	
	header('location:clientarea.php?action=productdetails&id='.$par['serviceid'].'&dosinglesignon=1&app=Softaculous_Home');
}

function soft_primarySidebar($primarySidebar){
	
	global $softaculous_conf;
	
	if(!empty($GLOBALS['softaculous_conf']['softaculous_sidebar_link'])){
		return true;
	}
		
	if(!empty($GLOBALS['softaculous_conf']['pid']) && !in_array($GLOBALS['moduleparams']['pid'], $GLOBALS['softaculous_conf']['pid'])){
		return true;
	}
	
	// Check if we should show SitePad link
	$allowed_modules = array('cpanel', 'cpanel_extended', 'cpanelextended');
	
	$check_module = strtolower($GLOBALS['moduleparams']['moduletype']);

	if(in_array($check_module, $allowed_modules)){
		
		//@var \WHMCS\View\Menu\Item $primarySidebar
		$newMenu = $primarySidebar->addChild(
			'Auto Install',
			array(
				'name' => 'Auto Install',
				'label' => 'Auto Install',
				'order' => 99,
				'icon' => 'fa-cog',
			)
		);
		
		$newMenu->addChild(
			'Softaculous',
			array(
				'name' => 'Softaculous Auto Install',
				'label' => 'Softaculous Auto Install',
				'uri' => 'clientarea.php?action=productdetails&id='.$_GET['id'].'&softa_login='.md5(uniqid(rand(), true)),
				'order' => 10,
				'icon' => 'fa-magic',
				'attributes' => array(
					'target' => '_blank'
				)
			)
		);
	}
}

add_hook("AfterModuleCreate", 1, "Soft_Auto_Install");
add_hook('ClientAreaPage', 1, 'soft_login');
add_hook('ClientAreaPrimarySidebar', 1, 'soft_primarySidebar');

?>