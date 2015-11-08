<?php

/*///////////////////////////////////////////////////////////////////////////

	One-Click WordPress Installer
	By Fotan (www.fotan.net)

	v1.2.4 (11/6/15)
		- Changed download to cURL and turned it into a function so it can be used for WP core as well
			as plugins.
		- Changed the download of WP core to .zip from .tar.gz
		- Changed some of the exec functions to PHP functions
		- Added better comments to the functions
		- Got rid of the plugins install stuff.  Seems better to just install plugins on a site by site basis.
	v1.2.3 (1/23/15)
		- Fixed the WordPress download function.  The original one stopped working with a 302 error.
			Decided to make it a little more simple and just used file_get_contents / file_put_contents
	v1.2.2 (5/16/14)
		- Added BackWPUp plugin to install list
		- Added Velvet Blues plugin to install list
	v1.2.1 (4/30/14)
		- Fixed permissions on Plugins folder so you can upload plugins rather than just
		  installing from wordpress.org/plugins
	v1.2 (4/29/14)
		- Backed out of deleting the license.txt and readme.html files
		- Added some security "stuff" to htaccess
		- Added DISALLOW_FILE_EDIT to wp-config.php so file editing from Dashboard is disabled
		- Added Simple Custom CSS to install list
		- Added Limit Login Attempts plugin to install list
		- Added an uploads folder to wp-content
	v1.1 (4/30/14)
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


$dir = dirname(__FILE__);



// .htaccess stuff that is added later.
// Just here to make it easy to find and edit.
$htaccess_stuff =
"";
$htaccess_stuff .=
"# Block/Allow access to wp-login.php\n
# Just toggle allow/deny from all\n
<files wp-login.php>\n
order allow,deny\n
allow from all\n
</files>\n\n";
$htaccess_stuff .=
"# Don't let anyone access wp-config\n
<Files wp-config.php>\n
order allow,deny\n
deny from all\n
</Files>\n\n";
$htaccess_stuff .=
"# Don't let anyone access htaccess\n
<Files .htaccess>\n
order allow,deny\n
deny from all\n
</Files>\n\n";
$htaccess_stuff .=
"# Block the include-only files.\n
<IfModule mod_rewrite.c>\n
RewriteEngine On\n
RewriteBase /\n
RewriteRule ^wp-admin/includes/ - [F,L]\n
RewriteRule !^wp-includes/ - [S=3]\n
RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]\n
RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]\n
RewriteRule ^wp-includes/theme-compat/ - [F,L]\n
</IfModule>\n\n";
$htaccess_stuff .=
"# Turn off Directory Browsing\n
Options All -Indexes\n\n";





/**
 * Generate random Salt keys
 *
 * @param None
 */
function rkg() { // random key generator
	$secret_ar = str_split("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!@#$%^&*()-=+[]{};:<>,.?");
	$secret = "";
	for($i=0;$i<66;$i++) {
		$secret .= $secret_ar[rand(0,85)];
	}
	return substr($secret,0,64);
} // End Random Key Generator


/**
 * Create random db prefix
 *
 * @param None
 */
function rand_prefix() { // Random DB Prefix
	$secret_ar = str_shuffle("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");
	$db_prefix = "wp". substr($secret_ar, rand(5,25), 3) ."_"; // Something like ArF_
	return $db_prefix;
} // End Random DB Prefix



/**
 * Recursively copy files from one directory to another
 *
 * @param String $src - Source of files being moved
 * @param String $dest - Destination of files being moved
 */
function rcopy($src, $dest){

    // If source is not a directory stop processing
    if(!is_dir($src)) return false;

    // If the destination directory does not exist create it
    if(!is_dir($dest)) {
        if(!mkdir($dest)) {
            // If the destination directory could not be created stop processing
            return false;
        }
    }

    // Open the source directory to read in files
    $i = new DirectoryIterator($src);
    foreach($i as $f) {
        if($f->isFile()) {
            copy($f->getRealPath(), "$dest/" . $f->getFilename());
        } else if(!$f->isDot() && $f->isDir()) {
            rcopy($f->getRealPath(), "$dest/$f");
        }
    }
}



/**
 * PHP cURL download function
 *
 * @param String $remote - Remote address of the file to be downloaded
 * @param String $local - Local download location
 */
function download($remote, $local) {
	$ch = curl_init();
	// Set the URL of the page or file to download.
	curl_setopt($ch, CURLOPT_URL, $remote);
	// Create a new file
	$fp = fopen($local, 'w');
	// Ask cURL to write the contents to a file
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_exec($ch);
	curl_close($ch);
	fclose($fp);
}


/**
 * PHP unzip .zip files
 *
 * @param String $file - .zip file to be unzipped
 * @param String $destination - Location to unaip the files
 */
function unzip($file, $destination) {
	$zip = new ZipArchive;
	$res = $zip->open($file);
	if($res === TRUE) {
		$zip->extractTo($destination);
	  	$zip->close();
	}
}









if(isset($_POST["process"]) && $_POST["process"]=="true") {
	// If we can't connect to the db, so show an error.
	if(mysql_connect($_POST["DB_HOST"], $_POST["DB_USER"], $_POST["DB_PASSWORD"])===FALSE) {
		die("<p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p><p>&nbsp;</p>
			<h3 align='center'>
				Couldn't connect to the database.<br />
				Please go back and check your DB settings.
			</h3>");
	}
	else {
		// We connected to the db, so unzip the wordpress.zip file and move files around.
		unzip("wordpress.zip", "./");
		// Copy Wordpress files from wordpress to the current directory.
		rcopy("wordpress", "./");
		// Delete the wordpress folder
		exec("rm -rf wordpress");
		// Delete wordpress.zip
		unlink("wordpress.zip");


		// Create wp-config.php
		if(!file_exists("wp-config-sample.php")) {
			echo "Operation appears to have failed.<br />";
		}
		else {
			$config = file_get_contents("wp-config-sample.php");

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
			$config = str_replace(
		                  "/**#@-*/",
						  "/**#@-*/ \n\n\n
						  // Auto Update FTP Information
						  define('FTP_USER', '".$_POST["ftp_user"]."');
						  define('FTP_PASS', '".$_POST["ftp_pass"]."');
						  define('FTP_HOST', '".$_POST["ftp_domain"]."');
						  define('FTP_SSL', ".$_POST["ftp_ssl"]."); \n

						  // Set WP Memory Limit for PHP
						  define('WP_MEMORY_LIMIT', ".$_POST["memory_limit"]."); \n\n

						  // Disable File Editing from Dashboard
						  define('DISALLOW_FILE_EDIT', true);\n\n\n
			", $config);




			if(substr($_POST["table_prefix"], strlen($_POST["table_prefix"])-1)=="_")
				{ $config = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '".$_POST["table_prefix"]."';", $config); }
			else
				{ $config = str_replace("\$table_prefix  = 'wp_';", "\$table_prefix  = '".$_POST["table_prefix"]."_';", $config); }

			$fp = fopen(dirname(__FILE__)."/wp-config-sample.php", "w+");
			fwrite($fp, $config);
			fclose($fp);
		}

		rename("wp-config-sample.php", "wp-config.php");

		// Make a .htaccess file
		exec("touch .htaccess");
		exec("chmod 644 .htaccess");
		$hta = fopen(dirname(__FILE__)."/.htaccess", "w+");
		fwrite($hta, $htaccess_stuff);
		fclose($hta);

		// Make an uploads folder
		exec("mkdir wp-content/uploads");
		exec("chmod 755 wp-content/uploads");

		// Fix permissions on the Plugins folder
		exec("chmod 755 wp-content/plugins");

		// Delete this script
		unlink("1-click-wp.php");

		header("Location: wp-admin/install.php");

		die();
	}
}
// See if wp-config exists.  If so, Wordpress is already installed and we need to stop.
if(is_file("wp-config-sample.php") || is_file("wp-config.php")) {
	echo "It looks like Wordpress has already been already uploaded and/or installed.<br />";
	echo "This utility is designed for clean installs only.<br />";
	die();
}
// See if the install directory is writable.  If not, we can't install.
if(is_writable($dir)===false) {
	echo "It looks like the current directory is not writable.<br />";
	echo "Please correct and re-run this script.<br />";
	die();
}

// See if there is already a wordpress archive here.  If so, delete it so we can download a fresh copy.
$wp_file = "wordpress.zip";
if(is_file($wp_file)) { unset($wp_file); }

// Download the latest version of Wordpress
download("https://wordpress.org/latest.zip", "wordpress.zip");
if(is_file("wordpress.zip")) { $wpfile = "wordpress.zip"; }
else { die("Wordpress wasn't downloaded.  Try again."); }
?>


<html>
	<head>
    	<title>One-Click WordPress Installer</title>
		<style>
            form {
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
	    <form action="" method="post">
		    <input type="hidden" name="process" value="true">
		    <input type="hidden" name="wpfile" value="<?php echo $wpfile; ?>">

		    DB Name: <small>(<em>database must already exist</em>)</small><br />
		    <input type="text" name="DB_NAME" size="25" value="<?php echo $db_name; ?>"><br />

		    MySQL username <small>(<em>must be a valid user for the database above</em>)</small>:<br />
		    <input type="text" name="DB_USER" size="25" value="<?php echo $db_user; ?>"><br />

		    MySQL password <small>(<em>this is the password for the database user above</em>)</small>:<br >
		    <input type="text" name="DB_PASSWORD" size="25" value="<?php echo $db_password; ?>"><br />

		    DB Host <small>(99% chance you won't need to change this value)</small>:<br />
		    <input type="text" name="DB_HOST" value="localhost" size="25"><br />

		    <!-- Secret Salts WP.  No reason to see them. -->
		    <input type="hidden" name="SECRET_KEY" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="AUTH_KEY" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="SECURE_AUTH_KEY" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="LOGGED_IN_KEY" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="NONCE_KEY" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="AUTH_SALT" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="SECURE_AUTH_SALT" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="LOGGED_IN_SALT" value="<?php echo rkg(); ?>">
		    <input type="hidden" name="NONCE_SALT" value="<?php echo rkg(); ?>">

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

		    <input type="submit" class="button" value="Submit">

	    </form>
    </body>
</html>