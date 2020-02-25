function setValueFromDropdown(dd, objName)
{
    var val = dd.value;
    var input = document.getElementById(objName);
    input.value = val;
}


function setDateNow(id)
{
    str = new Date().toISOString().split('T')[0];
    document.getElementById(id).value = str;

}


function checkAll(elm) 
{
    var inputs = document.getElementsByTagName('input');
    for (var i = 0; i < inputs.length; i++) 
    {
        var input = inputs[i];
        if (input.type != 'checkbox') {continue;}
        if (input.id.indexOf('CB') != 0) {continue;}
        inputs[i].checked = elm.checked;
    }
}


function clearForm(form)
{
    var elements = form.elements;
    form.reset();
    for(i=0; i<elements.length; i++) 
    {
        field_type = elements[i].type.toLowerCase();
        switch(field_type) 
        {
            case "text":
            case "password":
            case "textarea":
            case "hidden":
              elements[i].value = "";
              break;
            // case "radio":
            // case "checkbox":
            //     elements[i].checked = false;
            // break;
            case "select-one":
            case "select-multi":
                elements[i].selectedIndex = 0;
            break;
        }
    }
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
