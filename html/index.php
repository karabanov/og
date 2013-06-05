<?php

include('/var/www/snmp.php');

function translite($string) {

$rus = array('щ',        'ш', 'ё', 'ю', 'я', 'ж', 'ц', 'й', 'ч', 'а','б','в','г','д','е','з','и','к','л','м','н','о','п','р','с','т','у','ф','х',
             'Щ',   'Ш', 'Ш', 'Ё', 'Ю', 'Я', 'Ж', 'Ц', 'Й', 'Ч', 'А','Б','В','Г','Д','Е','З','И','Л','Л','М','Н','О','П','Р','С','Т','У','Ф','Х');
$eng = array('shch',     'sh','yo','yu','ya','zh','ce','iy','ch','a','b','v','g','d','e','z','i','k','l','m','n','o','p','r','s','t','u','f','h',
             'SHCH','Sh','SH','YO','YU','YO','ZH','CE','IY','CH','A','B','V','G','D','E','Z','I','K','L','M','N','O','P','R','S','T','U','F','H');

        $string = str_replace($eng, $rus,  $string);
        return $string;
    }

// Эта функция отвечает за получение последней строки из файла журнала
function getNewLines($log = '', $lastFetchedSize, $grepKeyword, $invert)
{

  // Максимальный разрешонный размер загружаемого лога
  $maxSizeToLoad = 2097152;
  // Промежуток времени в миллисекундах через который скрипт дёргают что бы он  заглядывал в лог
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

    // Вычисляем порт и выясняем его описане
    $snmp_description = '***НЕИЗВЕСТНО***';

    if(preg_match('/Port\s(\d{1,2})/', $line, $ports) || preg_match('/GigabitEthernet\s.*\/(\d{1,2})/', $line, $ports))
    {
      $snmp_description = format_snmp_string(@snmpget($ip[0], $community, '.1.3.6.1.2.1.31.1.1.1.18.'.$ports[1], 50000));
      if(empty($snmp_description)) $snmp_description = '***НЕИЗВЕСТНО***';
    }

    $snmp_location = format_snmp_string(@snmpget($ip[0], $community, '.1.3.6.1.2.1.1.6.0', 50000));


    if(strstr($line, ' loop'))
    {
      $class = 'loop';
    }
    elseif(strstr($line, ' down') || strstr($line, ' DOWN'))
    {
      $class = 'down';
    }
    elseif(strstr($line, ' %SYS-6-CLOCKUPDATE:'))
    {
      $class = 'notice';
    }
    elseif(strstr($line, ' up') || strstr($line, ' UP') || strstr($line, ' recovered'))
    {
      $class = 'up';
    }
    elseif(strstr($line, ' timed') || strstr($line, ' cold') || strstr($line, ' failed') || strstr($line, ' Logout') || strstr($line, ' logout') || strstr($line, ' Successful') || strstr($line, ' successfully') || strstr($line, ' save') || strstr($line, '%SYS-5-CONFIG_I') || strstr($line, 'SPANTREE') || strstr($line, 'INVALIDSOURCEADDRESSPACKET'))
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

    if($community == 'CoreBILDER')
    {
      $snmp_location = '[ЯДРО]&nbsp;'.$snmp_location;
    }

    // В этом масиве будут храниться регулярки
    $patterns = array();
    // В этом массиве будут храниться заменялки
    $replacements = array();

    $patterns[] = '/.*(Port)\s(\d{1,2})\s(link down)/';
    $replacements[] = "Упал порт <strong>$2</strong> это <strong>".$snmp_description.'</strong>';
    //$replacements[] = "Упал порт <strong>$2</strong> это <strong>".$snmp_description.'</strong><embed src="./sound/ound.mp3" type="audio/mpeg" width="0" height="0" autostart="true" loop="false">';

    $patterns[] = '/.*(Port)\s(\d{1,2})\s(link up),\s(\d{1,4}Mbps).*(FULL|HALF).*(duplex)/';
    $replacements[] = "Поднялся порт <strong>$2</strong>, линк <strong>$4 $5</strong> $6 это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*Port\s(\d{1,2}).*loop.*/';
    $replacements[] =  "ВНИМАНИЕ! Загружается игр... Всмысле обнаружена <strong>петля</strong>! Порт <strong>$1</strong> заблокирован! Это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*Successful\slogin\sthrough\s(Telnet|Web).*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#107d10; font-weight: bold;'>успешно зашёл</span> через <strong>$1</strong>";

    $patterns[] = '/.*Successful\slogin\sthrough\s(Telnet|Web).*Username:(.*)\)/';
    $replacements[] = "Пользователь: <strong>$2</strong> <span style='color:#107d10; font-weight: bold;'>успешно зашёл</span> через <strong>$1</strong>";

    $patterns[] = '/.*(Telnet).*failed\sfrom\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "<span style='color:#b51515; font-weight: bold;'>Кто-то</span> с IP: <strong>$2</strong> <span style='color:#b51515; font-weight: bold;'>пытается войти</span> через <strong>$1</strong>";

    $patterns[] = '/.*(Telnet).*User\s(.*).*login.*\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#107d10; font-weight: bold;'>успешно зашёл</span> через <strong>$1</strong>";

    $patterns[] = '/.*Login\sfailed\sthrough\s(Telnet|Web)\s.*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> <span style='color:#b51515; font-weight: bold;'>пытается войти</span> через <strong>$1</strong>";

    $patterns[] = '/.*Login\sfailed\sthrough\s(Telnet|Web)\s.*Username:(.*)\)/';
    $replacements[] = "Пользователь: <strong>$2</strong> <span style='color:#b51515; font-weight: bold;'>пытается войти</span> через <strong>$1</strong>";

    $patterns[] = '/.*(Telnet).*User\s(.*).*logout.*\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> вышел из <strong>$1</strong>";

    $patterns[] = '/.*Logout\sthrough\s(Telnet|Web).*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> вышел из <strong>$1</strong>";

    $patterns[] = '/.*(Telnet)\ssession\stimed.*Username:(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Пользователь: <strong>$2</strong> с IP: <strong>$3</strong> был выкинут из <strong>$1</strong>, так как <strong>долго бездействовал</strong>";

    $patterns[] = '/.*timed\sout\s\(Username:\s(.*)\)/';
    $replacements[] = "Пользователь: <strong>$1</strong> был выкинут из <strong>Telnet</strong>, так как <strong>долго бездействовал</strong>";

    $patterns[] = '/.*Logout\sthrough\s(Telnet|Web).*Username:(.*)\)/';
    $replacements[] = "Пользователь: <strong>$2</strong> вышел из <strong>$1</strong>";

    $patterns[] = '/.*saved.*Username:\s(.*).?\sIP:\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}).*/';
    $replacements[] = "Конфигурация сохранена пользователем <strong>$1</strong> с IP: <strong>$2</strong>";

    $patterns[] = '/.*\s(\d{1,2}\:\d{1,2}\:\d{1,2})\s.*\s(.*)\s(.*)\s\s(\d{1,2})\s(\d{4}).*/';
    $replacements[] = "Время скорректировано, теперь на часах <strong>$1</strong> на календаре <strong>$2 $4 $3 $5 г.</strong>";

    $patterns[] = '/.*\s(\d{1,2}\:\d{1,2}\:\d{1,2})\s.*\s(.*)\s(.*)\s(\d{1,2})\s(\d{4}).*/';
    $replacements[] = "Время скорректировано, теперь на часах <strong>$1</strong> на календаре <strong>$2 $4 $3 $5 г.</strong>";

    $patterns[] = '/.*cold.*start.*/';
    $replacements[] = "Коммутатор <strong>ЗАПУСТИЛСЯ</strong>";

    $patterns[] = '/.*warm.*start.*/';
    $replacements[] = "Коммутатор <strong>ПЕРЕЗАПУСТИЛСЯ</strong>";

    $patterns[] = '/.*IGMP-3-QUERY_INT_MISMATCH.*(VRF\s.*)\:.*\s(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/';
    $replacements[] = "Пришёл <strong>левый IGMP запрос</strong> через <strong>$1</strong> c IP <strong>$2</strong>";

    $patterns[] = '/.*Port\s(\d{1,2})\s.*recovered.*/';
    $replacements[] = "Разблокирован порт <strong>$1</strong> после петли";

    $patterns[] = '/.*MODULE_CONFIG_SHELL.*successfully/';
    $replacements[] = "Конфигурация сохранена";

    $patterns[] = '/.*LINK-3-UPDOWN.*GigabitEthernet\s(.*)\,.*up/';
    $replacements[] = "Пднялся физлинк в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*GigabitEthernet\s(.*)\,.*up/';
    $replacements[] = "Пднялся Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINK-3-UPDOWN.*GigabitEthernet\s(.*)\,.*down/';
    $replacements[] = "Упал физлинк в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*GigabitEthernet\s(.*)\,.*down/';
    $replacements[] = "Упал Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*\s(vty\d?)\s?\((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)/';
    $replacements[] = "Конфигурация сохранена пользователем с IP <strong>$2</strong> c консоли <strong>$1</strong>";

    $patterns[] = '/.*SPANTREE-5-ROOTCHANGE.*instance\s(\d).*GigabitEthernet\s(.*)\.\s.*\s(.*)\./';
    $replacements[] = "Корневой порт изменён. Новый корнеавой порт <strong>$2</strong>. Новый корневой MAC <strong>$3</strong>";

    $patterns[] = '/.*SPANTREE-5-TOPOTRAP.*Topology\sChange.*instance\s(\d).*/';
    $replacements[] = "Топология изменена";

    $patterns[] = '/.*INVALIDSOURCEADDRESSPACKET.*([0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}).*port\s(.*)\sin\svlan\s(\d{1,5})/';
    $replacements[] = "Получен некорректный пакет. Неправильный SOURCE-MAC <strong>$1</strong> из порта <strong>$2</strong> VLAN <strong>$3</strong>";


// May 28 14:47:22 192.168.20.22 *May 28 14:47:17: %NFPP_IP_GUARD-4-DOS_DETECTED: Host was detected.(2013-5-28 14:47:17)

// May 31 08:24:23 192.168.20.22 *May 31 08:24:18: %NFPP_IP_GUARD-4-DOS_DETECTED: Host was detected.(2013-5-31 8:24:18)

// Jun 4 16:00:47 192.168.20.2 67798: Jun 4 12:00:46.108: %LINK-3-UPDOWN: Interface Vlan652, changed state to up

// Jun 4 21:25:56 192.168.20.22 *Jun 4 21:25:51: %NFPP_IP_GUARD-4-DOS_DETECTED: Host was detected.(2013-6-4 21:25:51)

// Jun 5 09:11:45 192.168.20.31 *Jun 5 09:11:40: %LINK-5-CHANGED: Interface GigabitEthernet 0/22, changed state to administratively down.

  $param = array();
  $param['ip'] = '192.168.21.34';
  $param['community_ro'] = 'DLINKB';
  $param['community_rw'] = 'BDLINK';
  $param['port'] = '7';

    // Выполняем замену
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

<meta charset="utf-8">

<link type="text/css" href="./css/jquery-ui.css" rel="stylesheet">
<link type="text/css" href="./css/style.css" rel="stylesheet">

<script type="text/javascript" src="./js/jquery.min.js"></script>
<script type="text/javascript" src="./js/jquery-ui.min.js"></script>
<script type="text/javascript" src="./js/script.js"></script>

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
</script>

</head>

<body>
  <div class="float">
    <header>
      <h1><a href="/">Острый глаз</a></h1>
      <h2>Файл: <?php echo $log; ?></h2>

      <div style="right:350px; position:absolute; top:10px; width:30opx; ">
        <span style="display: block; margin:4px; padding:3px; min-width: 250px; background: #f68c8c;">Порт упал</span>
        <span style="display: block; margin:4px; padding:3px; min-width: 250px; background: #9af185;">Порт поднялся</span>
        <span style="display: block; margin:4px; padding:3px; min-width: 250px; background: #ca28b0;">Обнаружена петля</span>
        <span style="display: block; margin:4px; padding:3px; min-width: 250px; background: #f1ce78;">Логины, Логауты, Сохранения</span>
        <span style="display: block; margin:4px; padding:3px; min-width: 250px; background: #e2d6d6;">Несущественные сообщения</span>
      </div>


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
        <li><a href="index.php"><span style='color:#b51515; font-weight: bold;'>β</span>-версия (иногда кажет, что порт падает 3 и более раз подряд, пока не понятно, что это за фича)</a></li>
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


$log = '/var/log/dlink.log';

if(isset($_GET['ajax']))
{
  echo getNewLines($log, $_GET['lastsize'], $_GET['grep'], $_GET['invert']);
  die();
}

generateGUI($log);


?>
