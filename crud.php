<?php
/*-----------------------------------------------------------------------------------
crud.php - Utility mysql database create/read/update/delete class.

-----------------------------------------------------------------------------------*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("crud_config.php");


class CRUD
{
    
    function __construct()
    {
        global $dbServer, $dbName, $dbUser, $dbPass, $dbTables, $dbForeignKeys, $dbColors;
        global $scriptPath, $pageTitle;

        $this->dbServer = $dbServer;
        $this->dbName   = $dbName;
        $this->dbUser   = $dbUser;
        $this->dbPass   = $dbPass;
        $this->dbTables = $dbTables;
        $this->foreignKeys = $dbForeignKeys;
        $this->dbColors = $dbColors;
        $this->pageTitle    = $pageTitle;
    }


    function start()
    {
        $this->checkSessionTimeout();

        $params = array_merge($_GET, $_POST);
        if (isset($params['cmd']))
        {
            if      ($params['cmd'] == 'query')        {$this->showQueryTableForm($params);}
            else if ($params['cmd'] == 'doQuery')      {$this->showQueryTableResults($params);}
            else if ($params['cmd'] == 'create')       {$this->showCreateRecordForm($params);}
            else if ($params['cmd'] == 'newRecord')    {$this->showNewRecordForm($params);}
            else if ($params['cmd'] == 'insertRecord') {$this->insertRecord($params);}
            else if ($params['cmd'] == 'editRecord')   {$this->showEditRecordForm($params);}
            else if ($params['cmd'] == 'updateRecord') {$this->updateRecord($params);}
            else if ($params['cmd'] == 'selectTable')  {$this->showQueryTableForm($params);}
        }
        else 
        {
            $this->resetSessionVars();
            $this->showQueryTableForm($params);
        }
    }


    function checkSessionTimeout()
    {
        $timeout = 60 * 60 * 24 * 5; 
        if (isset($_SESSION['timeout'])) {
            $duration = time() - (int)$_SESSION['timeout'];
            if($duration > $timeout) {
                session_destroy();
                session_start();
            }
        }
        $_SESSION['timeout'] = time();        
    }


    function resetSessionVars()
    {
        session_destroy();
        session_start();

        $_SESSION['params'] = array();
        foreach ($this->dbTables as $table)
        {
            $_SESSION['params'][$table] = array();
        }
    }


    function printPageHeader()
    {
        echo "<script src='crud.js?v=" . date('Y-m-d') . "'></script>";
        echo "<link rel='stylesheet' href='crud.css?v=" . date('Y-m-d') . "'></style>";  
    }


    function showTableSelectForm($params)
    {
        $table = (isset($params['table'])) ? $params['table'] : false;

        echo "<table cellpadding=3><tr>";
        echo "<td style='background-color:#bbbbbb; border: 1px solid black'>";
        echo "<a href='index.php' style='text-decoration:none'>".$this->pageTitle."</a>";
        echo "</td>";

        echo "<form action='index.php' method='POST' style='margin:0; padding:0; display:inline;'>";
        echo "<input type='hidden' name='cmd' value='selectTable'>";
        echo "<td><b>TABLE: </b><select name='table' onchange='this.form.submit();'>";
        echo "<option disabled selected value> -- select an option -- </option>";
        foreach ($this->dbTables as $dbTable)
        {
            $selected = ($dbTable == $table) ? " selected " : '';
            echo "<option $selected>$dbTable</option>";
        }        
        echo "</select></td>";
        echo "</form>";

        if ($table)
        {
            echo "<td><a href='index.php?table=$table&cmd=query'><button class='button1'>query table</button></a></td>";
            echo "<td><a href='index.php?table=$table&cmd=create'><button class='button1'>create record</button></a></td>";
        }
        echo "</tr></table>";
        echo "<hr>";
    }


    function showCreateRecordForm($params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        $table = $params['table'];
        $tableDesc = $this->getTableDesc($table, true);
        $pk = $this->getPrimaryKey($tableDesc);

        echo "<FORM method=POST action='index.php'>";
        echo '<table border=1 bgcolor=#dddddd>';
        echo "<tr bgcolor=#abcdef><td colspan=99 align=center><b>Create new '$table' record</b></td></tr>";
        foreach ($tableDesc as $col=>$value)
        {
            $col = $value['Field'];
            $type = $value['Type'];
            $typeDisp = $type;
            $inputName = "insert_$col";

            if (   $col == $pk 
                || stristr($tableDesc[$col]['Extra'], 'CURRENT_TIMESTAMP')
                || stristr($tableDesc[$col]['Default'], 'CURRENT_TIMESTAMP')
            ) {continue;}

            echo "<tr>";
            echo "<td align=right><strong>$col</strong></td>";
            echo "<td>";
            if (array_key_exists($table, $this->foreignKeys) && array_key_exists($col, $this->foreignKeys[$table]))
            {
                $this->addForeignKeySelector($table, $col, $inputName);   
            }
            else if (stristr($type, 'enum('))
            {
                $this->addEnumSelector($table, $tableDesc, $col, $inputName);
                $typeDisp = 'enum';
            }
            else if ( $type == "text" || $type == "longtext" )
            {
                echo "<textarea rows=6 id='$inputName' name='$inputName' cols=60></textarea>";
            }
            else if ($type == 'date')
            {
                echo "<input type='date' id='$inputName' name='$inputName' size=40>";
            }
            else
            {
                echo "<input type='text' id='$inputName' name='$inputName' size=40>";
            }
            echo "</td>";
            echo "<td align=left>$typeDisp</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<input type='hidden' name='table' value='$table'>";
        echo "<input type='hidden' name='cmd' value='insertRecord'>";
        echo "<br><input class='button1' type='submit' value='Submit'>";
        echo "</FORM>";
    }

    function addEnumSelector($table, $tableDesc, $col, $inputName, $value=null)
    {
        $type = $tableDesc[$col]['Type'];
        preg_match_all('~\'(.*?)\'~', $type, $out);
        echo $this->getDropdownHtml($inputName, $out[1], null, $value, 'select...');
    }

    function addForeignKeySelector($table, $col, $inputName)
    {
        $fkey   = $this->foreignKeys[$table][$col][0];
        $ftable = $this->foreignKeys[$table][$col][1];
        $fname  = $this->foreignKeys[$table][$col][2];

        $query = "select $fkey, $fname from $ftable order by $fkey desc";
        $rows = $this->dbQuery($query);
        $options = array();
        $values = array();
        foreach ($rows as $row)
        {
            $options[] = $row[$fkey] . ' ('.$row[$fname].')';
            $values[]  = $row[$fkey];
        }
        echo $this->getDropdownHtml($inputName, $options, $values, null, 'select foreign key...');
    }

    function getDropdownHtml($inputName, $options, $values=null, $value=null, $defaultText=null)
    {
        $html = '';
        $html .= "<select name='$inputName'>";
        if ($defaultText) $html .= "<option value=''>$defaultText</option>";
        foreach ($options as $i=>$option)
        {
            $val = ($values) ? $values[$i] : $option;
            $selected = ($value != null and $value == $val) ? " selected " : '';
            $html .= "<option $selected value='$val'>$option</option>";
        }
        $html .= "";
        return $html;
    }


    function insertRecord($params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        $table = $params['table'];
        $query = "INSERT INTO `$table` set ";
        $sets = array();
        foreach ($params as $label => $value)
        {
            if ($value == "") {continue;}
            $value = htmlentities($value, ENT_QUOTES, 'UTF-8');
            if (substr($label, 0, 7) == "insert_") 
            {
                $col = substr($label, 7);
                $sets[] = "$col = '$value'";
            }
        }
        $query .= implode(", ", $sets);

        $result = $this->dbQuery($query);
        if (!$result) {print "<font color='#880000'>INSERT QUERY ERROR!</font><p>";}
        else          {print "<font color='#008800'>insert successful</font><p>";}

        echo "<li><a href='index.php?table=$table&cmd=doQuery&saved=1'>last query result</a>";
    }


    function showQueryTableForm($params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        if (!isset($params['table'])) return;

        $table = $params['table'];
        $tableDesc = $this->getTableDesc($table, true);

        echo "<form action='index.php' name='dataform' method='post' style='margin:0; padding:0;'>";
        echo "<input type=hidden name=cmd value='doQuery'>";
        echo "<input type=hidden name=table value='".$table."'>";
        $this->showTableColumnSelect($table, $tableDesc); 
        echo "<p>";
        $this->showTableQueryFields($table, $tableDesc);
        echo "<p>";
        // $this->showOrderBy($tableDesc);
        // echo "<p>";
        echo "<input type='checkbox' name='export' value='export'/> Export to file &nbsp; ";
        echo "<input type='button' value='clear form' onclick='clearForm(this.form);'> &nbsp; ";
        echo "<p><input class='button1' type=submit value='Submit query'>";
        echo "</form>";
    }


    function showOrderBy($tableDesc)
    {
        echo "<p><b>Sort by: </b> ";
        echo "<select name='orderBy'>";
        foreach ($tableDesc as $index=>$value)
        {
            echo "<option value='".$value['Field']."'>";
            echo $value['Field'];
            echo "</option>";
        }   
        echo "</select>";
    }


    function getSavedSessionVal($table, $key)
    {
        $val = null;
        if (array_key_exists($key, $_SESSION['params'][$table])) $val = $_SESSION['params'][$table][$key];
        return $val;
    }


    function showTableQueryFields($table, $tableDesc)
    {
        echo "<table border=1 bgcolor=#dddddd>"; 
        echo "<tr bgcolor=#abcdef><td colspan=99 align=left><b>Enter search criteria (assume 'like' search):</b></td></tr>";
        $i = 0;
        foreach ($tableDesc as $index=>$desc)
        {
            $col  = $desc['Field'];
            $type = $desc['Type'];
            $inputName = "TX".$col;
            $value = $this->getSavedSessionVal($table, $inputName);

            if ($i == 0) {echo "<tr>";}

            echo "<td align=right>$col:&nbsp;&nbsp;</td>";
            echo "<td>";
            if (array_key_exists($table, $this->foreignKeys) && array_key_exists($col, $this->foreignKeys[$table]))
            {
                $this->addForeignKeySelector($table, $col, $inputName);   
            }
            else if (stristr($type, 'enum('))
            {
                $this->addEnumSelector($table, $tableDesc, $col, $inputName, $value);   
            }
            else
            {
                echo "<input id='$inputName' name='$inputName' value='$value'></input>&nbsp;"; 
            }
            echo "</td>";       

            if ($i == 1) {echo "</tr>\n";}
            $i++;
            if ($i >= 2) $i = 0;
        }
        if ($i != 0)
        {
            while ($i < 2)
            {
                echo "<td>&nbsp;</td>";
                $i++;
            }
            echo "</tr>";
        }

        $value = $this->getSavedSessionVal($table, 'customWhere');
        echo "<tr><th>custom where:</th><td colspan=99><input id='customWhere' name='customWhere' value='$value' size=60></input></td></tr>";

        $value = $this->getSavedSessionVal($table, 'searchAll');
        echo "<tr><th>search all:</th><td colspan=99><input id='searchAll' name='searchAll' value='$value' size=60></input></td></tr>";

        echo "</table>";
    }


    function showTableColumnSelect($table, $tableDesc)
    {   
        echo "<table border=2>";   
        echo "<tr bgcolor=#abcdef><td colspan=99 align=left>";
        echo "<b>Select columns to show:</b>";
        echo " &nbsp; <input type='checkbox' checked onchange='checkAll(this);' ";
        echo "</td></tr>";

        reset ($tableDesc);
        $i = 0;
        $l = 0;
        foreach ($tableDesc as $index=>$value)
        {
            if ($i == 0) {echo "<tr>";}
            
            $id = "CB".$value['Field'];
            $checked = $this->getSavedSessionVal($table, $id);
            $checked = (empty($_SESSION['params'][$table]) || $checked != null) ? ' checked ' : '';
            echo "<td bgcolor=#eeffff>";
            echo "<input type='checkbox' name='$id' id='$id' ";
            echo " $checked >&nbsp;".$value['Field']."</input>";
            echo "</td>";       

            if ($i == 4)
            {
                echo "</tr>\n";
                $l++;
            }
            $i++;
            if ($i >= 5) $i = 0;
        }
        
        if ($i != 0 && $l > 0)
        {
            while ($i < 5)
            {
                echo "<td>&nbsp;</td>";
                $i++;
            }
            echo "</tr>";
        }
        echo "</table>";
    }


    function showQueryTableResults($params)
    {
        $table = $params['table'];
        if (isset($params['saved'])) $params = $_SESSION['params'][$table];
        else                         $_SESSION['params'][$table] = $params;

        $query = $this->buildQuery($params);
        $data = $this->dbQuery($query);
        if (isset($params['export'])) {$this->exportQueryResults($data, $params);}
        else                          {$this->printQueryResults($data, $params);}
    }


    function buildQuery($params)
    {
        $table = $params['table'];
        $tableDesc = $this->getTableDesc($table, true);

        $qCols = "";
        $qWhere = "";
        $qOrderBy = "";
        $i = 0; 
        $j = 0;

        foreach ($params as $key=>$value)
        {
            $pre = substr ($key, 0, 2);
            if ($pre == "CB") 
            {
                $name = substr($key, 2);
                if ($i > 0) $qCols .= ",";
                $qCols .= $name;
                $i++;
            }
            else if ($pre == "TX" || $key == 'customWhere' || $key == 'searchAll')
            {
                if (strlen($value) <= 0) continue;
                if ($j > 0) $qWhere .= " and ";
                if ($key == 'customWhere')
                {
                    $qWhere .= addslashes(trim($value));
                }
                else if ($key == 'searchAll')
                {
                    $tmp = array();
                    foreach ($tableDesc as $col=>$foo)
                        $tmp[] = "$col like '%$value%'";
                    $qWhere .= '(' . implode(' or ', $tmp) . ')';
                }
                else
                {
                    $name = substr($key, 2);
                    $type = (array_key_exists($name, $tableDesc)) ? $tableDesc[$name]['Type'] : false;
                    $isEnum = ($type && stristr($type, 'enum(')) ? true : false;
                    if      ($isEnum)   $qWhere .= $name . " = '" . addslashes(trim($value))."'";
                    else                $qWhere .= $name . " like '%" . addslashes(trim($value))."%'";
                }
                $j++;
            }
            else if ($key == "orderBy")
            {
                $qOrderBy = "order by " . addslashes($value);
            }
        }
        if (strlen ($qCols) <= 0) {$qCols = "*";}
        $qWhere = (strlen($qWhere) > 0) ? " where $qWhere " : "";

        $query = "select $qCols from $params[table] $qWhere $qOrderBy";
        print "q: $query<br>";
        return $query;
    }


    function getCheckedFields($params)
    {
        reset ($params);
        $fields = array();
        $i=0;
        foreach ($params as $index=>$value)
        {
            $pre = substr ($index, 0, 2);
            if ($pre == "CB") 
            {
                $name = substr ($index, 2);
                $fields[$i] = $name;
                $i++;
            }
        }
        return $fields;        
    }


    function exportQueryResults($results, $params)
    {
        $file = $params['table'] . "-" . date("Y-m-d-H-m-s") . '.txt';
        header("Content-type:text/csv");
        header("Content-disposition: attachment; filename=$file");

        $fields = $this->getCheckedFields($params);
        foreach ($results as $row)
        {
            foreach ($fields as $i=>$fld)
            {
                $val = $row[$fld];
                if ($i > 0) echo "\t";
                echo "$val";
            }
            echo "\n";
        }
    }


    function printQueryResults($results, $params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        $table = $params['table'];
        $tableDesc = $this->getTableDesc($table, true);
        $pk = $this->getPrimaryKey($tableDesc);

        $fields = $this->getCheckedFields($params);

        echo "<input type=hidden name=table value='$table'>";
        echo "<b>Query results: </b><i>(click headers to sort)</i><p>";
        echo "<table id='queryResultsTable' class='queryResultsTable'>";
        echo "<tr bgcolor=#dddddd>";
        echo "<th></th>";
        foreach ($fields as $i=>$fld)
        {
            echo "<th style='cursor:pointer;' onclick='sortTable(".($i+1).", \"queryResultsTable\")'>$fld</th>";
        }

        $i = 1;
        foreach ($results as $row)
        {
            $id = $row[$pk];
            echo "<tr align=center>";
            ++$i;

            echo "<td><a href='index.php?table=$table&cmd=editRecord&recordId=$id'><button class='button1'>edit</button></a></td>";
            foreach ($fields as $fld)
            {
                $val = $row[$fld];
                $val = htmlspecialchars_decode($val);
                $bgcolor = '';
                if (   array_key_exists($table, $this->dbColors) 
                    && array_key_exists($fld, $this->dbColors[$table])
                    && array_key_exists($val, $this->dbColors[$table][$fld])) {$bgcolor = 'bgcolor='.$this->dbColors[$table][$fld][$val];}
                $style = ($tableDesc[$fld]['Type'] == 'text') ? ' style="max-width:400px;" ' : '';
                echo "<td $bgcolor $style>$val</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }


    function getPrimaryKey($tableDesc)
    {
        foreach ($tableDesc as $td)
        {
            if ($td['Key'] == "PRI") return $td['Field'];
        }
        return false;
    }


    function getTableDesc($table, $hash=false)
    {
        $tableDesc = $this->dbQuery("show full columns from $table");
        if ($hash)
        {
            $newTableDesc = array();
            foreach ($tableDesc as $td)
            {
                $newTableDesc[$td['Field']] = $td;
            }
            $tableDesc = $newTableDesc;
        }
        return $tableDesc;
    }


    function showEditRecordForm($params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        $table  = $params['table'];
        $recordId = $params['recordId'];

        //get table desc
        $tableDesc = $this->getTableDesc($table, true);
        $pk = $this->getPrimaryKey($tableDesc);

        //  Query database for existing data
        $query = "SELECT * FROM $table WHERE $pk = $recordId limit 1";
        $data = $this->dbQuery($query);
        if (!$data || count($data) == 0)
        {
            echo 'These data do not exist.  Please try a different ID.';
            exit;
        }
        $row = $data[0];
        $id = $row[$pk];

        //  Display current state of dataset & create text fields based on description for modifying the dataset
        echo "<FORM method='post' action='index.php' style='margin:0; padding:0;'>";
        echo "<input type=hidden name=cmd value='updateRecord'>";
        echo "<input type=hidden name=recordId value='$id'>";
        echo "<input type=hidden name=table value='$table'>";
        echo '<table border="1" bgcolor=#dddddd>';
        echo "<tr bgcolor=#abcdef><td colspan=99 align=center><b>Edit '$table' record #$id</b></td></tr>";
        foreach ($row as $col=>$value)
        {
            $type = $tableDesc[$col]['Type'];
            $typeDisp = $type;

            echo '<tr>';
            echo '<td align="right"><strong>'.$col.'</strong></td>';
            echo '<td>';
            if (   $col == $pk 
                || stristr($tableDesc[$col]['Extra'], 'CURRENT_TIMESTAMP')
                || stristr($tableDesc[$col]['Default'], 'CURRENT_TIMESTAMP'))
            {
                echo $value;
                if ($col == $pk) echo '<input type="hidden" name="'.$col.'" value="'.$value.'"><br>';
            }
            else 
            { 
                $inputName = "edit_$col";
                if (array_key_exists($table, $this->foreignKeys) && array_key_exists($col, $this->foreignKeys[$table]))
                {
                    $this->addForeignKeySelector($table, $col, $inputName);   
                }
                else if (stristr($type, 'enum('))
                {
                    $this->addEnumSelector($table, $tableDesc, $col, $inputName, $value);
                    $typeDisp = 'enum';
                }
                else if ($type == 'date')
                {
                    echo "<input type='date' id='$inputName' name='$inputName' size=40 value='$value'>";
                }
                else if ( $type == "text" || $type == "longtext" )
                {
                    echo "<textarea rows=6 name='$inputName' id='$inputName' cols=60>$value</textarea>";
                }
                else
                {
                    echo "<input type='text' name='$inputName' id='$inputName' size=40 value='$value'>";
                }

            }
            echo '</td>';
            echo "<td>$typeDisp</td>";
            echo '</tr>';
        }

        echo '</table>';

        // Create a checkbox to allow deletion of a record
        echo '<input type="checkbox" name="delete" value="delete"/> Delete this record - permanently<br>';

        // Submit button
        echo "<input type='hidden' name='table' value='$table'><br />";
        echo "<input class='button1' type='submit' value='Update'> &nbsp; ";
        echo "</FORM>";
    }


    function updateRecord($params)
    {
        $this->printPageHeader();
        $this->showTableSelectForm($params);

        $table = $params['table'];
        $recordId = $params['recordId'];

        $tableDesc = $this->getTableDesc($table, true);
        $pk = $this->getPrimaryKey($tableDesc);

        if (isset($params['delete']))
        {
            $query = "DELETE from ".$table." WHERE $pk = ".$recordId;
            $res = $this->dbQuery($query);
            if ($res) echo "<font color=#882222>This record with $pk = ".$recordId." has been deleted!</font>";
            exit;
        }

        // Build and query database with the updates based on posted variables
        $result = true;
        $query = "update $table set ";
        $sets = array();
        foreach ($params as $label => $value)
        {
            $pre = substr ($label, 0, 5);
            if ($pre == "edit_") 
            {
                $col = substr($label, 5);
                $type = $tableDesc[$col]['Type'];
                $isAlpha = (stristr($type, 'varchar') || stristr($type, 'text')) ? true : false;
                $value = htmlentities($value, ENT_QUOTES, 'UTF-8');
                if ($value == '' && !$isAlpha) $sets[] = "$col = NULL";
                else                           $sets[] = "$col = '$value'";
            }
        }    
        $query .= implode(", ", $sets);
        $query .= " WHERE $pk = $recordId LIMIT 1";

        $res = $this->dbQuery($query);
        if (!$res) {print "<span style='background-color:#ff8888'>UPDATE QUERY ERROR</span><p>";}
        else       {print "<span style='background-color:#88ff88'>successfully updated record #$recordId</span><p>";}

        echo "<li><a href='index.php?table=$table&cmd=editRecord&recordId=$recordId'>edit record #$recordId</a>";
        echo "<li><a href='index.php?table=$table&cmd=doQuery&saved=1'>last query result</a>";
    }


    function dbQuery($query)
    {
        $this->dbConnect();

        $result = mysqli_query($this->dbConn, $query);
        if (!$result)
        {
            echo("Error description: " . mysqli_error($this->dbConn));
            return false;
        }

        $words = explode(" ", trim($query));
        $word1 = strtolower($words[0]);
        if (in_array($word1, array('select', 'describe', 'show')))
        {
            $rows = array();
            while ($row = mysqli_fetch_assoc($result)) {$rows[] = $row;}
            $result = $rows;
        }
        $this->dbClose();
        return $result;
    }


    function dbConnect()
    {
        $this->dbConn = mysqli_connect($this->dbServer, $this->dbUser, $this->dbPass, $this->dbName);
        if (mysqli_connect_errno()) 
        {
            echo "db connect error!<p>";
            echo "Debugging errno: " . mysqli_connect_errno() . PHP_EOL;
            exit;
        }
    }

    function dbClose()
    {
        mysqli_close($this->dbConn);
    }
}

?>
