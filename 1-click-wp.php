<?php
/*///////////////////////////////////////////////////////////////////////////
	
	One-Click WordPress Installer 
	By Fotan (www.fotan.net)
	
	v1.1
		- Setup screen now includes a list of the plugins I always seem to want to 
		  install with check boxes.  Just check the box and the latest version of the 
		  plugin is downloaded and unzipped in the wp-content/plugins folder.
		- To add more plugins to download and unzip, just copy and paste one of hte
		  ones already there and change the value and text after the input.
		- After unzipping the plugin file, the .zip file is deleted from the server
		- Cleaned up the CSS in the form a little.
	v1.0
		- Downloads the latest, stable version of WordPress
		- Displays a form to fill in DB Host, DB User, DB Password
		- Randomly creates the SALT fields (hidden from view)
		- Randomly creates the DB Prefix, but lets you change it if you want
		- Displays a form to fill in FTP Host, FTP User, FTP Password, SSL?  
		  If properly filled in, you will be able to use one touch installs and updates
		- Displays a form to set a Memory Limit
		- Deletes the tar ball of the install package
		- Deletes license.txt, because it's silly to have
		- Deletes readme.html, because it's silly to have
		- Creates .htaccess file and makes it writable to the user (664)
		- Forwards to instal.php
	
	
	
	Based on:	
	EasyWP WordPress Installer v1.2          
	Copyright ©2008 - 2010 Michael VanDeMar  
	http://www.funscripts.net/               
	All rights reserved.                     

///////////////////////////////////////////////////////////////////////////*/

function get_plugins($plugin_array) { // Take an array of plugin name slugs and download them from WP
    foreach($plugin_array as $plugin)
        {
            $url = "http://downloads.wordpress.org/plugin/". $plugin .".zip";
	        copy($url, "wp-content/plugins/". $plugin .".zip");
        }
}

function unzip_plugins($plugin_array) { // Unzip the plugins we downloaded
    foreach($plugin_array as $plugin)
        {
			exec("unzip wp-content/plugins/". $plugin .".zip -d wp-content/plugins/");
			unlink("wp-content/plugins/". $plugin .".zip"); 
		}
}

function rkg() { // random key generator
	$secret_ar = str_split("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!@#$%^&*()-=+[]{};:<>,.?");
	$secret = "";
	for($i=0;$i<66;$i++){
		$secret .= $secret_ar[rand(0,85)];
	}
	return substr($secret,0,64);
} // End Random Key Generator

function rand_prefix() { // Random DB Prefix
	$secret_ar = str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	$db_prefix = "wp". substr($secret_ar, rand(5,25), 3) ."_"; // Something like ArF_
	return $db_prefix;
} // End Random DB Prefix

function getLatestWP(){
	$head = "";

	$fp = @fsockopen("wordpress.org", 80, $errno, $errstr, 15);
	if(!$fp){
		$response[0] = -999;
		$response[1] = "$errstr ($errno).";
		$response[2] = 0;
	}else{
		stream_set_timeout($fp, 5);
		$out = "GET /latest.tar.gz HTTP/1.0\r\n";
		$out .= "Host: wordpress.org\r\n";
		$out .= "User-agent: One-Click WordPress Installer (http://www.fotan.net/)\r\n";
		$out .= "Connection: Close\r\n\r\n";

		fwrite($fp, $out);
		while(!feof($fp)){
			$head .= fgets($fp, 128);
		}
		fclose($fp);

		$tresponse = split("\r\n\r\n",$head);
		// headers returned
		$response[0] = $tresponse[0];
		// content
		$response[1] = trim(str_replace($tresponse[0], "", $head));
		//parse out response code
		preg_match("|.*\s([0-9]*)\s[^\n]*\n.*|Uis", $response[0], $out);
		if($out[1]!="" && !(is_null($out[1]))){
			$response[2] = $out[1];
		}else{
			$response[2] = 0;
		}
		if($response[2]==200){
			preg_match("/Content-Disposition: attachment; filename=(.*)\n/Ui", $response[0], $fname);
			if($fname[1]!=""){
				$fp = fopen(dirname(__FILE__)."/".trim($fname[1]),"a+");
				if($fp){
					fwrite($fp, $response[1]);
					fclose($fp);
					return trim($fname[1]);
				}else{
					die("Failed to create local file: ".trim($fname[1]));
				}
			}else{
				die("Problem parsing filename.");
			}
		}else{
			die("Problem with downloading file, header returned:\n".$response[0]);
		}
	}
} // End Get Latest WP





$dir = dirname(__FILE__);

if(isset($_POST["process"]) && $_POST["process"]=="true")
	{
		// If we can't connect to the db, show an error.
		if(!isset($_POST["doanyways"]) && @mysql_connect($_POST["DB_HOST"], $_POST["DB_USER"], $_POST["DB_PASSWORD"])===FALSE)
			{ die("<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>
					<h3 align='center'>Couldn't connect to the database.<br />Please go back and check your DB settings.</h3>"); } 
		else 
			{
				// We connected to the db, so unzip the tar ball and move files around.
				exec("tar xvfz ".$_POST["wpfile"], $buff);
				exec("mv -f ".$dir."/wordpress/* ".$dir."", $buff2);
				exec("rm -rf wordpress", $buff3);
				
				// Create wp-config.php
				if(!file_exists(dirname(__FILE__)."/wp-config-sample.php"))
					{ echo "Operation appears to have failed.<br />\n"; }
				else
					{
						$config = file_get_contents(dirname(__FILE__)."/wp-config-sample.php");
			
						// pre-3.0 replacements
						$config = str_replace("putyourdbnamehere", $_POST["DB_NAME"], $config);
						$config = str_replace("usernamehere", $_POST["DB_USER"], $config);
						$config = str_replace("yourpasswordhere", $_POST["DB_PASSWORD"], $config);
						$config = str_replace("localhost", $_POST["DB_HOST"], $config);
						$config = str_replace("'SECRET_KEY', 'put your unique phrase here'", "'SECRET_KEY', '".$_POST["SECRET_KEY"]."'", $config);
						$config = str_replace("'AUTH_KEY', 'put your unique phrase here'", "'AUTH_KEY', '".$_POST["AUTH_KEY"]."'", $config);
						$config = str_replace("'SECURE_AUTH_KEY', 'put your unique phrase here'", "'SECURE_AUTH_KEY', '".$_POST["SECURE_AUTH_KEY"]."'", $config);
						$config = str_replace("'LOGGED_IN_KEY', 'put your unique phrase here'", "'LOGGED_IN_KEY', '".$_POST["LOGGED_IN_KEY"]."'", $config);
						$config = str_replace("'NONCE_KEY', 'put your unique phrase here'", "'NONCE_KEY', '".$_POST["NONCE_KEY"]."'", $config);
			
						// 3.0 replacements
						$config = str_replace("database_name_here", $_POST["DB_NAME"], $config);
						$config = str_replace("username_here", $_POST["DB_USER"], $config);
						$config = str_replace("password_here", $_POST["DB_PASSWORD"], $config);
						$config = str_replace("localhost", $_POST["DB_HOST"], $config);
						$config = str_replace("'AUTH_KEY',         'put your unique phrase here'", "'AUTH_KEY',         '".$_POST["AUTH_KEY"]."'", $config);
						$config = str_replace("'SECURE_AUTH_KEY',  'put your unique phrase here'", "'SECURE_AUTH_KEY',  '".$_POST["SECURE_AUTH_KEY"]."'", $config);
						$config = str_replace("'LOGGED_IN_KEY',    'put your unique phrase here'", "'LOGGED_IN_KEY',    '".$_POST["LOGGED_IN_KEY"]."'", $config);
						$config = str_replace("'NONCE_KEY',        'put your unique phrase here'", "'NONCE_KEY',        '".$_POST["NONCE_KEY"]."'", $config);
						$config = str_replace("'AUTH_SALT',        'put your unique phrase here'", "'AUTH_SALT',        '".$_POST["AUTH_SALT"]."'", $config);
						$config = str_replace("'SECURE_AUTH_SALT', 'put your unique phrase here'", "'SECURE_AUTH_SALT', '".$_POST["SECURE_AUTH_SALT"]."'", $config);
						$config = str_replace("'LOGGED_IN_SALT',   'put your unique phrase here'", "'LOGGED_IN_SALT',   '".$_POST["LOGGED_IN_SALT"]."'", $config);
						$config = str_replace("'NONCE_SALT',       'put your unique phrase here'", "'NONCE_SALT',       '".$_POST["NONCE_SALT"]."'", $config);
			
						// Auto Update FTP Information
						$config = str_replace("/**#@-*/",
											  "/**#@-*/ \n\n\n
											  // Auto Update FTP Information
											  define('FTP_USER', '".$_POST["ftp_user"]."'); 
											  define('FTP_PASS', '".$_POST["ftp_pass"]."'); 
											  define('FTP_HOST', '".$_POST["ftp_domain"]."');
											  define('FTP_SSL', ".$_POST["ftp_ssl"]."); \n
												
											  // Set WP Memory Limit for PHP
											  define('WP_MEMORY_LIMIT', ".$_POST["memory_limit"]."); \n\n\n
						", $config);
						
						
						
						
						if(substr($_POST["table_prefix"], strlen($_POST["table_prefix"])-1)=="_")
							{ $config = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '".$_POST["table_prefix"]."';", $config); }
						else
							{ $config = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '".$_POST["table_prefix"]."_';", $config); }
						
						$fp = fopen(dirname(__FILE__)."/wp-config-sample.php", "w+");
						fwrite($fp, $config);
						fclose($fp);
					}
		
				rename(dirname(__FILE__)."/wp-config-sample.php", dirname(__FILE__)."/wp-config.php");
				exec("rm -f 1-click-wp.php", $buff3);
				exec("rm -f license.txt", $buff3);
				exec("rm -f readme.html", $buff3);
				
				// Make a .htaccess file
				exec("touch .htaccess");
				exec("chmod 644 .htaccess");
				
				// Delete the downloaded WP file
				exec("rm -f *.tar.gz");
				
				// Get the plugins
				get_plugins($plugins_group);
				
				// Unzip the plugins
				unzip_plugins($plugins_group);
				
				header("Location: wp-admin/install.php");
				
				die();
				//echo "Done.";
			}
	}
else
	{
		//form here, and checks
		if(@filetype($dir."/wp-config-sample.php")=="file" || @filetype($dir."/wp-config.php")=="file")
			{
				echo "It appears that Wordpress has already been already uploaded and/or installed.<br />\n";
				echo "This utility is designed for clean installs only.<br />";
				die();
			}
	
		if(is_writable($dir)===false)
			{
				echo "It does not appear that the current directory is writable.<br />\n";
				echo "Please correct and re-run this script.<br />\n";
				die();
			}
	
		$availfiles = array();
	
		if($dh = opendir($dir))
			{
				while(($file = readdir($dh)) !== false)
					{
						if(filetype($dir."/".$file)=="file" && substr($file, 0, 9)=="wordpress")
							{
								if(substr($file, strlen($file)-3)==".gz" || substr($file, strlen($file)-4)==".zip")
									{ $availfiles[] = $file; }
							}
					}
				closedir($dh);
			}
	
		if(count($availfiles)==0)
			{
				$availfiles[] = getLatestWP();
			}
		elseif(count($availfiles)>1)
			{
				echo "Multiple verions of Wordpress archives detected.<br />\n";
				echo "Please delete all but the one you wish to install, or delete all of them and allow this script to<br />\n";
				echo "download the latest version available from Wordpress.org.<br />\n";
				die();
			}
?>
		<html>
        	<head>
            	<title>One-Click WordPress Installer</title>
				<style>
                    form    {
                        background: -webkit-gradient(linear, bottom, left 175px, from(#CCCCCC), to(#EEEEEE));
                        background: -moz-linear-gradient(bottom, #CCCCCC, #EEEEEE 175px);
                        margin:auto;
                        position:relative;
                        width:550px;
                        font-family: Tahoma, Geneva, sans-serif;
                        font-size: 14px;
                        font-style: italic;
                        line-height: 24px;
                        font-weight: bold;
                        color: #09C;
                        text-decoration: none;
                        -webkit-border-radius: 10px;
                        -moz-border-radius: 10px;
                        border-radius: 10px;
                        padding:10px;
                        border: 1px solid #999;
                        border: inset 1px solid #333;
                        -webkit-box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3);
                        -moz-box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3);
                        box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3);
                    }
                    form input {
                        margin-top: 0px;	
                    }
                    H2 {
                        font-family: Verdana;
                        text-align: center;
                        margin: 26px;
                    }
                    small {
                        color: #777;
                        font-size: 10px;	
                    }
                    input {
                        width:50%;
                        display:block;
                        border: 1px solid #999;
                        height: 25px;
                        -webkit-box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
                        -moz-box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
                        box-shadow: 0px 0px 3px rgba(0, 0, 0, 0.3);
                    }
                    input.button {
                        width:100px;
                        position:absolute;
                        right:20px;
                        bottom:20px;
                        background:#09C;
                        color:#fff;
                        font-family: Tahoma, Geneva, sans-serif;
                        font-size: 16px;
                        height:30px;
                        -webkit-border-radius: 5px;
                        -moz-border-radius: 5px;
                        border-radius: 5px;
                        border: 1px solid #999;
                    }
                    input.button:hover {
                        background:#ddd;
                        color:#09C;
                    }
					input.checkbox { 
						display: inline; 
						width: 25px;
						/* kill the shadow around the check boxes */
                        -webkit-box-shadow: 0px 0px  px rgba(0, 0, 0, 0.3);
                        -moz-box-shadow: 0px 0px 0px rgba(0, 0, 0, 0.3);
                        box-shadow: 0px 0px 0px rgba(0, 0, 0, 0.3);
					}
                    
                </style>
			</head>
            <body>
            <h2>One-Click WordPress Installer</h2>
            <form action="<? echo $PHP_SELF; ?>" method="post">
            <input type="hidden" name="process" value="true">
            <input type="hidden" name="wpfile" value="<?php echo $availfiles[0]; ?>">
            
            DB Name: <small>(<em>database must already exist</em>)</small><br />
            <input type="text" name="DB_NAME" size="25" value="<?php echo $db_name; ?>"><br />
            
            MySQL username <small>(<em>must be a valid user for the database above</em>)</small>:<br />
            <input type="text" name="DB_USER" size="25" value="<?php echo $db_user; ?>"><br />
            
            MySQL password <small>(<em>this is the password for the database user above</em>)</small>:<br >
            <input type="text" name="DB_PASSWORD" size="25" value="<?php echo $db_password; ?>"><br />
            
            DB Host <small>(99% chance you won't need to change this value)</small>:<br />
            <input type="text" name="DB_HOST" value="localhost" size="25"><br />
            
            <!-- Secret Salts WP.  No reason to see them. -->
            <input type="hidden" name="SECRET_KEY" value="<?php echo $secret_key; ?>">
            <input type="hidden" name="AUTH_KEY" value="<?php echo $auth_key; ?>">
            <input type="hidden" name="SECURE_AUTH_KEY" value="<?php echo $secure_auth_key; ?>"> 
            <input type="hidden" name="LOGGED_IN_KEY" value="<?php echo $logged_in_key; ?>"> 
            <input type="hidden" name="NONCE_KEY" value="<?php echo $nonce_key; ?>"> 
            <input type="hidden" name="AUTH_SALT" value="<?php echo $auth_salt; ?>"> 
            <input type="hidden" name="SECURE_AUTH_SALT" value="<?php echo $secure_auth_salt; ?>"> 
            <input type="hidden" name="LOGGED_IN_SALT" value="<?php echo $logged_in_salt; ?>"> 
            <input type="hidden" name="NONCE_SALT" value="<?php echo $nonce_salt; ?>"> 
            
            DB prefix <small>(wp_ is the default, but it's not secure)</small>:<br />
            <input type="text" name="table_prefix" value="<?php echo rand_prefix(); ?>" size="25"> <small>(Only numbers, letters, and underscores please!)</small><br />
            
            <br /><hr /><br />
            
            FTP Domain <br />
            <input type="text" name="ftp_domain" value="fotan.us" size="25"> <br />
            
            FTP User <br />
            <input type="text" name="ftp_user" value="ftp user" size="25"> <br />
            
            FTP Password <br />
            <input type="text" name="ftp_pass" value="ftp pass" size="25"> <br />
            
            FTP SSL <small>(true / false)</small> <br />
            <input type="text" name="ftp_ssl" value="true" size="25"> <br />
            
            Memory Limit <small>Numbers Only!</small>  <br />
            <input type="text" name="memory_limit" value="128" size="25"> <small>(128k is good for most servers)</small><br />
            
            <br /><hr /><br />
            Include Plugins<br />
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="admin-management-xtended">Admin Management Xtended<br />
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="all-in-one-wp-security-and-firewall" />All In One WP Security<br />
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="configure-smtp" />Configure SMTP<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="exclude-pages" />Exclude Pages From Navigation<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="google-sitemap-generator" />Google XML Sitemaps<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="page-links-to" />Page Links To<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="simple-page-ordering" />Simple Page Ordering<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="tinymce-advanced" />TinyMCE Advanced<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="wp-blackcheck" />WP-BlackCheck<br /> 
            <input class='checkbox' type="checkbox" name="plugins_group[]" value="wp-slimstat" />WP SlimStat<br /> 
            <br /><br />

            
            <input type="submit" class="button" value="Submit">
            
            </form>
            </body>
        </html>
<?php
    }
?>
