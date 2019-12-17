<?php
/*-----------------------------------------------------------------------------------
crud.php - Utility mysql database create/read/update/delete class.

-----------------------------------------------------------------------------------*/
include_once("crud_config.php");


?>
<script>
function setForeignKeyVal(dd, objName)
{
    var val = dd.value;
    var input = document.getElementById(objName);
    input.value = val;
}
</script>
<?php 

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
        $this->scriptPath = $scriptPath;
        $this->title    = $crudTitle;
    }


    function start()
    {
        $params = array_merge($_GET, $_POST);
        $this->showTableSelectForm($params);
        if ($params['cmd'])
        {
            if      ($params['cmd'] == 'tableSelect')  {$this->selectTableAction($params);}
            else if ($params['cmd'] == 'newRecord')    {$this->showNewRecordForm($params);}
            else if ($params['cmd'] == 'insertRecord') {$this->insertRecord($params);}
            else if ($params['cmd'] == 'tableQuery')   {$this->showTableQueryResults($params);}
            else if ($params['cmd'] == 'editRecord')   {$this->showEditRecordForm($params);}
            else if ($params['cmd'] == 'updateRecord') {$this->updateRecord($params);}
        }
    }


    function showTableSelectForm($params)
    {
        $dbTable = $params['dbTable'];

        echo "<b>Select table: </b>";
        echo "<form action='$this->scriptPath' method='POST' style='margin:0; padding:0; display:inline;'>";
        echo "<select name='dbTable'>";
        foreach ($this->dbTables as $table)
        {
            $selected = ($table == $dbTable) ? " selected " : '';
            echo "<option $selected>$table</option>";
        }        
        echo "</select> ";
        echo "<input type='submit' name='queryForm' value='Query Form'>";
        echo "<input type='submit' name='newRecord' value='Insert New'>";
        echo "<input type='hidden' name='cmd' value='tableSelect'>";
        echo "</form>";
        echo "<hr>";
    }


    function selectTableAction($params)
    {
        if      ($params['queryForm']) {$this->showTableQueryForm($params);}
        else if ($params['newRecord']) {$this->showNewRecordForm($params);}
    }


    function showNewRecordForm($params)
    {
        $dbTable = $params['dbTable'];
        $tableDesc = $this->dbQuery("show full columns from $dbTable");
        echo "<FORM method=POST action='$this->scriptPath'>";
        echo "<h2>New Element for the '$dbTable' table</h2>";
        echo '<table border=1>';

        while( list($index, $value) = each ($tableDesc) )
        {
            $title = $value['Field'];
            $type = $value['Type'];
            $formName = "insert_$title";

            if ($title == "id"  || $title == "modDate") {continue;}

            echo "<tr>";
            echo "<td align=right><strong>$title</strong></td>";
            //todo: move skips to config
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
            if (array_key_exists($dbTable, $this->foreignKeys) && array_key_exists($title, $this->foreignKeys[$dbTable]))
            {
                $fkey   = $this->foreignKeys[$dbTable][$title][0];
                $ftable = $this->foreignKeys[$dbTable][$title][1];
                $fname  = $this->foreignKeys[$dbTable][$title][2];
                $ddhtml = $this->getForeignKeyDropdownHtml($ftable, $fkey, $fname, $formName);
                echo $ddhtml;
            }

            echo "</td>";
            echo "<td align=left>$type</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<input type='hidden' name='dbTable' value='$dbTable'>";
        echo "<input type='hidden' name='cmd' value='insertRecord'>";
        echo "<br><input type='submit' value='Submit'>";
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
        $dbTable = $params['dbTable'];
        $query = "INSERT INTO `$dbTable` set";
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
        if (!$result) {print "<font color='#880000'>INSERT QUERY ERROR!<br>";}
        else          {print "<font color='#008800'>insert successful<br>";}
    }


    function showTableQueryForm($params)
    {
        $dbTable = $params['dbTable'];
        $tableDesc = $this->dbQuery("show full columns from $dbTable");

        echo "<form action='$this->scriptPath' name='dataform' method='post' style='margin:0; padding:0;'>";
        echo "<input type=hidden name=cmd value='tableQuery'>";
        echo "<input type=hidden name=table value='".$dbTable."'>";
        $this->showTableColumnSelect($tableDesc); 
        echo "<hr>";
        $this->showTableQueryFields($tableDesc);
        echo "<hr>";
        $this->showOrderBy($tableDesc);
        echo "<hr>";
        echo "<input type=submit value='Submit query'>";
        echo "&nbsp;&nbsp;&nbsp;";
        echo "<input type=reset value='Clear Form'>";
        echo "</form>";
    }


    function showOrderBy($tableDesc)
    {
        echo "<p><b>Sort by: </b> ";
        echo "<select name='orderBy'>";
        while (list ($index, $value) = each ($tableDesc))
        {
            echo "<option value='".$value['Field']."'>";
            echo $value['Field'];
            echo "</option>";
        }   
        echo "</select>";
    }

    function showTableQueryFields($tableDesc)
    {
        echo '<p><b>Enter search criteria (assume "like" search):</b><br>';
        echo "<table border=1>"; 
        reset ($tableDesc);
        $i = 0;
        while (list ($index, $value) = each ($tableDesc))
        {
            if ($i == 0) {echo "<tr>";}

            echo "<td align=right>";        
            echo $value['Field'];
            echo ":&nbsp;&nbsp;</td><td>";
            echo "<input name='TX".$value['Field']."' value=''></input>&nbsp;&nbsp;";     
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
        echo "<p><b>Select columns to show:</b><br>";
        echo "<table border=2>";   

        reset ($tableDesc);
        $i = 0;
        $l = 0;
        while (list ($index, $value) = each ($tableDesc))
        {
            if ($i == 0) {echo "<tr>";}

            if (($i + $l) % 2 == 0) $color='#FFFFEE'; else $color='#EFFFFF';
            
            echo "<td bgcolor='".$color."'>";
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


    function showTableQueryResults($params)
    {
        $dbTable = $params['dbTable'];
        echo "<input type=hidden name=dbTable value='$dbTable'>";

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
        while (list ($index, $value) = each($vars))
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
        $dbTable = $vars['table'];

        reset ($vars);
        $fields = array();
        $i=0;
        while (list ($index, $value) = each($vars))
        {
            $pre = substr ($index, 0, 2);
            if ($pre == "CB") 
            {
                $name = substr ($index, 2);
                $fields[$i] = $name;
                $i++;
            }
        }

        echo "<b>Query results: <br>";
        echo "<table border=1 cellpadding=3>";
        echo "<tr bgcolor=#eeeeee>";
        echo "<th>";
        foreach ($fields as $fld)
        {
            echo "<th>".$fld;
        }

        $i = 1;
        foreach ($results as $row)
        {
            if ($i % 2 == 0) $bgcolor = "#eeffff";
            else             $bgcolor = "#ffffee";
            echo "<tr bgcolor=$bgcolor align=center>";
            ++$i;

            echo "<td><form action='$this->scriptPath' method='POST' style='margin:0; padding:0;'>";
            echo "<input type=hidden name=cmd value='editRecord'>";
            echo "<input type=hidden name=recordId value='$row[id]'>";
            echo "<input type=hidden name=dbTable value='$dbTable'>";
            echo "<input type=submit value='edit'>";
            echo "</form>";

            foreach ($fields as $fld)
            {
                $id = $row[id];               
                $val = $row[$fld];
                echo "<td>$val";
            }
            echo "<br>";
        }
        echo "</table>";
    }


    function showEditRecordForm($params)
    {
        $dbTable  = $params['dbTable'];
        $recordId = $params['recordId'];

        //get table desc
        $tableDesc = $this->dbQuery("show full columns from $dbTable");
        foreach ($tableDesc as $td)
        {
            $tableDesc[$td['Field']] = $td;
        }

        //  Query database for existing data
        $query = "SELECT * FROM $dbTable WHERE id = $recordId";
        $data = $this->dbQuery($query);
        if (!$data || count($data) == 0)
        {
            echo 'These data do not exist.  Please try a different ID.';
            exit;
        }
        $row = $data[0];

        //  Display current state of dataset & create text fields based on description for modifying the dataset
        echo "<FORM method='post' action='$this->scriptPath' style='margin:0; padding:0;'>";
        echo "<input type=hidden name=cmd value='updateRecord'>";
        echo "<input type=hidden name=recordId value='$row[id]'>";
        echo "<input type=hidden name=dbTable value='$dbTable'>";
        echo '<table border="1">';
        $i = 0;
        while( list($title, $value) = each ($row) )
        {
            $i++;

            echo '<tr><td align="right">';
            echo '<strong>'.$title.'</strong>';
            echo '</td><td>';

            if ($title == "ID" || $title == "LastUpdatedTime" )
            {
                echo $value;
                echo '<input type="hidden" name="'.$title.'" value="'.$value.'"><br />';
            }
            else 
            { 
                $type = $tableDesc[$title]['Type'];
                $inputname = "edit_$title";
                if ( $type == "text" || $type == "longtext" )
                    echo '<textarea rows="6" name="'.$inputname.'" cols="30">'.$value.'</textarea><br />';
                else
                    echo '<input type="text" name="'.$inputname.'" size="40" value="'.$value.'"><br />';

                echo '</td><td>';
                echo $tableDesc[$title]['Type'];
            }

            echo '</td></tr>';
        }

        echo '</table>';

        //
        // Create a checkbox to allow deletion of a record
        echo '<input type="checkbox" name="delete" value="delete"/>Delete this record - permanently';


        //
        // Be sure to pass on the selected table
        //
        echo '<input type="hidden" name="table" value="'.$table.'"><br />';
        echo "<p><hr>Be sure to double-check all fields & ONLY hit 'Update' once! <br>";
        echo '<input type="submit" value="Update"><input type="reset" value="Reset"></p>';
        echo "</FORM>";

    }

    function updateRecord($params)
    {
        $dbTable = $params['dbTable'];
        $recordId = $params['recordId'];

        $delete = $params['delete'];
        if ($delete == "delete")
        {
            $query = "DELETE from ".$table." WHERE id = ".$recordId;
            $this->dbQuery($query);
            echo "This record with ID = ".$record." has been deleted";
            exit;
        }

        // Build and query database with the updates based on posted variables
        foreach ($params as $label => $value)
        {
            $pre = substr ($label, 0, 5);
            if ($pre == "edit_") 
            {
                $name = substr($label, 5);
                //todo: skip some? put in config
                //todo: put 'id' in config
                $query = "UPDATE $dbTable SET $name = '$value' WHERE id = $recordId LIMIT 1";
                print "update query: $query<br>";
                $this->dbQuery($query);
            }
        }    

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
        $word1 = $words[0];
        if (in_array($word1, array('select', 'describe', 'show')))
        {
            $rows = array();
            while ($row = mysqli_fetch_assoc($result)) {$rows[] = $row;}
            $result = $rows;
        }
        mysqli_close();
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
