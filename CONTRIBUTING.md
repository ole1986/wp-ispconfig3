## Requirements

* PHP version 5.X or 7.X
* Composer (https://getcomposer.org/download/)
* Visual Studio Code (https://code.visualstudio.com/)
* Visual Studio Code Extensions
    * PHP Sniffer (https://marketplace.visualstudio.com/items?itemName=wongjn.php-sniffer)


## Install Composer

Download composer and install as described in the provided link

## Install packages (required for development only)

Use the below to install neccessary composer packages.
So you can benefit from code validation and formating against PSR-2 and PSR-1 using `PHP Sniffer` extension

```
./composer.phar install
```

## Configure PHP Sniffer

To enable the PHP Sniffer extension it might be necessary to amend the `PHP Sniffer: Executables Folder` settings from Visual Studio Code preferences

Open the vscode preferences (GUI) and search for `PHP Sniffer: Executables Folder`.
Add the path `vendor/bin/` if `phpcs` and `phpcbf` is not globally registered


## Manually run PHP Sniffer

To validate all php files use the command `vendor/bin/phpcs .`
To beautify and format the files use `vendor/bin/phpcbf .`