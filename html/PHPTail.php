<?php

  //$line = 'Apr 7 12:38:51 192.168.20.18 *Apr 7 12:38:47: %SYS-6-CLOCKUPDATE: System clock has been updated to 12:38:47 GMT Sun Apr 7 2013.';

  //echo  preg_replace('/.*\s(\d{1,2}\:\d{1,2}\:\d{1,2}).*\s(.*)\s(.*)\s(\d{1,2})\s(\d{4}).*/u', "Время скорректировано, теперь на часах <strong>$1</strong> на календаре <strong>$2 $4 $3 $5 г.</strong>", $line);

  //exit();

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
  //$ip[] = '192.168.20.116'; $name[] = 'Оператор ТП';
  //$ip[] = '192.168.20.118'; $name[] = 'Ромашка';
  //$ip[] = '192.168.20.114'; $name[] = 'Григорчук А. С.';
  //$ip[] = '192.168.20.112'; $name[] = 'Лена';
  //$data = str_replace($ip, $name, $data);


  // Если последний элемент массива представляет собой пустую строку удаляем его
  if(end($data) == '') array_pop($data);

  $test = array();

  foreach($data as $line)
  {
    // Вычисляем IP
    preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $line, $ip);

    // Вычисляем community
    $community = get_community($ip[0]);

    // Вычисляем порт
    //if(preg_match('/Port\s(\d{1,2})/', $line, $ports))
    // {
    //  $line .= $ports[1];
    //}

    $snmp_location = format_snmp_string(snmpget($ip[0], $community, '.1.3.6.1.2.1.1.6.0', 50000));


    if(strstr($line, ' loop'))
    {
      $class = 'loop';
    }
    elseif(strstr($line, ' down'))
    {
      $class = 'down';
    }
    elseif(strstr($line, ' %SYS-6-CLOCKUPDATE:'))
    {
      $class = 'notice';
    }
    elseif(strstr($line, ' up'))
    {
      $class = 'up';
    }
    elseif(strstr($line, ' failed') || strstr($line, ' Logout') || strstr($line, ' logout') || strstr($line, ' Successful') || strstr($line, ' successfully') || strstr($line, ' save'))
    {
      $class = 'login_failed';
    }
    else
    {
      $class = 'notice';
    }

    if($community == 'FiberCore')
    {
      $snmp_location = '[РАЙОННИК]&nbsp;'.$snmp_location;
    }

    // В этом масиве будут храниться регулярки
    $patterns = array();
    // В этом массиве будут храниться заменялки
    $replacements = array();

    $patterns[0] = '/.*(Port)\s(\d{1,2})\s(link down)/';
    $replacements[0] = "Упал порт <strong>$2</strong>";

    $patterns[1] = '/.*(Port)\s(\d{1,2})\s(link up),\s(\d{1,4}Mbps).*(FULL|HALF).*(duplex)/';
    $replacements[1] = "Поднялся порт <strong>$2</strong>, линк <strong>$4 $5</strong> $6";

    $patterns[2] = '/.*Port\s(\d{1,2}).*loop.*/';
    $replacements[2] =  "ВНИМАНИЕ! Загружается игр... Всмысле обнаружена петля! Порт <strong>$1</strong> заблокирован";

    $patterns[3] = '/.*Successful\slogin\sthrough\s(Telnet|Web).*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[3] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#107d10; font-weight: bold;'>успешно зашёл</span> через <strong>$1</strong>";

    $patterns[4] = '/.*(Telnet).*failed\sfrom\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[4] = "<span style='color:#b51515; font-weight: bold;'>Кто-то</span> с IP: <strong>$2</strong> <span style='color:#b51515; font-weight: bold;'>пытается войти</span> через <strong>$1</strong>";

    $patterns[5] = '/.*(Telnet).*User\s(.*).*login.*\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[5] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#107d10; font-weight: bold;'>успешно зашёл</span> через <strong>$1</strong>";

    $patterns[6] = '/.*Login\sfailed\sthrough\s(Telnet|Web)\s.*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[6] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#b51515; font-weight: bold;'>пытается войти</span> через <strong>$1</strong>";

    $patterns[7] = '/.*(Telnet).*User\s(.*).*logout.*\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[7] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> вышел из <strong>$1</strong>";

    $patterns[8] = '/.*Logout\sthrough\s(Telnet|Web).*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[8] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> вышел из <strong>$1</strong>";

    $patterns[9] = '/.*saved.*Username:\s(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[9] = "Конфигурация сохранена пользователем <strong>$1</strong> с IP: <strong>$2</strong>";

    $patterns[10] = '/.*\s(\d{1,2}\:\d{1,2}\:\d{1,2})\s.*\s(.*)\s(.*)\s\s(\d{1,2})\s(\d{4}).*/';
    $replacements[10] = "Время скорректировано, теперь на часах <strong>$1</strong> на календаре <strong>$2 $4 $3 $5 г.</strong>";

    $line = preg_replace($patterns, $replacements, $line);

    $test[] = '<h2 class="host_name '.$class.'">'.$snmp_location.'&nbsp;&#8658;&nbsp;'.$ip[0].'</h2>
               <a href="telnet://'.$ip[0].'"><img src="./img/telnet-24.png" width="24" height="24" alt="telnet" class="go_telnet"></a>
               <div class="message_body '.$class.'">'.$line.'</div>';
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

 #results {margin:50px 0 0 0;}

 .float{
  z-index:100;
  position: fixed;
  width: 100%;
  top: -200px;
  left: auto;

  -webkit-transition: all 1s ease-in-out;
  -moz-transition: all 1s ease-in-out;
  -o-transition: all 1s ease-in-out;
  transition: all 1s ease-in-out;

 }

.float:hover{
   -webkit-transform: translateY(200px);
   -moz-transform: translateY(200px);
   -o-transform: translateY(200px);
   transform: translateY(200px);

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
      $('.float').css({ position: 'fixed' , width: '100%' , top: '-200px' , left: 'auto' });
    }
    else
    {
      $('.float').css({ position: 'fixed' , top: '-100px' });
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
      if($('h2').length > 100) {
                         $("#results h2:first").remove();
                         $("#results a:first").remove();
                         $("#results div:first").remove();
                        }


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
    <span id="invertspan">10Mbps линки: 0</span><br>
    <span id="invertspan">Петель обнаружено: 0</span>
    <span id="invertspan">Неправильное время: 0</span>
    <span id="grepspan">Grep keyword: ""</span>
    <span id="invertspan">Inverted: false</span>
    <button id="grepKeyword">Настройки...</button>
  </div>

</header>
<nav>
  <ul>
    <li><a href="index.php">Меню</a></li>
  </ul>
</nav>
</div>

  <div id="settings" title="Острый галз: Настройки">
    <p>Grep keyword (return results that contain this keyword)</p>
    <input id="grep" type="text" value=""/>
    <p>Should the grep keyword be inverted? (Return results that do NOT contain the keyword)</p>
    <div id="invert">
      <input type="radio" value="1" id="invert1" name="invert" /><label for="invert1">Yes</label>
      <input type="radio" value="0" id="invert2" name="invert" checked="checked" /><label for="invert2">No</label>
    </div>
  </div>

 <article id="results"></article>
 </body>
 </html>

 <?php
 }
?>
