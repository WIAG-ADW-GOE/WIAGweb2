# WIAG setup
This document contains the instructions for setting up WIAG on:
- Ubuntu-22
- the "old" GWDG servers (Linux, but only with SFTP access and no way to change the DocumentRoot)
- Windows 11 (local installation, only for testing)

## Ubuntu-22
This can be used both for testing or production.

- sudo apt update && sudo apt upgrade		-- on the GWDG cloud servers this prompts you on whether to keep the old (installed by GWDG) versions of /etc/issue and /etc/cloud/cloud.cfg or install new ones. Keep the old ones!
- install packages: sudo apt install apache2 libapache2-mod-php php php-dom php-xml php-mysql mysql-server composer php-curl
- install [symfony](https://symfony.com/download): `curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash && sudo apt install symfony-cli`
- install [node.js with yarn](https://nodejs.org/en/download) **but first change myserverusername**: `curl -o- https://fnm.vercel.app/install | bash && source /home/myserverusername/.bashrc && fnm install 22 && corepack enable yarn`
- clone repo:
	- cd /var/www/
	- sudo mkdir WIAGweb2
	- sudo chown USER WIAGweb2
	- git clone https://github.com/WIAG-ADW-GOE/WIAGweb2.git WIAGweb2
	- cd WIAGweb2
- create .env.local file with the following, where ... is a random string of around 40 characters
	```
	APP_ENV=prod
	APP_SECRET=...
	DATABASE_HOST="localhost"
	DATABASE_SERVER_VERSION="8.0.42-0ubuntu0.22.04.1"
	```
- composer install -- this will print an error, but is needed for generating the keys (the error can be ignored for now)
- give rights for the generated log directory to the group, that the apache server uses:
	- sudo chgrp www-data var/log/ -R
- generate keys and store database passwords -- [explanation found here](https://symfony.com/doc/6.4/configuration/secrets.html)
	- php bin/console secrets:generate-keys
	- php bin/console secrets:set DATABASE_PASSWORD
	- php bin/console secrets:set DATABASE_GSO_PASSWORD
	- APP_RUNTIME_ENV=dev php bin/console secrets:generate-keys
	- APP_RUNTIME_ENV=dev php bin/console secrets:set DATABASE_PASSWORD
	- APP_RUNTIME_ENV=dev php bin/console secrets:set DATABASE_GSO_PASSWORD

- import data into mysql:
	- either:
		- if you're connected to a server via SSH:
			- disconnect
			- transfer sql dumps with scp (e.g. `scp C:\Users\myusername\Downloads\mydatabasename.sql myserverusername@myserversipaddress:/home/myserverusername/.`)
			- reconnect, now the file can be found in your home directory
		- download it some other way, e.g. via phpMyAdmin in a browser
	- sudo mysql
		- CREATE DATABASE mydatabasename;
		- quit
	- sudo mysql mydatabasename < mydatabasename.sql
	
- create user and give rights to him for the database [explanation here](https://gridscale.io/en/community/tutorials/create-a-mysql-user/):
	- sudo mysql
		- CREATE USER 'mydatabaseusername'@'localhost' IDENTIFIED BY 'mypassword';
		- GRANT ALL PRIVILEGES ON mydatabasename . * TO 'mydatabaseusername'@'localhost';
		- FLUSH PRIVILEGES;
		- quit
		
- some of our queries require this setting, which is on by default, to be off [explanation here](https://stackoverflow.com/questions/23921117/disable-only-full-group-by):
	- sudo mysql
		- SET PERSIST sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));
		- quit

- configure the apache server [explanation here](https://symfony.com/doc/6.4/setup/web_server_configuration.html):
	- sudo apt install libapache2-mod-fcgid php-fpm
	- sudo a2enmod proxy_fcgi
	- cd /etc/apache2/sites-available/
	- create a wiag.conf entry (copy the default): sudo cp 000-default.conf wiag.conf
	- edit it:
		- remove the DocumentRoot line
		- add the following (change socket to correct php version if not 8.1):
			```
			<FilesMatch \.php$>
					# when using PHP-FPM as a unix socket
					SetHandler proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://dummy

					# when PHP-FPM is configured to use TCP
					# SetHandler proxy:fcgi://127.0.0.1:9000
			</FilesMatch>

			DocumentRoot /var/www/WIAGweb2/public
			<Directory /var/www/WIAGweb2/public>
					AllowOverride None
					Require all granted
					FallbackResource /index.php
			</Directory>
			```								
	- check for syntax errors: sudo apache2ctl configtest
	- disable default site: sudo a2dissite 000-default.conf
	- enable the newly created WIAG site: sudo a2ensite wiag.conf
	- reload configuration: sudo systemctl reload apache2
	
- if no access to the server configuration is available, the .htaccess file, that symfony generates in the public folder, should do the job, but for that the server needs the rewrite module enabled
	- to test this set-up:
		- sudo a2enmod rewrite
		- and add the following lines to /etc/apache2/sites-available/wiag.conf INSTEAD of the ones above
			```
			DocumentRoot /var/www/WIAGweb2/public
			<Directory /var/www/WIAGweb2/public>
				AllowOverride All
				Require all granted
				FallbackResource /index.php
			</Directory>
			```

- cd /var/www/WIAGweb2
- composer install && yarn install && cd public && yarn build && cd ..
- sudo systemctl restart apache2
- now WIAG should be reachable via the IP of the server (HTTPS would need additional steps)
- should there be an error, change APP_ENV=prod to APP_ENV=dev in the .env.local file and `sudo systemctl restart apache2`. Now you should get a stacktrace and explanation of what happened. It might (but probably not) be necessary to once call `composer install && yarn install && cd public && yarn build && cd ..`

## "old" GWDG servers
Since there is no SSH access and consequently no possibility of building the project on the server, you need to build the project locally and copy everything via SFTP. Also needed is the old .htaccess (named .old_htaccess). Should there be a generated .htaccess file under /public/, delete it.

- git clone
- cd WIAGweb2
- create .env.local file with the following, where ... is a random string of around 40 characters (no " allowed)
	```
	APP_ENV=prod
	APP_SECRET=...
	```
- IMPORTANT for wiagstage: -- otherwise changes on the stage system would actually change the prod system, meaning anyone playing around with the data might destroy important prod data
	- adjust config/packages/doctrine.yaml:
		- user: 'wiagstage_adm'
		- dbname: 'wiagstage'
- composer install -- this will print an error ('Environment variable not found: "DATABASE_PASSWORD"'), but is needed for generating the keys (the error can be ignored for now)
- generate keys and store database passwords (https://symfony.com/doc/6.4/configuration/secrets.html)
	- php bin/console secrets:generate-keys
	- php bin/console secrets:set DATABASE_PASSWORD
	- php bin/console secrets:set DATABASE_GSO_PASSWORD

- optionally generate the same thing for the dev environment:
	- change APP_ENV=prod to APP_ENV=dev in the .env.local file
	- php bin/console secrets:generate-keys				-- this will also print an error ('Environment variable not found: "DATABASE_PASSWORD"'), but seems to also be necessary
	- php bin/console secrets:set DATABASE_PASSWORD
	- php bin/console secrets:set DATABASE_GSO_PASSWORD
	- change APP_ENV=dev back to APP_ENV=prod in the .env.local file
	
- composer install
- yarn install
- cd public
- yarn build
- cd ..

- rename .old_htaccess to just .htaccess (this is the .htaccess file that is needed for these old GWDG servers, but nowhere else)

- create a new directory on the server (choose any name, but something like `new_wiagvokabulare` works well)
- transfer ALMOST all the contents of the folder to the newly created folder on the server: except the .git, doc and notebooks folders, and the .gitignore and README.md files
- transfer the folders build and images (inside the public folder) to the general directory (directory root). After this the build folder should be at new_wiagvokabulare/build and new_wiagvokabulare/public/build (same for images) -- There is no possibility of changing the DocumentRoot variable for this server (support said "no"). The simplest solution seems to be to copy the contents of the public folder also to the directory root. I did not find another solution online, since this does not seem to be a regular case.
- IMPORTANT: set the permissions to 775 for the var folder -- if you don't do this, the apache server can't use the cache and consequently can't serve the site

- rename the current `wiagvokabulare` folder to something like `wiagvokabulare-2025-10-24`
- rename your folder (with name `new_wiagvokabulare` or similar) to `wiagvokabulare`
- now the site should be reachable - should there be an error, change APP_ENV=prod to APP_ENV=dev in the .env.local file and transfer it to the server. Now you should get a stacktrace and explanation of what happened.

If you don't want the backup of the last version (generally unnecessary, because there are of course backups of the code in git), you can delete the directory on the server (wiagvokabulare/wiagstage). This might not work though, because the server sometimes owns files in the cache that you then can't delete.

## local WIAG on Windows 11 for testing
These instructions might be incomplete, but since they should still be quite helpful, it still makes sense to keep them.

- install XAMPP with the correct PHP version (can be found in the composer.json file, probably still: "php": ">=8.1")
- install [composer](https://getcomposer.org/)
- activate php extension: intl
- change PHP settings to allow the big import of data:
	- max_execution_time=5000
	- max_input_time=5000
	- memory_limit=512M
	- post_max_size=256M
	- upload_max_filesize=200M
	- to "xampp\phpmyadmin\config.inc.php" add this line: "$cfg['ExecTimeLimit'] = 6000;"
- start XAMPP as admin! (otherwise the database may randomly break)
- start apache and mysql server in XAMPP
- import data from WIAG
	- export data per phpMyAdmin from WIAG as .sql file
	- import into local database per phpMyAdmin (http://localhost/phpmyadmin/)
	- import generally takes around 30 minutes, but the time it takes varies vastly (10 to 80 minutes)
- create a .env.local file with content: 'APP_ENV=dev'
- composer install
- yarn install
- cd public
- yarn build
- cd ..
- symfony serve (starts local development server)
- now the local server should be reachable via https:localhost:8000

optional: install xdebug to be able to debug with e.g VSCodium (call php -i in a console -> paste it here: https://xdebug.org/wizard)
	
Notes:
- if something (with the frontend) does not work, even after undoing the changes, try: clearing the project cache (rmdir .\var\cache\* -Force) and the browser cache (remove cookies and clear cache for the page)
- always run XAMPP as admin, otherwise the database might break randomly
