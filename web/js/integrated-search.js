if ( LI == undefined )
  var LI = {};

LI.list_integrated_search = function(data)
{
  data = $.parseHTML(data);
  
  // replacing only necessary content
  filters = $('#sf_admin_filters_buttons').clone(true);
  $('.sf_admin_list > table').replaceWith($(data).find('.sf_admin_list > table'));
  $('#sf_admin_filters_buttons').replaceWith(filters);
  
  // updating links to order_by and pagination
  $('.sf_admin_list > table > thead a, .sf_admin_list > table > tfoot a').each(function(){
    $(this).attr('href',
      $(this).attr('href')
        .replace($('#list-integrated-search input[name=url]').val(),$('#list-integrated-search').attr('action'))
      +'&s='+$('#list-integrated-search input[name=s]').val()
    );
  }).click(function(){
    $.get($(this).prop('href'),function(data){
      LI.list_integrated_search(data);
    });
    return false;
  });
  
  // disabling the input which permits to change current page arbitrary
  $('.sf_admin_list > table > tfoot [name=page]')
    .prop('disabled','disabled')
    .prop('style','background-color: white');
  
  // disabling extra-actions
  if ( $('#sf_admin_actions_menu .sf_admin_action_group, #sf_admin_actions_menu .sf_admin_action_csv').length > 0 )
  {
    $('#sf_admin_actions_menu .sf_admin_action_group, #sf_admin_actions_menu .sf_admin_action_labels, #sf_admin_actions_menu .sf_admin_action_csv')
      .remove();
    $('#sf_admin_actions_button').unbind().menu({
      content: $('#sf_admin_actions_menu').html(),
      showSpeed: 300
    });
  }
  
  // if searched by id and only one result, going into the object's file
  if ( parseInt($('#list-integrated-search input[name=s]').val().replace(/^0*/,''),10)+'' == $('#list-integrated-search input[name=s]').val().replace(/^0*/,'') && $('.sf_admin_list > table .sf_admin_action_show').length == 1 )
  {
    window.location = $('.sf_admin_list > table .sf_admin_action_show a:first').prop('href');
  }

  // for recorded function to execute after an Ajax reloading
  if ( window.integrated_search_end != undefined )
  for ( i = 0 ; i < window.integrated_search_end.length ; i++ )
    window.integrated_search_end[i]();
}

LI.list_integrated_search_init = function()
{
  // focus on integrated search on load
  $('#list-integrated-search input[type=text]:first').focus();
  
  $('#list-integrated-search').unbind().submit(function(){
    if ( location.hash != '#debug' )
    {
      $.get($(this).prop('action'),{ s: $(this).find('input[name=s]').val() },function(data){
        LI.list_integrated_search(data);
      });
      return false;
    }
    else
      $(this).prop('target', '_blank');
  });
}

$(document).ready(function(){
  LI.list_integrated_search_init();
});
