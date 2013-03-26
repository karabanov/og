<?php

require 'PHPTail.php';

$log = '/var/log/dlink.log';

if(isset($_GET['ajax']))
{
  echo getNewLines($log, $_GET['lastsize'], $_GET['grep'], $_GET['invert']);
  die();
}

generateGUI($log);
?>
