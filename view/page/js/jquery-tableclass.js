
/*
 * jQuery Table Extension plugin 2.0
 *
 * Copyright (C) 2007-2014 MyCoreCMS
 *
 * Dual licensed under the MIT and GPL licenses:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details. See <http://www.gnu.org/licenses/>.
 */
var typingTimer;
var lastValue;
$.ajaxSetup ({
    // Disable caching of AJAX response
    cache: false,
    timeout: 45000
});

$(function() {
    $(document)
    .ajaxStart(function() {
        $("body").css("cursor", "progress");
        if($(this).find('tbody').html() != null){ //If we have a table, only show it as loading
            $(this).find('tbody').css({ opacity: 0.5 });
            $('.loading').removeClass('hidden');
        }
    })
    .ajaxStop(function() {
        $(this).show();
        $(this).find('tbody').css({ opacity: 1 });
        $("body").css("cursor", "auto");
        $('.loading').addClass('hidden');
    });
  //Since using a class for a date field doesn't auto assign, we assign when a datepickerclass input field is clicked
    $(document).on('click','.datepickerclass', function() {
        $('.datepickerclass').datepick({dateFormat: 'yyyy-mm-dd' ,
          onSelect: function(dateText) {
            if($(this).attr('class').search('Filter') != -1){
                 var page = getCurrentPage(this);
                 //Gather Search Criteria
                 var search = getSearchVars(page) + "rpp="+ page.find('select.rpp').val() +"&";  //Get the number of results to be displayed
            	 if(window.typingTimer != null) clearTimeout(typingTimer);
            	 typingTimer = setTimeout(function(){do_search(page,search)}, 300);
            }
          }
        });
        $(this).datepick({showOn:'focus'}).focus();
    });
    $(document).on('click','.timepickerclass', function() {
        $('.timepickerclass').timepicker({ampm: true});
        $(this).timepicker({showOn:'focus'}).focus();
    });

    $('.tabs ul:first a').click(function() { // bind click event to link
                    current_tab = $(this).attr('href');
                    $('.tabs').tabs('select', $(this).attr('href')); // switch tab
                    $.address.value(index_page +"?tab="+ $(this).attr('href').replace('#','') + "&"+ getSearchVars($(current_tab)));
                    return false;
     });
     $(document).on('click',".other_option", function() {
      if($(this).val() == "Other"){
        //alert($(this).parent().html());
        $(this).parent().parent().html("<input name=\'"+$(this).attr("name")+"\' type=\'text\'>");
        }
    });
    $(document).on('click','.close_row', function() {
     var url = $(this).attr('href');
     var page = getCurrentPage(this);
     var row = $(this).parents('tr:eq(0)');
     get_queue( url + "&jquery=TRUE"+"&advanced_search=" + page.find('.advanced_search').val(), function(msg) {
        row.find('td:eq(0)').wrapInner('<div style="display: block;" />').parent().find('td > div:first').slideUp(1500, function(){
            msg= $(msg).attr('class','').addClass($(this).parent().parent().attr('class'));
            $(this).parent().parent().after(msg); //Add the row
            $(this).parent().parent().remove();  //remove the old row
        });
     });
     return false; // don't follow the link!
   });
   $(document).on('click','.edit_row', function() {
     var url = $(this).attr('href');
     //var page = getCurrentPage(this);
     var row = $(this).parents('tr:eq(0)');
     //$("#something").hide().append(data).fadeIn('slow');
     get_queue( url + "&jquery=TRUE", function(msg) {
                if(url.search('action=Add_New') == -1){
                  row.html("<td colspan='100%'><div style='display: none;' class='row_div'>"+msg+"</div></td>").fadeIn(0).find('td:eq(0) > div:first').slideDown(1500, function(){
                    if($(this).find('.current_page'))
                         document.title = $(this).find('.current_page').attr('name');
                    //$(this).replaceWith($(this).contents());
                  });
                }
                else
                  row.after("<tr><td colspan='100%'><div class='row_div'>"+msg+"</div></td></tr>");
     });

     return false; // don't follow the link!
   });
   $(document).on('click','.delete_row', function() {
     var answer = confirm('Are you sure you want to delete this?');
     if(answer){
         //Send the request to server
         get_queue( $(this).attr('href')+"&jquery=TRUE", function(){});
         //Hide the row being deleted
         $(this).parents('tr:eq(0)').remove();
         return false;
     }
     return false; // don't follow the link!
   });
   $(document).on('click','.edit_link', function() {
     var url = $(this).attr('href');
     var page = getCurrentPage(this,url);
     var search = getSearchVars(page) + "rpp="+ page.find('.rpp').val() +"&";
     //$('.loading').removeClass('hidden');
     //$("#something").hide().append(data).fadeIn('slow');
     get_queue( url +"&"+search+ "&jquery=TRUE", function(msg) {
                page.hide("slide",{direction: "left"},500, function() {
                  page.html(msg);
                  page.show("slide",{direction:"right"},500);
                  //$('.loading').addClass('hidden');
                  page.append("<input type='hidden' id='back_search' value='"+search+"'>");
                  //Update the page title
                  if(page.find('.current_page'))
                         document.title = page.find('.current_page').attr('name');
                  });
     });

     return false; // don't follow the link!
   });
   $(document).on('click','.update_link', function() {
     var url = $(this).attr('href');
     var pos = url.search('tab=');
     var tab = url.substring(pos+4);
     var page = $('.content').find('#' + tab);
     //Submit the request and update the page with the results
     page.fadeOut(250, function() {
       get_queue( url + "&jquery=TRUE", function(msg) {
         page.html(msg);
         page.fadeIn(250);
         $(".tabs").tabs('select', '#' + tab);
       });
     }); //close the fadeOut() call
     return false; // don't follow the link!
   });
   $(document).on('click','.submit_link', function() {
     var url = $(this).attr('href');
     get_queue( url + "&jquery=TRUE", function(msg) {
         if(msg != '')
            alert($('.result',msg).html());
     });
     return false; // don't follow the link!
   });
   $(document).on('click','.back', function() {
         var url = $(this).attr('href');
         var question = "&";
         if(encodeURIComponent($(this).attr('href')).search("%3F") == -1) //Check if we already have a ? mark in the string!
                question = "?";

         var page = getCurrentPage(this,url);
         var search = "";
         if(page.find('#back_search').val() != undefined)
            search = page.find('#back_search').val();
           //$('.loading').removeClass('hidden');
           //Submit the request and update the page with the results
           get_queue( url + question+search+"jquery=TRUE", function(msg) {
                  page.hide("slide",{direction: "left"},500, function() {
                  page.html(msg);
                  page.show("slide",{direction:"right"},500);
                   //$('.loading').addClass('hidden');
                   //Update the page title
                  if(page.find('.current_page'))
                         document.title = page.find('.current_page').attr('name');
                  });
           });
         return false; // don't follow the link!
   });
   $(document).on('click','a.result_limiter', function() {
     var page = getCurrentPage(this); //We need to get the page before we set the url
     var url = $(this).attr('href') + "&jquery=TRUE&sort="+ page.find('.sort').val() +"&ascending=" + page.find('.sort').attr('ascending')+"&rpp="+ page.find('.rpp').val() +"&" + getSearchVars(page) ;
     var page = getCurrentPage(this,url);
         //Submit the request and update the page with the results
         get_queue( url +"action=Get_results", function(msg) {
                try{
                  page.find('.content_table tbody').html($(msg).find('.content_table tbody').html());
                  page.find('.total_results').html($(msg).find('.total_results').html());
                  page.find('.page_list').html($(msg).find('.page_list').html());
                }
                catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
         });
         return false; // don't follow the link!
   });
   $(document).on('change','select.rpp', function() {
     var page = getCurrentPage(this);  //We need to get the page before we set the url
     var url = page.find('.current_page').val() + "&jquery=TRUE&sort="+ page.find('.sort').val() +"&advanced_search=" + page.find('.advanced_search').val() +"&ascending=" + page.find('.sort').attr('ascending') +"&" + getSearchVars(page) +"rpp="+ $(this).val();
     page = getCurrentPage(this,url);
     //Submit the request and update the page with the results
         get_queue( url +"&action=Get_results", function(msg) {
                try{
                  page.find('.content_table tbody').html($(msg).find('.content_table tbody').html());
                  page.find('.total_results').html($(msg).find('.total_results').html());
                  page.find('.page_list').html($(msg).find('.page_list').html());
                }
                catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
         });
         return false; // don't follow the link!
   });
   $(document).on('click','.clear', function() {
         var page = getCurrentPage(this);
         var url = $(this).attr('href');
         //Clear out anything that is pending
         if(window.typingTimer != null) clearTimeout(typingTimer);
         //Set all filters to empty
         page.find('.Filter').each(function() {
            if($(this).attr('name').search(/\[\]/) != -1)
                  $(this).prop('checked', false);
            else
                $(this).val("");
        });
         //Reset all classes for filter update
         page.find('.header').removeClass('headerSortDown headerSortUp');
         //Submit the request and update the page with the results
         get_queue( url + "&action=Get_results&jquery=TRUE", function(msg) {
                try{
                  page.find('.content_table tbody').html($(msg).find('.content_table tbody').html());
                  page.find('.total_results').html($(msg).find('.total_results').html());
                  page.find('.page_list').html($(msg).find('.page_list').html());
                }
                catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
         });
         return false; // don't follow the link!
   });
   $(document).on('click','.header', function() {
     var page = getCurrentPage(this);
           //Alternate between ascending and descending
           var ascending = (page.find('.sort').attr('ascending') == 1?0:1);
           var header = $(this).attr('value');

           //Update the html ascending field
           page.find('.sort').attr('ascending',ascending);
            //Gather Search Criteria
           var search = getSearchVars(page) + "rpp="+ page.find('select.rpp').val() +"&";  //Get the number of results to be displayed
         //Submit the request and update the page with the results
         get_queue( page.find('.current_page').val() +"&sort="+ $(this).attr('value') +"&ascending=" + ascending +"&" + search +"action=Get_results&jquery=true", function(msg) {
                try{

                  page.find('.content_table tbody').html($(msg).find('.content_table tbody').html());
                  page.find('.total_results').html($(msg).find('.total_results').html());
                  page.find('.page_list').html($(msg).find('.page_list').html());
                  //Reset all classes for filter update
                   page.find('.header').removeClass('headerSortDown headerSortUp');
                   //Update the html sort field
                   page.find('.sort').val($(this).attr('value'));
                   if(ascending == 0)
                        page.find("th[value='"+header+"']").addClass("headerSortDown");
                   else
                        page.find("th[value='"+header+"']").addClass("headerSortUp");
                  
                }
                catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
         });
         return false; // don't follow the link!
   });//Perform search
   $(document).on('keyup change','.Filter', function(event) {
     if((event.type == 'change' && $(this).attr('type') != 'text') || event.type =='keyup'){
         var page = getCurrentPage(this);
         //Gather Search Criteria
         var search = getSearchVars(page) + "rpp=&advanced_search=" + page.find('.advanced_search').val()+"&"+ page.find('select.rpp').val() +"&";  //Get the number of results to be displayed
    	 if(window.typingTimer != null) clearTimeout(typingTimer);
    	 typingTimer = setTimeout(function(){do_search(page,search)}, 600);
      }
   });
   //Bulk update with checkboxes
   $(document).on('change','select.bulk', function() {
         //Create a list of all checked checkboxes
         var page = getCurrentPage(this);
         var checkbox_list = "";
         page.find('.checkboxes').each(function() { if ($(this).prop('checked')) checkbox_list = checkbox_list + "," +$(this).val(); });
         checkbox_list = checkbox_list.substring(1);
         if($(this).val() == 'Map')
            open_window(page.find('.current_page').val() + "&action=Map&map_list=" +checkbox_list + "&" + getSearchVars(page),600,700);
         else if($(this).val() != '' )
            window.open(page.find('.current_page').val() + "&action="+$(this).val()+"&bulk=" +checkbox_list +"&jquery=true");
   });
   //Select/Unselect checkboxes in bulk
   $(document).on('click','.update_checkboxes', function() {
         //Create a list of all checked checkboxes
         var page = getCurrentPage(this);
         var checked = false;
         if($(this).prop('checked'))
            checked = true;

         page.find('.checkboxes').each(function() { $(this).prop('checked', checked)  });
   });
      //Export a file
   $(document).on('click','.export', function(event) {
        var page = getCurrentPage(this);
        var url = $(this).attr('href');
        var question = "&";
        if(encodeURIComponent(url).search("%3F") == -1) //Check if we already have a ? mark in the string!
            question = "?";
        window.open(url + question +getSearchVars(page) +"&action=Export");
        event.preventDefault();
   });
   $(document).on('click','.export_pdf', function(event) {
        var page = getCurrentPage(this);
        var url = $(this).attr('href');
        var question = "&";
        if(encodeURIComponent(url).search("%3F") == -1) //Check if we already have a ? mark in the string!
            question = "?";
        window.open(url + question +getSearchVars(page) +"&action=Print");
        event.preventDefault();
   }); //Records which submit was clicked
   $(document).on('click','input[type=submit]', function() {
      $("input[type=submit]").removeAttr("clicked");
      $(this).attr("clicked", "true");
   });
   //Handles Updates and Adds
   $(document).on('submit','.default_form', function(event) {
   //Check if there is a file to upload, if so we need to do a regular submit via an iframe(slower loading)
     if($(this).find('input[type="file"]').attr('name') == undefined)
     {
       //Prevent Double Clicks
       $(this).find('input[type="submit"]').attr('disabled', true).delay(1000).queue(function(){
	 	        $(this).removeAttr("disabled");
	 	        $(this).dequeue();
	});
     //JS won't get the value of the submit clicked, this will just grab the 1st submit on the form
     var action = $(this).find('input[type="submit"][clicked="true"]').val();
     var url = $(this).attr('action');
     var page = getCurrentPage(this);
     var forms = $(this);

     var pos = url.search('tab=');
     var fields =  $(this).serialize();
     var fielddata =  $(this).serializeArray();

     if(action == "Download")
        window.open(url + "&"+ fields +"&"+ getSearchVars(page) +"&action=" + action);
     else {
         post_queue( url  +"&action=" + action + "&jquery=TRUE",fielddata, function(msg) {
                  if(msg!= ''){
                    if(action =='Add' || action =='Update'){
                        forms.parents('.row_div:eq(0)').find('div.result').html(msg);
                        if(forms.parents('tr:eq(0)')[0])
                            forms.parents('tr:eq(0)')[0].scrollIntoView();
                        if(action == 'Add'){
        	                post_queue(page.find('.current_page').val() + "&sort="+ page.find('.sort').val() +"&ascending=" + page.find('.sort').attr('ascending') +"&action=Get_results&jquery=TRUE",getSearchVars(forms) + "rpp="+ page.find('select.rpp').val() +"&", function(msg) {
                                try{
                                        page.find('.content_table tbody').html($(msg).find('.content_table tbody').html());
                                        page.find('.total_results').html($(msg).find('.total_results').html());
                                        page.find('.page_list').html($(msg).find('.page_list').html());
                                }
                                catch(err){
                                          page.html(msg);
                                      }
                               });
                        }
                        if(action == 'Update' && msg == "Updated"){
                          var url = forms.parents('.row_div:eq(0)').find('.close_row').attr('href');
                          if(url != undefined && page.find('.close_row').attr('href') != undefined)//If we are a tab the close url will not be in the current page
                          {var row = forms.parents('tr:eq(0)');
                            get_queue( url + "&jquery=TRUE"+"&advanced_search=" + page.find('.advanced_search').val(), function(msg) {
                              row.find('td').wrapInner('<div style="display: block;" />').parent().find('td > div:first').slideUp(1500, function(){
                                  $(this).parent().parent().after(msg); //Add the row
                                  $(this).parent().parent().remove();  //remove the old row
                              });
                           });
                         }
                        }
                    }
                    else
                        page.find('div.result').html(msg);
                  }
         });

     }
     event.preventDefault();
   }
   else{//If this has a file, submit it to an iframe instead of going through jQuery, iFrame calls function when done loading
        //$(this).attr("action") = $(this).attr("action")+"&jquery=TRUE";
        $(this).prop("target", $(this).find('input[type="file"]').attr('target'));
   }
   });
   $(document).on('click','.quick_add', function(event) {
     var action = $(this).val();
     var page = getCurrentPage(this);
     get_queue(page.find('.current_page').val()+ "&"+ getSearchVars(page) +"&action=" + action + "&jquery=TRUE", function(msg) {
                    alert(msg);
     });
     event.preventDefault();
   });
   $(document).on('keyup','.auto', function() {
       var subject =  $(this).attr('id');
       var search = $(this).val();
       var page = getCurrentPage(this);
       var url = page.find('.current_page').val() + "&sort="+ subject +"&"+subject+"="+search +"&action=Autocomplete";
           //Send the request to server
       $('#'+subject).autocomplete("");
       if(search.length > 0){
             get_queue(page.find('.current_page').val() + "&sort="+ subject +"&"+subject+"="+search +"&action=Autocomplete", function(msg) {
                $('#'+subject).autocomplete(msg.split(","));
             });
       }
   });
   $(document).on('click','.edit_field', function(event) {
     var page = getCurrentPage(this);
     var url = page.find('.current_page').val() + "&action=Edit_Field&get_id="+ $(this).attr('lookup_id') +"&get_key=" + $(this).attr('key');
     var the_div = $(this);
         get_queue( url +"&jquery=TRUE", function(msg) {
           the_div.html(msg);
           the_div.removeClass('edit_field');
           var tmp = the_div.find("input").val();
           the_div.find("input").focus().val('').val(tmp);  //resetting the value puts the cursor at the end
           the_div.addClass('set_field');

         });
   });
   $(document).on('change focusout','.set_field', function(event) {
     if((event.type == 'change' && $('input',this).attr('type') != 'text') || (event.type =='focusout' && $('input',this).attr('type') == 'text') ){
     var page = getCurrentPage(this);
     var key = $(this).attr('key');
     var search = $(this).find('.get_field').serialize()+"&";
     /*
     $(this).find('.get_field').each(function() {
      if($(this).val()){    //Check if this is a checkbox array
        if($(this).attr('name').search(/\[\]/) != -1){
              if($(this).is(":checked"))
                search = search + $(this).attr('name') +"="+ encodeURIComponent($(this).val()) +"&";
        }
        else
            search = search + $(this).attr('name') +"="+ encodeURIComponent($(this).val()) +"&";
      }
    }); */


     var url = page.find('.current_page').val() + "&action=Set_Field&get_id="+ $(this).attr('lookup_id') +"&get_key=" + key+"&"+search;
     var the_div = $(this);
         get_queue( url +"&jquery=TRUE", function(msg) {
           try{var response = $.parseJSON(msg);}
           catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
           //if there is an error show in popup
           if(response[1] != null)
             open_popup(response[1],50,400);
             the_div.html(response[0]);
             the_div.removeClass('set_field');
             the_div.addClass('edit_field');
         });
   }
   });
   /*
   //Make highlighting table rows pretty
   $(".content_table tr").live("mouseover" ,function() {
            $(this).stop().animate({backgroundColor:"#8FAEE0"},400);
        });
   $(".content_table tr").live("mouseout" ,function() {
            $(this).stop().animate({backgroundColor:"#ffffff"},800);
        });
   $(".content_table tr:odd").live("mouseout" ,function() {
            $(this).stop().animate({backgroundColor:"#eeeeee"},800);
    });  */
    if((navigator.userAgent.match(/iPhone/i)) || (navigator.userAgent.match(/iPod/i)) || (navigator.userAgent.match(/iPad/i))) {
      $(document).on('touchstart','.dropmenu', function() {
          $(this).child('ul').css('left','0px');
      });
      $(document).on('touchend','.dropmenu', function() {
          $(this).child('ul').css('left','-9999px');
      });

    }
    $(document).on('click','.submenu', function(event) {
            $('.submenu').removeClass("selected");
            $(this).addClass("selected");
            $('.sub_menu_list').addClass("hidden");
            $('.'+$(this).attr('view')).removeClass("hidden");
            return false;
        });
   //Jquery menu links!
    $(document).on('click','.menu_link', function() {
         $.address.value($(this).attr('href'));
         $('.menu_link').removeClass("current_link");
         $(this).addClass("current_link");
         var question = "";
            if(encodeURIComponent($(this).attr('href')).search("%3F") == -1) //Check if we already have a ? mark in the string!
                question = "?";
         var url = $(this).attr('href');
         var pos = url.search('get_page=');
         var page_id = url.substring(pos+9);
         if(url.search("&")!= -1)
            page_id = url.substring(pos+9,url.search("&"));
         var get_page = $("div[id='page"+page_id+"']");
         if(get_page.html() == null)//Check if we already have the tab loaded
         {
             //Load up a tab + append to the content container
             get_queue( $(this).attr('href') + question+ "&jquery=true", function(msg) {
                      get_page = $("div[id='page"+page_id+"']");
                      if(get_page.html() != null) //Double Check if we already have the tab loaded in case of double click
                        return false;
                      $('.content_container').hide("slide",{direction: "left"},500, function() {
                      $('.tab').addClass('hidden');
                      $('.content_container').append("<div class='tab' id='page"+page_id+"'>\n"+msg+"</div>\n");
                      $('.content_container').show("slide",{direction:"right"},500);
                      //Update the page title
                      if($("div[id='page"+page_id+"']").find('.current_page'))
                         document.title = $("div[id='page"+page_id+"']").find('.current_page').attr('name');
                     });
             });
         }
         else{
            $('.content_container').hide("slide",{direction: "left"},500);
            $('.tab').addClass('hidden');
            get_page.removeClass('hidden');
            $('.content_container').show("slide",{direction:"right"},500);
            //Update the page title
            if(get_page.find('.current_page'))
                document.title = get_page.find('.current_page').attr('name');
         }
         return false; //Don't follow the link!
   });
   $(document).on('click','.plus', function(event) {
         var page = getCurrentPage(this);
         var plus_link =  $(this);
         get_queue($(this).attr('href')+ "&"+ getSearchVars(page) + "&jquery=TRUE", function(msg) {
              if(msg.length > 0){
                    plus_link.parents('tr').after(msg);
                    plus_link.removeClass('plus');
                    plus_link.addClass('minus');
                    plus_link.unbind();
              }
          });
          
          return false;
    });
    $(document).on('click','.minus', function(event) {
         $(this).removeClass('minus');
         $(this).addClass('plus');
         $(this).parents('tr').next().remove();
         $(this).unbind();
         return false;
    });
   //Check if a user clicked the back/forward button
   $.address.externalChange(function(event) {
        //Check that we actually have a page to move to!
        if(event.value != '/'){
         var url = event.value;
         //var startpos = url.search('get_page=')||[];
         //var endpos = startpos+(url.match(/get_page=-?\d+/g)||[])[0].length;
         //var page_id = url.substring(startpos+9,endpos)||[];
         var pos = url.search('get_page=');
         var page_id = url.substring(pos+9);
         if(url.search("&")!= -1)
            page_id = url.substring(pos+9,url.search("&"));
         var question = "";
            if(encodeURIComponent(url).search("%3F") == -1) //Check if we already have a ? mark in the string!
                question = "?";
        var get_page = $("div[id*='page"+page_id+"']");
         //Check if we have a valid page id
         if(page_id != ''){
             if(get_page.html() == null)//Check if we already have the tab loaded
             {
                 //Load up a tab + append to the content container
                 get_queue(url + question+ "&jquery=true", function(msg) {
                          $('.content_container').hide("slide",{direction: "left"},500, function() {
                          $('.tab').addClass('hidden');
                          $('.content_container').append("<div class='tab' id='page"+page_id+"'>\n"+msg+"</div>\n");
                          $('.content_container').show("slide",{direction:"right"},500);
                          //Update the page title
                          if(get_page.find('.current_page'))
                             document.title = get_page.find('.current_page').attr('name');
                         });
                 });
             }
             else{ //Check if the current url of the page matches the url we are changing to
                if(get_page.find('.current_page').val() != url){
                    get_queue( url + question+ "&jquery=true", function(msg) {
                          $('.content_container').hide("slide",{direction: "left"},500, function() {
                          $('.tab').addClass('hidden');
                          get_page.html(msg);
                          get_page.removeClass('hidden');
                          $('.content_container').show("slide",{direction:"right"},500);
                          //Update the page title
                          if(get_page.find('.current_page'))
                             document.title = get_page.find('.current_page').attr('name');
                         });
                 })
                }
                else{  //if the current url does match just switch tabs instead of reloading
                    $('.content_container').hide("slide",{direction: "left"},200);
                    $('.tab').addClass('hidden');
                    get_page.removeClass('hidden');
                    $('.content_container').show("slide",{direction:"right"},200);
                    //Update the page title
                if(get_page.find('.current_page'))
                    document.title = get_page.find('.current_page').attr('name');
             }
           }
         }
         else
            window.location.href = event.value; //If we don't have a page id just reload the page
         }
    }).history(true);

});
function get_queue(Options, action) {
   $(document).queue( function(next) {
      $.ajax({
        url: Options,
        type: "GET",
        success: action,
        complete:  next //move to next queue
      });
   });
}
function post_queue(linkss,option, action) {
   $(document).queue( function(next) {
      $.ajax({
        url: linkss,
        data: option,
        type: "POST",
        success: action,
        complete:  next //move to next queue
      });
   });
}


function getCurrentPage(reference,url){
    var page;
    if(url && $(reference).parent('.tabs').html() == null) //Not all requests need to have the address bar updated for backtracking
            $.address.value(url);
     //if($(reference).parents('.tabs').html() != null)
     //    page = $(reference).parents("#tabs-"+$(".tabs").tabs( "option", "selected" )); //Update the current tab
     if($(reference).closest('.ui-tabs-panel').html() != null)
         page = $(reference).closest('.ui-tabs-panel');
     else if($(reference).closest('.tab').html() != null)
         page = $(reference).closest('.tab');
     else
         page = $(reference).closest('.content_container');

     return page;
}

function getSearchVars(page)
{   var search = "";
    //Gather all the search fields + build query
    page.find('.Filter').each(function() {
      if($(this).val()){
        search = search + $(this).serialize()+"&";
      }

    });
    return search;
}
function open_window(src,heights,widths){
    $.modal('<iframe src="' + src + '&jquery=true&_='+Math.floor(Math.random()*11)+ '" height="'+heights+'" width="'+widths+'" style="border:0">', {
        opacity:75,
    	containerCss:{
    		backgroundColor:"#fff",
    		borderColor:"#0063dc",
    		height:heights,
    		padding:0,
    		width:widths
    	},
    	overlayClose:false,

        onOpen: function (dialog) {
    	dialog.overlay.fadeIn('slow', function () {
    		dialog.data.hide();
    		dialog.container.css({'right' : 1200-widths, 'top' : '-1350px'}).animate({width: widths, height: heights, right: "0px", top: "100px"}, "slow", function () {
    		    dialog.data.fadeIn("fast");
    		}).fadeIn("slow");
    	});
        },
        onClose: function (dialog) {
    	dialog.data.fadeOut('slow', function () {
    	    dialog.overlay.fadeOut('fast');
    		dialog.container.animate({top: "-1550px"}, "slow").slideUp('slow', function () {
                $.modal.close();
    		});
    	});

        }
    });
}
function open_popup(text,heights,widths){
    $.modal(text, {
      opacity:0,
    	containerCss:{
    		backgroundColor:"#fff",
    		borderColor:"#CDCDCD",
            color:"#000000",
            padding:0,
    		height:heights,
    		width:widths
    	},DataCss:{
            color:"#000000"
    	},
        autoResize:true,autoPosition:true
        });
}
function do_search(page,search){
    //Send the request to server
    post_queue(page.find('.current_page').val() + "&sort="+ page.find('.sort').val() +"&ascending=" + page.find('.sort').attr('ascending') +"&action=Get_results&jquery=TRUE",search, function(msg) {
          try{
                  page.find('.results').replaceWith(msg);
                  //var table_html = jQuery.parseJSON(msg);
                  //page.find('.content_table tbody').html(table_html[1]);
                  //page.find('.total_results').html(table_html[2]+" Records");
                  //page.find('.page_list').html(table_html[0]);
          }
          catch(err){
                  if(msg.search('name="loginform"'))
                    page.html(msg);
                  else
                    alert(msg);
                }
   });
}
function check_iframe_loading(frame) {
    if (frame.contents().find('html body').html() != ''){
      //alert(frame.contents().find('html body').html());  //uncomment for debugging
        $('.loading').addClass('hidden');
        if(frame.contents().find('.error').html() != ''){
            alert(frame.contents().find('.error').html());
            if(frame.contents().find('.error').html() == "Added"){
                var page = getCurrentPage(frame);
                do_search(page,getSearchVars(page) + "rpp="+ page.find('select.rpp').val() +"&"); //reload the search

            }
        }
    }
}

