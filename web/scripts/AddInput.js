/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/* 
 * Copy the input in the current button.
 */
function addRow(button) 
{
    div = $(button).parent();
    
    row_new = div.find(".row").first().clone();
    
    $(button).before(row_new);
    buttonDiv = jQuery('<div/>', { class: 'col-sm-1' });
    row_new.append(buttonDiv);
    buttonDiv.append("<button type='button' onClick='removeRow(this);' class='btn btn-danger btn-xs remove-input'>\n\
<span class='glyphicon glyphicon-minus-sign'></span> Remove</button>");
}


function removeRow(button)
{
   $(button).parent().parent().remove(); 
}