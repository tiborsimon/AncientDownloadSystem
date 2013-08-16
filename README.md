#Ancient Download System

Ancient Download System is an easy to use lightweight PHP & MySQL based download system that allows you to share your stuff via the web.

####Main features:

* __Only accepts zip format__ ( yes, it's a feature: simple and even file system )
* __Upload files via browser or FTP__ ( with large files it could be handy )
* __Master password backend protection__ ( with password reset mechanism )
* __Automatic URL detection__
* __Statistics about the download__
* __Easy to use interface__
* __Customizable logo and favicon__

This project is using another project: <https://github.com/tiborsimon/AncientSession>



---

#How to install

1. Prepare your MySQL datadase information. _Database name, database location, username, password._ You can get this information from your web host.
2. Create a folder on your server that will contains every file for the system.  _Optionally you can create a subdomain that points to that folder (for example download.yourdomain.com)_
3. Upload all the files in this folder to that new directory.
4. Open setup.php in your web browser. _( in this case download.yourdomain.com/setup.php )_ 
5. Go through the installation steps.
6. You are ready to upload your files.

---

#Upload files

You have two upload methods to upload your files to the system: browser and FTP upload.

Browser upload allows you to upload files directly from your browser, but it may fail depending on your host or your internet connection. 

With FTP upload you can upload your files to the FILES folder via an FTP client, and then you can open files.php, which will detect the new files, and it will register them.

After you uploaded and registered a file, the system will give you a link for that file, which you can share whith the world.

---

#About the files
There are 5 main PHP files you should take care of:

* files.php	
* index.php
* setup.php	
* stats.php
* upload.php

---

###files.php
This script is responsible for listing and editing the downloadable files and for checking the databsa consistency ( no registered files are missing, all uploaded files are registered ).


###index.php
This is the only frontend file of the system. It expects one URL parameter that identifies the downloadable file.

###setup.php
This file is the first you should run on the server during installation. It installs the system on your server (creates the requierd files, folders, databse tables, security protection), handles the forgotten password issue, manages the settings of the system.

setup.php will create additional files and folders. ( _the name of the files are in hungarian for security reasons_ )

* __FILES__ folder - that contains all of your uploaded files. via FTP you should upload your files in that folder, otherwise, the system won't find them
* __.htaccess__ - this file will protect the sensitive files the system have to have
* __.adatbazis.hozzaadas__ - this file will keep track of the database connection information. The .htaccess file hides it from the outside world. It will be safe util your username and password for you FTP account is secure.

###stats.php
This file is used for the statistics. It can plot two type of statistics:
Statstics based on downloads: it will list all of the downloads ordered by the download server time. Statistics based on files: it will show how many times was each file downloaded.

###upload.php
This file handles all uploads. It can operate in two modes: browser and FTP upload mode.