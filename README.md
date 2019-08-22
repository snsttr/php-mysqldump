php-mysqldump
=============
This is a primitive php alternative to the `mysqldump` cli tool. It is possible to export tables (with all rows) and
views.

## Usage
You can configure php-mysqldump by config-file (`config.php`) and/or by calling it with the appropriate parameters.

### config.php
A description of all settings can be found in `config.php`. The settings of `config.php` are being ignored if you also
use parameters.

### Parameters
To call php-mysqldump with parameters use the following syntax:

    php mysqldump.php <hostname> <username> <password> <database> <port> <export-file> <log-file>
    
- You can fallback to a setting configured in `config.php` if you use an asterisk (`*`) at this position (see example
  below).
- in `export-file` and `log-file` you can use the placeholder `{FILENAME}` to let the script generate a filename. 

#### Example

    php mysqldump.php localhost root * test * "./backups/{FILENAME}.sql" "./backups/logs/last-export.log"

This backups the `test` Database from the MySQL Server running on `localhost` with the configured port in `config.php`.
The password is also being used from `config.php`. The export-file will be placed under `./backups/` with a generated
filename (`Y-m-d His <database>`, e.g. `2019-07-31 152129 test`). The log-file is being placed under
`./backups/logs/last-export.log`. 

## Hints
- The database user needs the  `SELECT` and `SHOW VIEW` privileges
- Currently it is **not** possible to export `FUNCTIONS`, `PROCEDURES` and `TRIGGERS`.

## How to use php-mysqldump with Craft CMS
If you are using Craft CMS but do not have a chance to access `mysqldump` you can use this script instead.
Just add the following line to your `craft/config/general.php` file (under global or a certain environment):

    'backupCommand' => 'php <path-to-script>/mysqldump/mysqldump.php" {server} {user} {password} {database} {port} "{file}" "<path-to-log-file>/{FILENAME}.log'
    
The attributes in curly brackets are automatically being replaced by Craft. You just have to change
`<path-to-script>` and `<path-to-log-file>` to the appropriate values.

## Bugs / Contribution
Please report bugs through github. Feel free to fork this repo or open pull requests on github.

## License
See [LICENSE.md](LICENSE.md)