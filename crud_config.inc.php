<?php
//----------------------------------------------------------
// Edit this file and rename to 'crud_config.php'
//
//----------------------------------------------------------
$pageTitle = 'title';


$dbServer = '';
$dbName = '';
$dbUser = '';
$dbPass = '';


$dbTables = array(
	'table1',
	'table2',
);


$dbForeignKeys = array(
	'table1' => array(
		'key1' => array('fkey1', 'table2', 'name')
	),
);


$dbColors = array(
	'table1' => array(
		'key1' => array('val1'=>'#rrggbb', 'val2'=>'#rrggbb')
	)
);

?>

