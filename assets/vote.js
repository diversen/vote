// most simple voting thing I could find is found here: 
// http://webhole.net/2010/04/04/voting-script-with-php-and-jquery/
// voteing javascript is in large inspired from above URL. 

$(document).ready(function(){

    var voteDownScript='/vote/down';
    var voteUpScript='/vote/up';

    // vote up
     $("button.vote_up").on('click', function(e){ // when people click an up button
            //$("span#response").show().html('Voting, please wait...'); // show wait message
            e.preventDefault();
            itemID=$(this).val(); // get post id
            $.post(voteUpScript,{id:itemID},function(response){ // post to up script
                    $("span#" + itemID).html(response).show(); // show response
            });
            return false;
            //$(this).attr({"disabled":"disabled"}); // disable button
     });

     // vote down
     $("button.vote_down").on('click', function(e){
            //$("span#response").show().html('voting, please wait.. ');
            e.preventDefault();
            itemID=$(this).val();
            $.post(voteDownScript,{id:itemID},function(response){ // post to down script
                    $("span#" + itemID).html(response).show();// show response
            });
            return false;
            //$(this).attr({"disabled":"disabled"}); // disable button

    });                
});
