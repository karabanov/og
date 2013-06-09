  // Предыдущий размер файла
  lastSize = "";
  // Ключевые слова
  grep = "";
  // Надоли исключать записи содержащие ключевые слова?
  invert = 0;
  // Предыдущая высота страницы
  documentHeight = 0;
  // Предыдущая позицыя прокрутки
  scrollPosition = 0;
  // Надо ли прокручивать вниз?
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
      $("#grepspan").html("Ключевые слова: \"" + grep + "\"");
      $("#invertspan").html("Игнорировать: " + (invert == 1 ? 'true' : 'false'));
      }});

 // Закрываем диалог
 $('#grep').keyup(function(e) {
 if(e.keyCode == 13) {$( "#settings" ).dialog('close');}});

 // Ставим фокус на текстовое поле
 $("#grep").focus();

 // Приятная глазу кнопка
 $("#grepKeyword").button();

 // Кнопка открывает диалоог настроек
 $("#grepKeyword").click(function(){
 $( "#settings" ).dialog('open');
 $("#grepKeyword").removeClass('ui-state-focus');});


 // Приятная глазу кнопка убрать шум
 $("#Remove_noise").button();
 // При клике убираем шум
 nois = 0;
 $("#Remove_noise").click(function(){
 if(nois == 0)
 {
   $("#Remove_noise").addClass('ui-state-focus');
   $("#Remove_noise").html("Показать шум");
   $("#noise_span").css({'color' : '#107d10'});
   $("#noise_span").html("Шумоподавление включено!")
   grep = "computer";
   invert = 1;
   nois = 1;
 }
 else
 {
   $("#Remove_noise").removeClass('ui-state-focus');
   $("#Remove_noise").html("Убрать шум");
   $("#noise_span").css({'color' : '#b51515'});
   $("#noise_span").html("Шумоподавление выключено!")

   grep = "";
   invert = 0;
   nois = 0;}});


  // Выставляем интервал через котоый будет проверятся не появилась ли в логе новая запись
  setInterval( "updateLog()",  300);

  // Очтавляем верхнюю панель неподвижнй если страница начинает прокручиваться
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

  // Если размер окна изменился проверяем надо ли прокручивать вниз
  $(window).resize(function(){if(scroll){scrollToBottom();}});


  // Обработчик прокрутки окна
  $(window).scroll(function()
  {
    documentHeight = $(document).height();
    scrollPosition = $(window).height() + $(window).scrollTop();
    if(documentHeight <= scrollPosition)
    {
      scroll = true;
    }
    else{ scroll = false; }});

    scrollToBottom();

  });

  // Эта функция прокручивает вниз
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

  // Эта функция запрашивает на сервере обновления
  function updateLog()
  {
    $.getJSON('PHPTail.php?ajax=1&lastsize=' + lastSize + '&grep=' + grep + '&invert=' + invert, function(data) {

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
