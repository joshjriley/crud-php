# crud-php
Create/Read/Update/Delete module for php and mysql

This application is a simple bare-bones database table manager (ie a poor man's phpMyAdmin) with two goals in mind:
- Must be completely table independent and fully configurable from a config file.
- Add needed bells and whistles that are typically not found in off the shelf DB admin web tools.


## Setup
- Copy crud_config.inc.php to crud_config.php
- Edit crud_config.php to include:
    - DB connect info
    - DB table list
    - Foreign key mappings
    - Other optional configurable features (ie cell highlighting)
    
    
## Status
- Quick first prototype done.  See aa-dev-notes.md for list of TODOs and ideas.
