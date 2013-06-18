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

  $log = '/var/log/dlink.log';
  $lastFetchedSize = $_GET['lastsize'];
  $grepKeyword = $_GET['grep'];
  $invert = $_GET['invert'];

  // Максимальный разрешонный размер загружаемого лога
  $maxSizeToLoad = 2097152;

  // Очистить стат кэш, чтобы получить последние результаты
  clearstatcache();

  // Определите, сколько мы должны загрузить из файла журнала
  $fsize = filesize($log);

  // Если к скрипту произошло первое обращение прискаиваем  $lastFetchedSize значение $fsize
  if(empty($lastFetchedSize)) $lastFetchedSize = $fsize;

  $maxLength = ($fsize - $lastFetchedSize);

  // Убедитесь, что мы не загружать больше данных, чем разрешено
  if($maxLength > $maxSizeToLoad)
  {
    echo json_encode(array('size' => $fsize, 'data' => array('ERROR: PHPTail попытался загрузить больше ('.round(($maxLength / 1048576), 2).'MB) чем максимальный размер ('.round(($maxSizeToLoad / 1048576), 2).'MB) в байтах в памяь. Вы должны уменьшить $defaultUpdateTime, чтобы этого не происходило.<br><br>')));
  }

  // Вы этот массив будем добавлять данные
  $data = array();

  if($maxLength > 0)
  {
    $fp = fopen($log, 'r');
    fseek($fp, -$maxLength , SEEK_END);
    $data = explode("\n", fread($fp, $maxLength));
  }

  // Если последний элемент массива представляет собой пустую строку удаляем его
  if(end($data) == '') array_pop($data);

  // В этот масси будем добавлять обработанные данные и, в последствии, отдадим его клиенту
  $out = array();

  foreach($data as $line)
  {
    // Вычисляем IP
    preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/', $line, $ip);

    // Вычисляем community
    $community = get_community($ip[0]);

    // Вычисляем порт и выясняем его описане
    $snmp_description = '***НЕИЗВЕСТНО***';
    if(preg_match('/Port\s(\d{1,2})/', $line, $ports) || preg_match('/GigabitEthernet\s.*\/(\d{1,2})/', $line, $ports) || preg_match('/Ethernet.*\d\/(\d{1,2})/', $line, $ports))
    {
      $snmp_description = format_snmp_string(@snmpget($ip[0], $community, '.1.3.6.1.2.1.31.1.1.1.18.'.$ports[1], 50000));
      if(empty($snmp_description)) $snmp_description = '***НЕИЗВЕСТНО***';
    }

    // Выясняем где распологается коммутатор
    $snmp_location = format_snmp_string(@snmpget($ip[0], $community, '.1.3.6.1.2.1.1.6.0', 50000));

    // Выбираем оформление для записи
    if(strstr($line, ' loop'))
    {
      $class = 'loop';
    }
    elseif(strstr($line, ' down') || strstr($line, ' DOWN') || strstr($line, 'HALF') || strstr($line, '10M') || strstr($line, '100M'))
    {
      $class = 'down';
    }
    elseif(strstr($line, ' %SYS-6-CLOCKUPDATE:'))
    {
      $class = 'notice';
    }
    elseif(strstr($line, ' up') || strstr($line, ' UP') || strstr($line, ' recovered') || strstr($line, 'FULL'))
    {
      $class = 'up';
    }
    elseif(strstr($line, ' timed') || strstr($line, ' cold') || strstr($line, ' failed') || strstr($line, ' Logout') || strstr($line, ' logout') || strstr($line, ' Successful') || strstr($line, ' successfully') || strstr($line, ' save') || strstr($line, '%SYS-5-CONFIG_I') || strstr($line, 'SPANTREE') || strstr($line, 'INVALIDSOURCEADDRESSPACKET') || strstr($line, 'DOS_DETECTED'))
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

    $patterns[] = '/.*LINK-3-UPDOWN.*Interface\s(.*)\,.*up/';
    $replacements[] = "Пднялся физлинк  на интерфейсе <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*GigabitEthernet\s(.*)\,.*up/';
    $replacements[] = "Пднялся Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*Ethernet(.*)\,.*UP/';
    $replacements[] = "Пднялся Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINK-3-UPDOWN.*GigabitEthernet\s(.*)\,.*down/';
    $replacements[] = "Упал физлинк в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*LINK-3-UPDOWN.*Interface\s(.*)\,.*down/';
    $replacements[] = "Упал физлинк на интерфейсе <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*GigabitEthernet\s(.*)\,.*down/';
    $replacements[] = "Упал Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%DUPLEX-7-CHANGE.*Ethernet(.*)\,.*FULL/';
    $replacements[] = "Порт <strong>$1</strong> переключен в режим <strong>FULL DUPLEX</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%DUPLEX-7-CHANGE.*Ethernet(.*)\,.*HALF/';
    $replacements[] = "Порт <strong>$1</strong> переключен в режим <strong>HALF DUPLEX</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%SPEED-8-CHANGE.*Ethernet(.*)\,.*(10M|100M|1000M|1G)/';
    $replacements[] = "Скорость в порту <strong>$1</strong> изменена на <strong>$2</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*LINK-5-CHANGED.*GigabitEthernet\s(.*)\,.*administratively\sdown/';
    $replacements[] = "Администратор выключил порт <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*%LINEPROTO-5-UPDOWN.*Ethernet(.*)\,.*DOWN/';
    $replacements[] = "Упал Ethernet протокол в порту <strong>$1</strong> это <strong>".$snmp_description.'</strong>';

    $patterns[] = '/.*\s(vty\d?)\s?\((\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\)/';
    $replacements[] = "Конфигурация сохранена пользователем с IP <strong>$2</strong> c консоли <strong>$1</strong>";

    $patterns[] = '/.*SPANTREE-5-ROOTCHANGE.*instance\s(\d).*GigabitEthernet\s(.*)\.\s.*\s(.*)\./';
    $replacements[] = "Корневой порт изменён. Новый корнеавой порт <strong>$2</strong>. Новый корневой MAC <strong>$3</strong>";

    $patterns[] = '/.*SPANTREE-5-TOPOTRAP.*Topology\sChange.*instance\s(\d).*/';
    $replacements[] = "Топология изменена";

    $patterns[] = '/.*INVALIDSOURCEADDRESSPACKET.*([0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}).*port\s(.*)\sin\svlan\s(\d{1,5})/';
    $replacements[] = "Получен некорректный пакет. Неправильный SOURCE-MAC <strong>$1</strong> из порта <strong>$2</strong> VLAN <strong>$3</strong>";

    $patterns[] = '/.*NFPP_IP_GUARD-4-DOS_DETECTED.*Host\swas\sdetected/';
    $replacements[] = "Обнаружена <strong>DoS</strong> атака";


    // Выполняем замену
    $line = preg_replace($patterns, $replacements, $line);

    $out[] = '<h2 class="host_name '.$class.'">'.$snmp_location.'&nbsp;&#8658;&nbsp;'.$ip[0].'</h2>
              <a href="telnet://'.$ip[0].'"><img src="./img/telnet-24.png" width="24" height="24" alt="telnet" class="go_telnet"></a>
              <div class="message_body '.$class.'">'.$line.'</div>';

    // Запустите GREP функция возвращает только те строки, мы заинтересованы
    if($invert == 0)
    {
      $out = preg_grep("/$grepKeyword/", $out);
    }
    else
    {
      $out = preg_grep("/$grepKeyword/", $out, PREG_GREP_INVERT);
    }

  }

  // отдаём результат клиенту
  echo json_encode(array('size' => $fsize, 'data' => $out));
?>
