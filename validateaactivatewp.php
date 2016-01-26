<?php

/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 * DO NOT CHANGE ANYTHING BELOW THIS LINE
 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * 
 */
 
/* ENCODE FROM HERE ON */


if (!class_exists('activateWPValidator')) {
	class activateWPValidator{
		protected $productId;
		protected $licenseStatus;
		protected $numberAttempts;
		protected $settings;
		protected $message;
		protected $remoteUrl;
		protected $email;
		protected $messages;
		protected $debug;
		protected $productSlug;
		
		function __construct($productId, $url, $messages, $slug) {
			$this->productId = $productId;
			$this->remoteUrl = $url;
			$this->messages = $messages;
			$this->debug = true;        
			$this->productSlug = preg_replace('/\s+/', '', $slug);
			
			// Get the settings
			//update_option($this->productSlug . "_settings", array());
			$this->settings = get_option($this->productSlug . "_settings", "x");
			if ($this->settings == "x") {
				$this->settings = Array();
			}
			
			
	
		}
		
		
	 function getLicenseMessage() { 
			
			if ($this->settings["lastvalidateresponse"] <> "") {
				return "Licensing error: " . $this->settings["lastvalidateresponse"];
			} else {
				return $this->messages[$this->settings["localkeysplit"][4]];
			}
		}
		
		function getLocalKeyExpiryTimestamp() {
			return $this->settings["localkeysplit"][3];
		}
		
		
		
		
		
		// Save setttings locally and to the WP database
		function updateSetting($key, $value) {
			$this->settings[$key] = $value;
			update_option($this->productSlug . "_settings", $this->settings);
		}
		
		
		/***************************************************
		 * Validation routines
		 ***************************************************/
		
		function validateLicense($key,$username,$useremail, $debug = false) {
	
			if($key != 'x'){
				$this->updateSetting("licensekey", $key);
				$this->updateSetting("username", $username);
				$this->updateSetting("useremail", $useremail);
			}
			$this->debug = $debug;
			$revalidate = true;
			
			if ($this->debug) echo "Current settings:<pre>" . print_r($this->settings,1) . "</pre>";
	
			// Get the local key
			$localKey = $this->getLocalKey();
			$localKeySplit = explode("|", $localKey);
			if ($this->debug) echo '<hr size="10" color="#000000">';
			if ($this->debug) echo "<pre>" . print_r($localKeySplit,1) . "</pre>";
			$this->updateSetting("localkeysplit", $localKeySplit);
			// Now we've got the key, validate it.
			$isValid = true;
			
			switch ($localKeySplit[4]) {
				case "active":
					$this->setLicenseActive();
					break;
				case "expired":
					$this->setLicenseExpired();
					$isValid = false;
					break;
				case "unknown":
					$this->setLicenseUnknown();
					$isValid = false;
					break;
				case "domainlimit":
					$this->setLicenseDomainLimit();
					$isValid = false;
					break;
				default:	
					$this->setLicenseInactive();
					$isValid = false;
					break;
			}
			return $isValid;
		}
		
		function getLocalKey() {
			// Get the current local key
			$localKey = $this->settings["localkey"];
			
			
			if ( (trim($localKey[0]) == "") || (!$this->isLocalKeyCurrent() ) ){
				if ($this->debug) echo "<BR>Local Key has expired"; // Get a new local key from the remote server
				$localKey = $this->refreshLocalKey();
				$localKeySplit = explode("|", $localKey);
				$this->localKeySplit = $localKeySplit;
				$this->localKey = $localKey;
				$this->updateSetting("localkeysplit", $localKeySplit);
				$this->updateSetting("localkey", $localKey);
				if ($this->debug) echo "<BR>Got new local key: $localKey";
				if ($this->debug) echo "<BR>Expires on " . date("d-m-y H:i:s", $localKeySplit[3]);
			} else {
				if ($this->debug) echo "<BR>Got saved local key: $localKey";
				$this->updateSetting("lastLocalValidate", time());
			}
			
			
			return $localKey;
			
		}
		
		
		function refreshLocalKey() {
			// Get the current local key
			$localKey = $this->settings["localkey"];
			
			// Get the number of validation failures due to communication
			$numFails = $this->settings["numbervalidatefails"];
	
			// Get the last time a revalidation was attempted
			$lastValidateAttempt = $this->settings["lastvalidateattempt"];
			if ($lastValidateAttempt > 0) {
				if ($this->debug) echo "<BR>Last remote validation was  ";
				if ($this->debug) echo $this->dateDifference(time(),$this->settings["lastvalidateattempt"]) . " ago"; 
			} else {
				if ($numFails == 0) {
					if ($this->debug) echo "<BR>No remote validate has been attempted before";
				} else {
					if ($this->debug) echo "<BR>The last remote validationg failed";
				}
			}
			
			
			
			if ($this->debug) echo "<br>Connecting to remote server: " . $this->remoteUrl;
			$productid = "";
	
			$args = array( 'license'=>$this->settings["licensekey"], 'productid' => $this->productId, 'ip' => $_SERVER['SERVER_ADDR'], 'domain'=>parse_url(home_url('/'), PHP_URL_HOST), 'username'=>$this->settings["username"],'useremail'=>$this->settings["useremail"] ); 
	
			$response = wp_remote_post( $this->remoteUrl, array(
				'timeout' => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking' => true,
				'headers' => array(),
				'body' => $args,
				'cookies' => array()
			) );
	
	
			// Check for  issues
			$error = false;
			
			if( is_wp_error( $response ) ) {
				if ($this->debug) echo '<br>Something went wrong!';
				if ($this->debug) echo "<pre>" . print_r($response,1) . "</pre>";
				
				if ($this->debug) echo '<br>Something went wrong!';
				if ($this->debug) echo "<pre>" . print_r($response,1) . "</pre>";
				
				// Update the number of fails, if it's more than 24 hours since the last one, and if
				// admin has set a limit
				$numFails = $this->incrementOfflineFails();
				$result = "";
				
				$error = true;
				$errorMessage = $response->errors["http_request_failed"][0];
				$this->updateSetting("lastvalidateresponse", "Problem connecting to ActivateWP License Server ("  . $response->errors["http_request_failed"][0] . ")");
			} else {
				if ($this->debug) echo '<br>HTTP request succeeded!';
				if ($response["response"]["code"] <> 200) {
					if ($this->debug) echo '<br>HTTP code: ' . $response["response"]["code"];
					$error = true;
					$numFails = $this->incrementOfflineFails();
					$errorMessage = "Unable to reach remote server (Response code:" . $response["response"]["code"] . ")";
					$this->updateSetting("lastvalidateresponse", "Problem connecting to ActivateWP License Server: "  . $errorMessage);
				} else {
					// Reset any errors
					$this->updateSetting("lastvalidateresponse", "");
				}
			}
	
			
			
			if (! $error) {
				// Get the body only
				$response = $response['body'];
				if ($this->debug) echo '<HR>Response from remote server:<blockquote><font color="red"><pre>';
				if ($this->debug) print_r( $response );
				if ($this->debug) echo '</pre></font></blockquote><hr>';
				$result = substr($response, strpos($response, "===")+3);
				if ($this->debug) echo '<Hr>Encoded Result:<pre>';
				if ($this->debug) print_r( $result );
				if ($this->debug) echo '</pre><hr>';
				$localkey = base64_decode($result);
				if ($this->debug) echo '<Hr>Decoded Result:<pre>';
				if ($this->debug) print_r( $result );
				if ($this->debug) echo '</pre><hr>';
				
				
			} 
			
			// Save the time and number of files
			$this->updateSetting("lastvalidateattempt", time());
			$this->updateSetting("numbervalidatefails", $numFails);
			return $localkey;
		}
		
		
		function incrementOfflineFails() { 
			// Get the number of validation failures due to communication
			$numFails = $this->settings["numbervalidatefails"];
			if ($numFails == "") {
				$this->updateSetting("numbervalidatefails", 0);
				$numFails = 0;
			}
			
			
			 if ($this->maxDaysOffline <> 0) {
				// There is a limit
				if ($this->debug) echo "<BR>There is a limit on the number of offline fails. Checking limit.";
				// Is it more than 24 hours since last fail?
				$lastFail = $this->settings["lastvalidateattempt"];
				
				if ((time()-$lastFail) > (24*60*60)) {
					if ($this->debug) echo "<BR>More than 24 hours since last fail";
					// Yes
					$numFails++;
					$this->updateSetting("numbervalidatefails", $numFails);
					
					
					// If it's failed more times than the limit, set the license to inactive
					if ($numFails > $this->maxDaysOffline) {
						if ($this->debug) echo "<BR>More than max number of allowed fails, so license is to be made inactive";
						$this->setLicenseInactive();
					}                    
				} else {
					if ($this->debug) echo "<BR>Less than 24 hours since last fail";
				}
			} else {
				if ($this->debug) echo "<BR>No limit on the number of offline fails.";
			}
			return $numFails;
		}
		
		function isLocalKeyCurrent() {
			$expires = $this->settings['localkeysplit'][3];
			if ($this->debug) echo "<BR>Local key expires at " . date("d-m-y H:i:s", $expires);
			if ($this->debug) echo "<BR>Current time is " . date("d-m-y H:i:s", time());
			if ($expires <= time()) {
				
				if ($this->debug) echo "<BR>Local key has expired";
				return false;
			} else {
				if ($this->debug) echo "<BR>Local key is still current";
				return true;
			}
		}
	
		function setLicenseActive() {
			if ($this->debug) echo "<BR>License is active";
				   
			// Reset the "last check" and "# attempt" settings
			$this->updateSetting("numbervalidatefails", 0);
			
			// Update the license status
			$this->updateSetting("license_status", "active");
			$this->licenseStatus = "active";
			$this->message = "License is active";
			
			$this->updateSetting("lastLocalValidate", time());
			$this->updateSetting("lastRemoteValidate", time());
	
			
		}
		
		function setLicenseInactive() {
			if ($this->debug) echo "<BR>License is inactive";
			$this->updateSetting("license_status", "inactive");
			$this->licenseStatus = "inactive";
			$this->message = "License is inactive";
		}
		
		function setLicenseDomainLimit() {
			if ($this->debug) echo "<BR>License has reached domain limit";
			$this->updateSetting("license_status", "domainlimit");
			$this->licenseStatus = "domainlimit";
			$this->message = "License has reached it's domain limit.";
		
		}
		
		function setLicenseUnknown() {
			if ($this->debug) echo "<BR>License key is unknown";
			$this->updateSetting("license_status", "inactive");
			$this->licenseStatus = "inactive";
			$this->message = "License key is not recognized";
		
		}
		
		
		function setLicenseExpired() {
			if ($this->debug) echo "<BR>License key is expired";
			$this->updateSetting("license_status", "expired");
			$this->licenseStatus = "expired";
			$this->message = "License key is expired";
		}
		
		function dateDifference($timenow,$oldtime) {
			/**
			 * Minutes  =  60       seconds
			 * Hour     =  3600     seconds
			 * Day      =  86400    seconds
			 * Week     =  604800   seconds
			 * Month    =  2592000  seconds
			 */
			$secondDifference = $timenow-$oldtime;
		
			if ($secondDifference >= 2592000) {
				// months
				$difference = $secondDifference/2592000;
				$difference = round($difference,0);
				if ($difference>1) { $extra="s"; }
				$difference = $difference." month".$extra."";
			}
			elseif ($secondDifference >= 604800) {
				// weeks
				$difference = $secondDifference/604800;
				$difference = round($difference,0);
				if ($difference>1) { $extra="s"; }
				$difference = $difference." week".$extra."";
			}
			elseif ($secondDifference >= 86400) {
				// days
				$difference = $secondDifference/86400;
				$difference = round($difference,0);
				if ($difference>1) { $extra="s"; }
				$difference = $difference." day".$extra."";
			}
			elseif ($secondDifference >= 3600) {
				// hours
				$difference = $secondDifference/3600;
				$difference = round($difference,0);
				if ($difference>1) { $extra="s"; }
				$difference = $difference." hour".$extra."";
			}
			elseif ($secondDifference < 3600) {
				// hours
				$difference = $secondDifference/60;
				$difference = round($difference,0);
				if ($difference>1) { $extra="s"; }
				$difference = $difference." minute".$extra."";
			}
		
			$FinalDifference = $difference;
			return $FinalDifference;
		} 
		
	}
}

// Create the plugin, passing in the activateWP product ID, it's URL, and the text messages
function create_activateWPValidator($url) {
	if ( ! function_exists( 'get_plugins' ) ) 
		require_once ABSPATH . 'wp-admin/includes/plugin.php';	
		$all_plugins = get_plugins();
		foreach($all_plugins as $mypath => $hdata):
			$path = plugin_basename(__FILE__);
			$t1 = explode('/',$path);
			$t2= explode('/',$mypath);	
			$s1 = $t1[0];
			$s2 = $t2[0];
			if(trim($s1) == trim($s2))
				$fname = $t2[1];
		endforeach;
	
		$plpath = plugin_dir_path( __FILE__ ).$fname;
	
		$plugindata = get_plugin_data( $plpath, $markup = true, $translate = true ) ;
		// Define the text that the user will see in when the license is in each of the states.
		$activateWpMessages = array(
		"inactive"     => __("Your ".$plugindata["Name"]." license is inactive"),
		"expired"      => __("Your ".$plugindata["Name"]." license has expired"),
		"invalid"      => __("Your ".$plugindata["Name"]." license key is invalid"),
		"domainlimit"  => __("Your ".$plugindata["Name"]." license is already installed on the maximum number of domains"),
		"active"       => __("Your ".$plugindata["Name"]." license key is active"),
		"banned"	   => __("Your domain has been banned"),
		"unknown"	   => __("Your ".$plugindata["Name"]." license key is invalid")
		);
		
		$explode = explode('/',$path);
		$slug = $explode[count($explode)-2];
	/*	echo $plugindata["PluginURI"]."/index.php?activatewpv=1";
							print_r($activateWpMessages);
							echo $slug;*/
		return new activateWPValidator(
							'',
							$url,
							$activateWpMessages,
							$slug
						);
    
}


////////////////////////////////////////////////////////////////////////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////////////////////////////
function validate_actwp_ae12e7b66004bffc56748902ab9b1cbe()
{
$url = "http://burchdigitalmarketing.com/index.php?activatewpv=1";
$x = create_activateWPValidator($url);
if(isset($_POST['submit43965'])){
	$key=$_POST['lickey'];
	$username=$_POST['username'];
	$useremail=$_POST['useremail'];
}else{
	$key="x";
}
$y= $x->validateLicense($key,$username,$useremail, false);
if($y)  return true;

   $image_folder = SM_PLUGIN_URL . "/images/";
//show error and form

?>
<style type="text/css">
.vhead {
	vertical-align:middle;
	font-size:20px;
	font-weight:bold
}
.tdbutton {
	text-align:center;
}
.vtd {
	vertical-align:middle;
}
.vtd input[type=text] {
	width:300px;
	padding:7px 10px;
	font-size:20px;
}
.vtd .button-primary {
	padding: 0px 15px 0px 15px;
	height: 41px;
	font-size: 20px !important;
	width: 100px;
}
</style>
<div class='wrap' style="border:1px solid #999; border-radius:10px; width:600px; margin:0 auto; margin-top:20px; padding:20px;"> <br/>
  <form action="<?php
    echo $_SERVER['REQUEST_URI'];
?>" method="post">
    <table style="margin:0 auto; ">
      <tr>
        <th colspan="4" style="text-align:left"> <p style="font-weight:bold; color:#F00; font-size:20px;">
            <?php
    _e($x->getLicenseMessage());
?>
          </p>
          <br/>
          <?php
    $expires429 = $x->getLocalKeyExpiryTimestamp();
?>
          <p>
            <?php

    _e("Local key expires at: ");
?>
            <strong>
            <?php
    echo date("d-m-y H:i:s", $expires429);
?>
            </strong> <br/>
            <?php
    _e("Current time is: ");
?>
            <strong>
            <?php
    echo date("d-m-y H:i:s", time());
?>
            </strong>
            <?php
    if ($expires429 <= time())
      {
        echo '<br/><br/><span style="color:#F00">' . __("Local key has expired") . '</span>';
      }
    else
      {
        echo '<br/><br/><span style="color:#F00">' . __("Local key is still current") . '</span>';
      }
?>
          </p>
          <br/> <?php $temp = explode('?',$url); $par_url = str_replace('index.php','',$temp[0]);  ?>
          <strong>* If the key fails validation, please click <a target="_blank" href="<?php echo $par_url;  ?>">here </a> and get your new key</strong>
          <br/></th>
      </tr>
      <tr>
        <th class="vhead" >Enter Key</th>
        <td class="vtd" style="vertical-align:middle"><input onfocus="if(this.value=='x') this.value='';" onblur="if(this.value=='') this.value='x';" type="text" name="lickey" value="<?php echo $key; ?>" /></td>
      </tr>
      <tr>
        <th  class="vhead">User Name</th>
        <td  class="vtd"><input type="text" name="username" value="<?php echo $username ?>" required /></td>
      </tr>
      <tr>
        <th  class="vhead">User Email</th>
        <td class="vtd" ><input type="text" name="useremail" value="<?php echo $useremail ?>" required /></td>
      </tr>
      <tr>
        <td  colspan="4"  class="vtd tdbutton"><input class="button-primary" type="submit" value="Validate" name="submit43965" /></td>
      </tr>
    
    </table>
  </form>
</div>
<?php	exit;
}
?>