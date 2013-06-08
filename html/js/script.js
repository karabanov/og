  //Last know size of the file
  lastSize = "";
  //Grep keyword
  grep = "";
  //Should the Grep be inverted?
  invert = 0;
  //Last known document height
  documentHeight = 0;
  //Last known scroll position
  scrollPosition = 0;
  //Should we scroll to the bottom?
  scroll = true;

    $(document).ready(function(){

    // Setup the settings dialog
    $( "#settings" ).dialog({
      modal: true,
      resizable: false,
      draggable: false,
      autoOpen: false,
      width: 590,
      height: 270,
      buttons: {
      Close: function() { $( this ).dialog( "close" ); }},
      close: function(event, ui) {
      grep = $("#grep").val();
      invert = $('#invert input:radio:checked').val();
      $("#grepspan").html("Grep keyword: \"" + grep + "\"");
      $("#invertspan").html("Inverted: " + (invert == 1 ? 'true' : 'false'));
      }});

 //Close the settings dialog after a user hits enter in the textarea
 $('#grep').keyup(function(e) {
 if(e.keyCode == 13) {
 $( "#settings" ).dialog('close');}});

 //Focus on the textarea
 $("#grep").focus();

 //Settings button into a nice looking button with a theme
 $("#grepKeyword").button();

 //Settings button opens the settings dialog
 $("#grepKeyword").click(function(){
 $( "#settings" ).dialog('open');
 $("#grepKeyword").removeClass('ui-state-focus');});


  //Set up an interval for updating the log. Change updateTime in the PHPTail contstructor to change this
  setInterval( "updateLog()",  300);

  //Some window scroll event to keep the menu at the top
  $(window).scroll(function(e)
  {
    if ($(window).scrollTop() > 0)
    {
      $('.float').css({ position: 'fixed' , width: '100%' , top: '-200px' , left: 'auto' });
    }
    else
    {
      $('.float').css({ position: 'fixed' , top: '-100px' });
    }
  });

  //If window is resized should we scroll to the bottom?
  $(window).resize(function()
  {if(scroll){scrollToBottom();}});


  //Handle if the window should be scrolled down or not
  $(window).scroll(function()
  {
    documentHeight = $(document).height();
    scrollPosition = $(window).height() + $(window).scrollTop();
    if(documentHeight <= scrollPosition)
    {
      scroll = true;
    }
    else
    {
      scroll = false;
    }});

  scrollToBottom();

  });

  //This function scrolls to the bottom
  function scrollToBottom()
  {
    $('.ui-widget-overlay').width($(document).width());
    $('.ui-widget-overlay').height($(document).height());

    $("html, body").scrollTop($(document).height());
    if($( "#settings" ).dialog("isOpen"))
    {
      $('.ui-widget-overlay').width($(document).width());
      $('.ui-widget-overlay').height($(document).height());
      $( "#settings" ).dialog("option", "position", "center");
    }
  }

  //This function queries the server for updates.
  function updateLog()
  {
    $.getJSON('http://192.168.24.4/og/html/PHPTail.php?ajax=1&lastsize=' + lastSize + '&grep='+grep + '&invert=' + invert, function(data) {

    lastSize = data.size;

    $.each(data.data, function(key, value)
    {
      //var keywords = '192.168.20.116 WARN INFO';
      //keywords = keywords.split(" ");
      //value = value.replace(new RegExp('('+keywords.join('|')+')',"ig"),"<b>$1</b>");

      $("#results").append(value);
      if($('h2').length > 100) {
                         $("#results h2:first").remove();
                         $("#results a:first").remove();
                         $("#results div:first").remove();
                        }


    });

 scrollToBottom();

 });

}
