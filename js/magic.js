/**
 *
 * Javascript / jQuery functions
 *
 *
 */
$(document).ready(function () {

/* @general functions */

/*loading spinner functions */
function showSpinner() { $('div.loading').show(); }
function hideSpinner() { $('div.loading').fadeOut('fast'); }

/*	Login redirect function if success
****************************************/
function loginRedirect() {
	var base = $('.iebase').html();
	window.location=base;
}


//tooltip2
$('.tooltip2').tooltip({
    track: true,
    delay: 0,
    showURL: false,
    showBody: " - ",
    extraClass: "tooltip2",
    fixPNG: true,
    opacity: 0.95,
    left: 0
});

/* hide error div if jquery loads ok
*********************************************/
$('div.jqueryError').hide();

//disabled links
$('.disabled a').click(function() {
	return false;
});



//prevent loading for disabled buttons
$('a.disabled, button.disabled').click(function() { return false; });

// load modal
$(document).on("click", '.load-modal', function() {
    // load
    $('#modal1 .modal-content').load($(this).attr('href'));
    // show
    $('#modal1').modal('show');
    // dont reload
    return false;
});
$(document).on("click", '.load-modal-big', function() {
    // load
    $('#modal2 .modal-content').load($(this).attr('href'));
    // show
    $('#modal2').modal('show');
    // dont reload
    return false;
});

/* @cookies */
function createCookie(name,value,days) {
    var date;
    var expires;

    if (typeof days === 'undefined') {
        date = new Date();
        date.setTime(date.getTime()+(days*24*60*60*1000));
        expires = "; expires="+date.toGMTString();
    }
    else {
	    var expires = "";
    }

    document.cookie = name+"="+value+expires+"; path=/";
}
function readCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}


$('th.header-full_screen i').click(function() {
   // toggle fullscreen class
   $(this).closest('div.container-fluid').toggleClass('full_screen full_screen_live');
   $('nav.navbar, .search-wrapper, .footer, .hosts-wrapper, .message-wrapper').toggleClass('hidden');
});




/*	submit login
*********************/
$('form#login').submit(function() {
	//show spinner
	showSpinner();
    //stop all active animations
    $('div#loginCheck').stop(true,true);

    var logindata = $(this).serialize();

    $('div#loginCheck').hide();
    //post to check form
    $.post('app/login/login_check.php', logindata, function(data) {
        $('div#loginCheck').html(data).fadeIn('fast');
        //reload after 2 seconds if succeeded!
        if(data.search("alert alert-success") != -1) {
            showSpinner();
            //search for redirect
            if($('form#login input#phptrapredirect').length > 0) { setTimeout(function (){window.location=$('form#login input#phptrapredirect').val();}, 1000); }
            else 												 { setTimeout(loginRedirect, 1000);	}
        }
        else {
	        hideSpinner();
        }
    });
    return false;
});





//default row count
if(readCookie('table-page-size')==null) { def_size = 25; }
else                                    { def_size = readCookie('table-page-size'); }
// table
$('table.sorted').bdt({
   pageRowCount: def_size,
   searchFormClass: 'form-inline pull-right',
   divClass: 'text-right'
});
$('table.sorted-left').bdt({
   pageRowCount: def_size,
   searchFormClass: 'form-inline pull-left clearfix',
   divClass: 'text-left clearfix'
});
$('table.sorted').stickyTableHeaders();
$("li.disabled a").click(function () {
   return false;
});
$('form.search-form').submit(function() {
   return false;
})


return false;
});
