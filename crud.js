function setForeignKeyVal(dd, objName)
{
    var val = dd.value;
    var input = document.getElementById(objName);
    input.value = val;
}


function sortTable(n, tableId) 
{
    var shouldSwitch = 0;
    var switchcount = 0;
    var switching = true;
    var dir = "asc";

    var table = document.getElementById(tableId);
    while (switching) 
    {
        switching = false;
        //Loop through all table rows (except the first header row)
        var rows = table.rows;
        for (var i = 1; i < (rows.length - 1); i++) 
        {
            // Start by saying there should be no switching:
            shouldSwitch = false;

            //Compare col val from current row and next row
            var x = rows[i].getElementsByTagName("TD")[n];
            var y = rows[i+1].getElementsByTagName("TD")[n];
            if (dir == "asc") 
            {
                if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) 
                {
                    shouldSwitch = true;
                    break;
                }
            } 
            else if (dir == "desc") 
            {
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) 
                {
                    shouldSwitch = true;
                    break;
                }
            }
        }
        // If a switch has been marked, make the switch and mark that a switch has been done:
        if (shouldSwitch) 
        {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount ++;
        } 
        //If no switching has been done AND the direction is "asc", set the direction to "desc" and run the while loop again. 
        else 
        {
            if (switchcount == 0 && dir == "asc") 
            {
                dir = "desc";
                switching = true;
            }
        }
    }
}
