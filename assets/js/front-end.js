$ = jQuery.noConflict();


$(() => {
//    $("#delete-subsite-btn").live("click", function(e) {
    $("#delete-subsite-btn").click(function(e) {
        console.log('clicked');
        e.preventDefault();
        var bvDeleteUser = prompt(bvVar.delete_text);
        if (bvDeleteUser === "DELETE") {
            window.location.replace(bvVar.delete_url);
        } else {
            alert(bvVar.delete_error_text);
        }
    });
    //let pms_group_name = (new Date()).toString() + ' ' + bvVar.user_email;
    let pms_group_name = bvVar.user_email;
    $('#pms_group_name').val(pms_group_name);

    var pathname = window.location.pathname.split('/');
    if (pathname[pathname.length - 2] == bvVar.main_tab) {
        $('.pms-account-subscription-details-table').remove();
    };

})
