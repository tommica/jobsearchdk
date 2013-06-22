$(function() {
  'use strict';

  // Show/Hide job listing
  $('.js_listToggler').on('click', function() {
    // Set the proper icon
    $(this).find('.js_showWhenVisible, .js_showWhenHidden').fadeToggle();
    $(this).next().slideToggle();
  });

  // Animating and checking out the form
  $('.js_introPage form button').on('click', function() {

    if( $('.js_word').val() && $('.js_zip').val() ) {
      // Initial click of the buttons
      $('.js_service button').trigger('click');

      $('.js_listJobs').animate( {'left':'0%'}, 500, function() {
        $('.js_introPage').fadeOut('500');
      });
    }

    return false;
  });

  // The job list loading is done by the load more button
  var toolUrl = 'tool.php';

  $('.js_service button').on('click', function() {
    // Infoelem contains the info on the service and page
    var infoElem = $(this).closest('.js_service');

    // Get the page and update the page num
    var page = parseInt( infoElem.attr('data-page'), null ) + 1;
    // TODO: Move the setting the page to ONLY on success
    infoElem.attr('data-page', page);

    // Get rest of the needed information
    var service = infoElem.attr('data-service');
    var word = $('.js_word').val();
    var zip = $('.js_zip').val();

    // Enable the loading animation
    var loadingElem = $(this).siblings('.js_loading');
    loadingElem.fadeIn('fast');

    // List element for the jobs
    var listElement = $(this).siblings('ul');

    // Get the list
    $.getJSON(toolUrl+'?nocache='+Math.round(Math.random()*10153)+'&zip='+zip+'&word='+word+'&service='+service+'&page='+page, function(res) {
      loadingElem.fadeOut('fast');

      if( res.result.length > 0 ) {
        var results = res.result;
        for(var i = 0; i < results.length; i++) {
          var title = results[i].title;
          title = title ? title.replace(/^\s\s*/, '').replace(/\s\s*$/, '') : "";

          var company = results[i].company;
          company = company ? company.replace(/^\s\s*/, '').replace(/\s\s*$/, '') : "";

          var loc = results[i].location;
          loc = loc ? loc.replace(/^\s\s*/, '').replace(/\s\s*$/, '') : "";

          var text = title + ( company ? ", "+company : "" ) + ( loc ? ", " + loc : "" );

          var html = '<li><a href="'+results[i].url+'">'+text+'</a></li>';

          listElement.append(html);
        }
      }
    });

    return false;
  });
});
