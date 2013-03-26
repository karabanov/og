<?php

include('/var/www/snmp.php');

// Эта функция отвечает за получение последней строки из файла журнала
function getNewLines($log = '', $lastFetchedSize, $grepKeyword, $invert)
{

  $maxSizeToLoad = 2097152;
  $updateTime = 300;

  // Очистить стат кэш, чтобы получить последние результаты
  clearstatcache();

  // Определите, сколько мы должны загрузить из файла журнала
  $fsize = filesize($log);
  $maxLength = ($fsize - $lastFetchedSize);

  // Убедитесь, что мы не загружать больше данных, чем разрешено
  if($maxLength > $maxSizeToLoad)
  {
    return json_encode(array('size' => $fsize, 'data' => array('ERROR: PHPTail попытался загрузить больше ('.round(($maxLength / 1048576), 2).'MB) чем максимальный размер ('.round(($maxSizeToLoad / 1048576), 2).'MB) в байтах в памяь. Вы должны снизить $defaultUpdateTime, чтобы этого не происходило.')));
  }

  /**
   * Actually load the data
  */
  $data = array();

  if($maxLength > 0)
  {
    $fp = fopen($log, 'r');
    fseek($fp, -$maxLength , SEEK_END);
    $data = explode("\n", fread($fp, $maxLength));
  }

  // Запустите GREP функция возвращает только те строки, мы заинтересованы
  if($invert == 0)
  {
    $data = preg_grep("/$grepKeyword/",$data);
  }
  else
  {
    $data = preg_grep("/$grepKeyword/",$data, PREG_GREP_INVERT);
  }

  // Иициируем массив с IP адресами и соответствующими им именами
  $ip[] = '192.168.20.116'; $name[] = 'Карабанов';
  $ip[] = '192.168.20.118'; $name[] = 'Ромашка';
  $ip[] = '192.168.20.112'; $name[] = 'Лена';
  $data = str_replace($ip, $name, $data);


  // Если последний элемент массива представляет собой пустую строку удаляем его
  if(end($data) == '') array_pop($data);

  $test = array();

  foreach($data as $val)
  {
    preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $val, $matches);

    $ip = '';
    $community = '';

    $ip = ip2long($matches[0]);

    // Вычисляем комьюнити
    if($ip == -1062726654 || $ip == -1062726651)
    {
       $community = 'CoreBILDER';
    }
    elseif($ip >= -1062726653 && $ip <= -1062726620)
    {
      $community = 'FiberCore';
    }
    elseif($ip >= -1062726556 && $ip <= -1062725633)
    {
       $community = 'DLINKB';
    }

    // Ковертируем число обратно в IP
    $ip = long2ip($ip);

    $snmp_location = format_snmp_string(snmpget($ip, $community, '.1.3.6.1.2.1.1.6.0', 50000));

    //$test[] = '<h2 class="warning host_name">'.$ip.' --> '.$community.'</h2><div class="warning message_body">'.$val.'</div>';
    $test[] = '<h2 class="host_name warning">'.$matches[0].' --> '.$snmp_location.'</h2><div class="warning message_body">'.$val.'</div>';
    //$test[] = $matches[0].' --> '.$snmp_location.'<br>';
  }

  //return print_r($test);
  return json_encode(array('size' => $fsize, 'data' => $test));
}

 // Эта функция будет распечатать необходимый HTML / CSS / JS
 function generateGUI($log) {
 ?>
 <!DOCTYPE html>
 <html>
 <head>
 <title><?php echo basename($log); ?></title>

 <meta http-equiv="content-type" content="text/html; charset=utf-8">

 <link type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/themes/flick/jquery-ui.css" rel="stylesheet">


<style type="text/css">

 #grepKeyword, #settings {
   font-size: 80%;
 }

 .float{
  z-index:100;
 }

 </style>

 <link type="text/css" href="./css/style.css" rel="stylesheet">

 <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>
 <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>

  <script type="text/javascript">

    //Last know size of the file
    lastSize = <?php echo filesize($log); ?>;
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
 $( "#settings" ).dialog('close');
 }
 });

 //Focus on the textarea
 $("#grep").focus();

 //Settings button into a nice looking button with a theme
 $("#grepKeyword").button();

 //Settings button opens the settings dialog
 $("#grepKeyword").click(function(){
 $( "#settings" ).dialog('open');
 $("#grepKeyword").removeClass('ui-state-focus');
 });


  //Set up an interval for updating the log. Change updateTime in the PHPTail contstructor to change this
  setInterval ( "updateLog()",  300);

  //Some window scroll event to keep the menu at the top
  $(window).scroll(function(e)
  {
    if ($(window).scrollTop() > 0)
    {
      $('.float').css({ position: 'fixed' , width: '100%' , top: '0' , left: 'auto' });
    }
    else
    {
      $('.float').css({ position: 'static' });
    }
  });

  //If window is resized should we scroll to the bottom?
  $(window).resize(function()
  {
    if(scroll)
    {
      scrollToBottom();
    }
 });


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
    }
  });

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
    $.getJSON('?ajax=1&lastsize='+lastSize + '&grep='+grep + '&invert='+invert, function(data) {

    lastSize = data.size;

    $.each(data.data, function(key, value)
    {
      //var keywords = '192.168.20.116 WARN INFO';
      //keywords = keywords.split(" ");
      //value = value.replace(new RegExp('('+keywords.join('|')+')',"ig"),"<b>$1</b>");

      $("#results").append(value);
    });

 scrollToBottom();

 });

}


 </script>
 </head>

<body>
  <div class="float">
<header>
<h1><a href="/">Острый глаз</a></h1>
<h2>Файл: <?php echo $log; ?></h2>

  <div style="right:0; position:absolute; top:10px; width:200px;">
    <span id="grepspan">Grep keyword: ""</span>
    <span id="invertspan">Inverted: false</span>
    <button id="grepKeyword">Опции...</button>
  </div>

</header>
<nav>
  <ul>
    <li><a href="index.php">Меню<a/></li>
  </ul>
</nav>
</div>

  <div id="settings" title="PHPTail settings">
    <p>Grep keyword (return results that contain this keyword)</p>
    <input id="grep" type="text" value=""/>
    <p>Should the grep keyword be inverted? (Return results that do NOT contain the keyword)</p>
    <div id="invert">
      <input type="radio" value="1" id="invert1" name="invert" /><label for="invert1">Yes</label>
      <input type="radio" value="0" id="invert2" name="invert" checked="checked" /><label for="invert2">No</label>
    </div>
  </div>

 <div id="results"></div>
 </body>
 </html>

 <?php
 }
?>
