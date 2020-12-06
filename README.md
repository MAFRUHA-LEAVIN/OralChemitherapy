AntiCAppWebsite
===============

AntiC Website powered by Silex


# Installation
To install the AntiC website locally you will need the following:
- PHP 5.3+
- MySQL
- Apache
- Composer
- Ability to edit vhosts

### Step 1
Clone the AntiCAppWebsite repository to a folder on your machine that the web server has access to.

### Step 2
Download composer into the same folder using the following command: ```curl -sS https://getcomposer.org/installer | php```

### Step 3
Run composer using: ```php composer.phar install```
This will install the required libraries into the vendor directory.

### Step 4
You now need to setup a virtual host and a host entry. To do this find apache vhosts and add the following:
```bash
# AntiC Website
<VirtualHost *:8888>
    ServerName FAKE.URL.OFSITE
    DocumentRoot "/PATH/TO/ANTICAPPWEBSITE/web"
    DirectoryIndex index.php
    <Directory "/PATH/TO/ANTICAPPWEBSITE/web">
        Options All
        AllowOverride All
        Order allow,deny
        Allow from all
    </Directory>
</VirtualHost>
```
Note: /web is needed on the end for this to work, as /web is the directory that is accessible to the websites.

You will then need to add a host entry into your hosts file. To do this open your hosts and add the following:
```bash
127.0.0.1   FAKE.URL.OFSITE
```

After this you may need to clear your dns cache. From here you can now use a web browser and go to the name of the site: ```http://FAKE.URL.OFSITE:8888```
