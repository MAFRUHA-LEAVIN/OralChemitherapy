/* AntiC Website JQuery Methods
 * Used for Global JQuery Buttons
 * and listeners
 */
$(function(){

    // This would be best done with just AngularJS to not mix the two
    $('.btnDisableUser').click(function(){
        var userID = $(this).attr('id').split('-')[1];
        $.post("/console/user/"+userID+"/enable");
    });

    // Shows and Hides a Drug
    $('.btnShowHideDrug').click(function(){
        var drugID = $(this).attr('id').split('-')[0];
        var enabled = $(this).hasClass("btn-info");
        if (enabled)
            enabled = 0;
        else 
            enabled = 1;

        $.post("/console/drugs/"+drugID+"/showhide", { 'enabled': enabled  }, function (data){
            if (data != "1")
                alert(data);
        });
    });

    // Shows and Hides a Interaction
    $('.btnShowHideInteraction').click(function(){
        var interactionID = $(this).attr('id').split('-')[0];
        var enabled = $(this).hasClass("btn-info");
        if (enabled)
            enabled = 0;
        else 
            enabled = 1;


        $.post("/console/interactions/"+interactionID+"/showhide", { 'enabled': enabled }, function (data){
            if (data != "1")
                alert(data);
        });
    });    

    // Smart Add button, used for Edit and Add pages, based off Zach's original code.
    $('.addMore').click(function(){
        var parent = $(this).parent();
        var clonedRow = parent.find(".toClone").last().clone();
        var number = 0;
        // Overrides Parent by Cloning class with toClone
        if (!clonedRow.length) {
             clonedRow = parent.find(".row").last().clone();
        }

        // Update the Name
        clonedRow.find('input:text, input:password, input:file, select, textarea, input:radio, input:checkbox').each(function(){
            var match = $(this).attr('name').match(/(\d+)/g);
            if (match) {
                number = parseInt(match[0]);
                number = number + 1;
                $(this).attr('name', $(this).attr('name').replace(/(\d+)/g, number));
            }
        });

        // Clear the inputs
        clonedRow.find('input:text, input:password, input:file, select, textarea').val('');
        clonedRow.find('input:radio, input:checkbox').removeAttr('checked').removeAttr('selected');

        var itemName = this.id.replace('addMore', "");

        // If there is no button, then add the remove, otherwise ignore.
        if (!clonedRow.find('button').length)
            clonedRow.append("<div class='col-sm-1'><button type='button' id='remove"+itemName+"["+number+"]' onClick='removeRow(this);' class='btn btn-danger btn-xs remove-input'>\n\
<span class='glyphicon glyphicon-minus-sign'></span> Remove</button></div>");
        clonedRow.insertBefore($(this));

        
    });

    $('#last-revision').datepicker({ dateFormat: "yy-mm-dd" });

});