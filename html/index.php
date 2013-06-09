<!DOCTYPE html>
<html>
<head>
<title>Неизвестно</title>

<meta charset="utf-8">

<link type="text/css" href="./css/jquery-ui.css" rel="stylesheet">
<link type="text/css" href="./css/style.css" rel="stylesheet">

<script type="text/javascript" src="./js/jquery.min.js"></script>
<script type="text/javascript" src="./js/jquery-ui.min.js"></script>
<script type="text/javascript" src="./js/script.js"></script>

</head>

<body>
  <div class="float">
    <header>
      <h1><a href="/">Острый глаз</a></h1>
      <h2>Файл: Неизвестно</h2>

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
        <span id="grepspan">Ключевые слова: ""</span>
        <span id="invertspan">Инвернировать: Нет</span>
        <button id="grepKeyword">Настройки...</button>
        <button id="Remove_noise">Убрать шум</button>
      </div>
    </header>

    <nav>
      <ul>
        <li><a href="index.php">Меню</a></li>
        <li><a href="index.php"><span style="color:#b51515; font-weight: bold;">β</span><span style="font-size:0.3em;">-версия (иногда кажет, что порт падает 3 и более раз подряд, пока не понятно, что это за фича)</a></span></li>
        <li><span id="noise_span" style="color:#b51515;">Шумоподавление выключено!</span></li>
      </ul>
    </nav>
  </div>

  <div id="settings" title="Острый галз: Настройки">
    <p>Ключевые слова: (Показывать строки содержащие ключевые слова)</p>
    <input id="grep" type="text" value="">
    <p>Не показывать строки содержащие ключевые слова</p>
    <div id="invert">
      <input type="radio" value="1" id="invert1" name="invert" /><label for="invert1">Да</label>
      <input type="radio" value="0" id="invert2" name="invert" checked="checked" /><label for="invert2">Нет</label>
    </div>
  </div>

  <article id="results"></article>
</body>
</html>
