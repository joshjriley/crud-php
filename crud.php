<?php
/*-----------------------------------------------------------------------------------
crud.php - Utility mysql database create/read/update/delete class.

-----------------------------------------------------------------------------------*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once("crud_config.php");

echo "<script src='crud.js'></script>";
echo "<link rel='stylesheet' href='crud.css'></style>";

class CRUD
{
    
    function __construct()
    {
        global $dbServer, $dbName, $dbUser, $dbPass, $dbTables, $dbForeignKeys;
        global $scriptPath, $crudTitle;

        $this->dbServer = $dbServer;
        $this->dbName   = $dbName;
        $this->dbUser   = $dbUser;
        $this->dbPass   = $dbPass;
        $this->dbTables = $dbTables;
        $this->foreignKeys = $dbForeignKeys;
        $this->title    = $crudTitle;
    }


    function start()
    {
        $params = array_merge($_GET, $_POST);
        $this->showTableSelectForm($params);
        if (isset($params['cmd']))
        {
            if      ($params['cmd'] == 'query')        {$this->showQueryTableForm($params);}
            else if ($params['cmd'] == 'doQuery')      {$this->showQueryTableResults($params);}
            else if ($params['cmd'] == 'create')       {$this->showCreateRecordForm($params);}
            else if ($params['cmd'] == 'newRecord')    {$this->showNewRecordForm($params);}
            else if ($params['cmd'] == 'insertRecord') {$this->insertRecord($params);}
            else if ($params['cmd'] == 'editRecord')   {$this->showEditRecordForm($params);}
            else if ($params['cmd'] == 'updateRecord') {$this->updateRecord($params);}
        }
    }


    function showTableSelectForm($params)
    {
        $table = $params['table'];

        echo "<b>TABLE: </b>";
        echo "<form action='index.php' method='GET' style='margin:0; padding:0; display:inline;'>";
        echo "<select name='table' onchange='this.form.submit();'>";
        echo "<option disabled selected value> -- select an option -- </option>";
        foreach ($this->dbTables as $dbTable)
        {
            $selected = ($dbTable == $table) ? " selected " : '';
            echo "<option $selected>$dbTable</option>";
        }        
        echo "</select> ";
        echo "</form>";

        if ($table)
        {
            echo " &nbsp; <a href='index.php?table=$table&cmd=query'><button class='button1'>query table</button></a>";
            echo " &nbsp; <a href='index.php?table=$table&cmd=create'><button class='button1'>create record</button></a>";
        }
        echo "<hr>";
    }


    function showCreateRecordForm($params)
    {
        $table = $params['table'];
        $tableDesc = $this->getTableDesc($table, true);
        $pk = $this->getPrimaryKey($tableDesc);

        echo "<FORM method=POST action='index.php'>";
        echo '<table border=1>';
        echo "<tr bgcolor=#abcdef><td colspan=99 align=center><b>Create new '$table' record</b></td></tr>";
        foreach ($tableDesc as $index=>$value)
        {
            $title = $value['Field'];
            $type = $value['Type'];
            $formName = "insert_$title";

            if ($title == $pk || stristr($tableDesc[$index]['Extra'], 'CURRENT_TIMESTAMP')) {continue;}

            echo "<tr>";
            echo "<td align=right><strong>$title</strong></td>";
            echo "<td>";
            if ( $type == "text" || $type == "longtext" )
            {
                echo "<textarea rows=6 id='$formName' name='$formName' cols=40></textarea>";
            }
            else
            {
                echo "<input type='text' id='$formName' name='$formName' size=40>";
            }

            $fkey = false; $ftable = false; $foptions = false;
            if (array_key_exists($table, $this->foreignKeys) && array_key_exists($title, $this->foreignKeys[$table]))
            {
                $fkey   = $this->foreignKeys[$table][$title][0];
                $ftable = $this->foreignKeys[$table][$title][1];
                $fname  = $this->foreignKeys[$table][$title][2];
                $ddhtml = $this->getForeignKeyDropdownHtml($ftable, $fkey, $fname, $formName);
                echo $ddhtml;
            }

            echo "</td>";
            echo "<td align=left>$type</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<input type='hidden' name='table' value='$table'>";
        echo "<input type='hidden' name='cmd' value='insertRecord'>";
        echo "<br><input class='button1' type='submit' value='Submit'>";
        echo "</FORM>";
    }

    function getForeignKeyDropdownHtml($ftable, $fkey, $fname, $objName)
    {
        $query = "select $fkey, $fname from $ftable order by $fkey desc";
        $rows = $this->dbQuery($query);
        $html = '';
        $html .= "<select name='$objName' onchange='setForeignKeyVal(this, " . '"' . $objName . '"' . ");''>";
        $html .= "<option value=''>select foreign key...</option>";
        foreach ($rows as $row)
        {
            $val = $row[$fkey];
            $name = $row[$fname];
            $html .= "<option value='$val'>$val ($name)</option>";
        }
        $html .= "";
        return $html;
    }

    function insertRecord($params)
    {
        $table = $params['table'];
        $query = "INSERT INTO `$table` set";
        $i=0;
        foreach ($params as $label => $value)
        {
            if ($value == "") {continue;}
            if (substr($label, 0, 7) == "insert_") 
            {
                $name = substr($label, 7);
                if ($i++ > 0) {$query .= ', ';}
                $query .= " `$name`='$value' ";
            }
        }
        $result = $this->dbQuery($query);
        if (!$result) {print "<font color='#880000'>INSERT QUERY ERROR!</font><br>";}
        else          {print "<font color='#008800'>insert successful</font><br>";}
    }


    function showQueryTableForm($params)
    {
        $table = $params['table'];
        $tableDesc = $this->dbQuery("show full columns from $table");

        echo "<form action='index.php' name='dataform' method='post' style='margin:0; padding:0;'>";
        echo "<input type=hidden name=cmd value='doQuery'>";
        echo "<input type=hidden name=table value='".$table."'>";
        $this->showTableColumnSelect($tableDesc); 
        echo "<p>";
        $this->showTableQueryFields($tableDesc);
        echo "<p>";
        // $this->showOrderBy($tableDesc);
        // echo "<p>";
        echo "<input class='button1' type=submit value='Submit query'>";
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

    function showTableQueryFields($tableDesc)
    {
        echo "<table border=1>"; 
        echo "<tr bgcolor=#abcdef><td colspan=99 align=left><b>Enter search criteria (assume 'like' search):</b></td></tr>";
        reset ($tableDesc);
        $i = 0;
        foreach ($tableDesc as $index=>$value)
        {
            if ($i == 0) {echo "<tr>";}

            echo "<td bgcolor=#ffffee align=right>";        
            echo $value['Field'];
            echo ":&nbsp;&nbsp;</td>";
            echo "<td>";
            echo "<input name='TX".$value['Field']."' value=''></input>&nbsp;";     
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
        echo "</table>";
    }

    function showTableColumnSelect($tableDesc)
    {   
        echo "<table border=2>";   
        echo "<tr bgcolor=#abcdef><td colspan=99 align=left><b>Select columns to show:</b></td></tr>";

        reset ($tableDesc);
        $i = 0;
        $l = 0;
        foreach ($tableDesc as $index=>$value)
        {
            if ($i == 0) {echo "<tr>";}
            
            echo "<td bgcolor=#eeffff>";
            echo "<input type='checkbox' name='CB".$value['Field'];
            echo "'checked >&nbsp;".$value['Field']."</input>";
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
        echo "<input type=hidden name=table value='$table'>";

        $query = $this->buildQuery($params);
        $data = $this->dbQuery($query);
        $this->printQueryResults($data, $params);
    }


    function buildQuery($vars)
    {
        $qCols = "";
        $qWhere = "";
        $qOrderBy = "";
        $i = 0; 
        $j = 0;
        
        reset ($vars);
        foreach ($vars as $index=>$value)
        {
            $pre = substr ($index, 0, 2);
            if ($pre == "CB") 
            {
                $name = substr($index, 2);
                if ($i > 0) $qCols .= ",";
                $qCols .= $name;
                $i++;
            }
            else if ($pre == "TX")
            {
                if (strlen($value) <= 0) continue;
                $name = substr($index, 2);
                if ($j > 0) $qWhere .= " and ";
                $qWhere .=  $name . " rlike '(.)*" . addslashes(trim($value))."(.)*'";
                $j++;
            }
            else if ($index == "orderBy")
            {
                $qOrderBy = "order by " . addslashes($value);
            }
        }
        if (strlen ($qCols) <= 0) {$qCols = "*";}
        $qWhere = (strlen($qWhere) > 0) ? " where $qWhere " : "";

        $query = "select $qCols from $vars[table] $qWhere $qOrderBy";
        return $query;
    }


    function printQueryResults($results, $vars)
    {
        $table = $vars['table'];
        $tableDesc = $this->dbQuery("show full columns from $table");
        $pk = $this->getPrimaryKey($tableDesc);

        reset ($vars);
        $fields = array();
        $i=0;
        foreach ($vars as $index=>$value)
        {
            $pre = substr ($index, 0, 2);
            if ($pre == "CB") 
            {
                $name = substr ($index, 2);
                $fields[$i] = $name;
                $i++;
            }
        }

        echo "<b>Query results: </b><i>(click headers to sort)</i><p>";
        echo "<table id='queryResultsTable' border=1 cellpadding=3>";
        echo "<tr bgcolor=#eeeeee>";
        echo "<th></th>";
        foreach ($fields as $i=>$fld)
        {
            echo "<th style='cursor:pointer;' onclick='sortTable(".($i+1).", \"queryResultsTable\")'>$fld</th>";
        }

        $i = 1;
        foreach ($results as $row)
        {
            $id = $row[$pk];
            if ($i % 2 == 0) $bgcolor = "#eeffff";
            else             $bgcolor = "#ffffee";
            echo "<tr bgcolor=$bgcolor align=center>";
            ++$i;

            echo "<td><a href='index.php?table=$table&cmd=editRecord&recordId=$id'><button class='button1'>edit</button></a></td>";
            foreach ($fields as $fld)
            {
                $val = $row[$fld];
                echo "<td>$val";
            }
            echo "<br>";
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
        echo '<table border="1">';
        echo "<tr bgcolor=#abcdef><td colspan=99 align=center><b>Edit '$table' record #$id</b></td></tr>";
        foreach ($row as $col=>$value)
        {
            echo '<tr>';
            echo '<td bgcolor=ffffee align="right"><strong>'.$col.'</strong></td>';
            echo '<td>';

            if ($col == $pk || stristr($tableDesc[$col]['Extra'], 'CURRENT_TIMESTAMP')) 
            {
                echo $value;
                echo '<input type="hidden" name="'.$col.'" value="'.$value.'"><br />';
            }
            else 
            { 
                $type = $tableDesc[$col]['Type'];
                $inputname = "edit_$col";
                if ( $type == "text" || $type == "longtext" )
                    echo '<textarea rows="6" name="'.$inputname.'" cols="40">'.$value.'</textarea><br />';
                else
                    echo '<input type="text" name="'.$inputname.'" size="40" value="'.$value.'"><br />';

                echo '</td><td>';
                echo $tableDesc[$col]['Type'];
            }
            echo '</td>';
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
        $table = $params['table'];
        $recordId = $params['recordId'];

        $tableDesc = $this->dbQuery("show full columns from $table");
        $pk = $this->getPrimaryKey($tableDesc);

        $delete = $params['delete'];
        if ($delete == "delete")
        {
            $query = "DELETE from ".$table." WHERE $pk = ".$recordId;
            $this->dbQuery($query);
            echo "<font color=#882222>This record with $pk = ".$recordId." has been deleted!</font>";
            exit;
        }

        // Build and query database with the updates based on posted variables
        //TODO: make this one query
        $result = true;
        foreach ($params as $label => $value)
        {
            $pre = substr ($label, 0, 5);
            if ($pre == "edit_") 
            {
                $name = substr($label, 5);
                $query = "UPDATE $table SET $name = '$value' WHERE $pk = $recordId LIMIT 1";
                $res = $this->dbQuery($query);
                if (!$res) {$result = false;}
            }
        }    

        if (!$result) {print "<span style='background-color:#ff8888'>UPDATE QUERY ERROR</span><p>";}
        else          {print "<span style='background-color:#88ff88'>update successful</span><p>";}

        $this->showEditRecordForm($params);    
    }

    function dbQuery($query)
    {
        $this->dbConnect();
        $result = mysqli_query($this->dbConn, $query);
        if (!$result)
        {
            echo("Error description: " . mysqli_error());
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
