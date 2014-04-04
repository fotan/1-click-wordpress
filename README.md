1-click-wordpress
=================

A &lt; 20kb file that installs WordPress and it's zillion files, plus sets up your wp-config and downloads plugins and cleans up after itself.  Saves a ton of time.


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
