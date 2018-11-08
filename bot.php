<?php

header('Content-Type: text/html; charset=utf-8');
// подрубаем API
require_once("vendor/autoload.php");

// дебаг
if(true){
    error_reporting(E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED));
    ini_set('display_errors', 1);
}
//бд хостинга
$mysqli = new mysqli('localhost', 'ch2me', 'N2z', 'ch25ame');
if (!empty($mysqli->connect_errno)) {
exit;
}

// создаем переменную бота
$token = "";
$bot = new \TelegramBot\Api\Client($token,null);

if($_GET["bname"] == "revcombot"){
    $bot->sendMessage("@burgercaputt", "Тест");
}

// если бот еще не зарегистрирован - регистируем
if(!file_exists("registered.trigger")){
    /**
     * файл registered.trigger будет создаваться после регистрации бота.
     * если этого файла нет значит бот не зарегистрирован
     */

    // URl текущей страницы
    $page_url = "https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    $result = $bot->setWebhook($page_url);
    if($result){
        file_put_contents("registered.trigger",time()); // создаем файл дабы прекратить повторные регистрации
    } else die("ошибка регистрации");
}
//--------------------------------------------------------------------------------------------------------------------------------------------------


// Команды бота
// пинг. Тестовая
$bot->command('ping', function ($message) use ($bot) {
    $bot->sendMessage($message->getChat()->getId(), 'pong!');
});

// обязательное. Запуск бота
$bot->command('start', function ($message) use ($bot) {
    $answer = 'Добро пожаловать! Для начала игры введите команду /game
    Для просмотра правил наберите команду /rules';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

$bot->command('rules', function ($message) use ($bot) {
    $answer = 'Правила: Игроки по очереди получают очки от 2 до 11, 
    победившим считается тот игрок который набрал большее 
    количество очков при этом не больше 21. Если игрок 
    набрал больше 21 очка, он считается проигравшим. Игрок 
    может в любой момент выйти из игры, но при этом 
    оппонент берет еще 1 карту.';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

// помощ
$bot->command('help', function ($message) use ($bot) {
    $answer = 'Команды:
/help - помощь
/game - игра
/rules - правила';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});


//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------

// Reply-Кнопки
	$bot->command("game", function ($message) use ($bot,$mysqli) {
    
    
    $mtext = $message->getText();
    $cid = $message->getChat()->getId();
    
    create_player($cid,$mysqli);
    
    if (get_stat($cid,'name',$mysqli)=='null'){
    	$bot->sendMessage($message->getChat()->getId(), "Введите никнейм");
    	
    }
    else{
    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }
});

	 function create_player($cid,$mysqli){
	 	$query = "SELECT `id` FROM `player_session` WHERE `id` = $cid ";
		$player = $mysqli->query($query)->fetch_row();
		if (empty($player)) {
		$mysqli->query(" INSERT INTO  `player_session` (`id`, `bot_score`,`player_score`,`start`,`stop`)VALUES ($cid,0,0,0,0);");
		$mysqli->query(" INSERT INTO  `player_stats` (`id`,`name`,`win`,`lose`)VALUES ($cid,'null',0,0);");
		}
	 }
	 
	 function get_data($cid,$column,$mysqli){
	 	$query = "SELECT `$column` FROM `player_session` WHERE `id` = $cid ";
		$data = $mysqli->query($query)->fetch_row();
		if (!empty($data)) {
			return $data[0];
		}
		return null;
	 }
	
	function set_data($cid,$column,$mysqli,$value){
	 	$query = "UPDATE `player_session` SET $column=$value WHERE `id` = $cid ";
		$data = $mysqli->query($query);
	}



	function get_stat($cid,$column,$mysqli){
	 	$query = "SELECT `$column` FROM `player_stats` WHERE `id` = $cid ";
		$data = $mysqli->query($query)->fetch_row();
		if (!empty($data)) {
			return $data[0];
		}
		return null;
	 }
	
	function set_stat($cid,$column,$mysqli,$value){
	 	$query = "UPDATE `player_stats` SET $column='$value' WHERE `id` = $cid ";
		$data = $mysqli->query($query);
	}
	
	function print_top($cid,$mysqli,$bot){
		$query="SELECT name,win FROM player_stats ORDER BY win DESC LIMIT 5";
		$data = $mysqli->query($query);
			$bot->sendMessage($cid, "Топ 5 игроков:");
		while ($row = $data->fetch_row()) {
        	
        	$bot->sendMessage($cid, "Игрок: $row[0] | Побед: $row[1]");
		 }
	}
	
	
// Отлов любых сообщений + обрабтка reply-кнопок
	$bot->on(function($Update) use ($bot,$mysqli){
	
    $message = $Update->getMessage();
    $mtext = $message->getText();
    $cid = $message->getChat()->getId();
	
	$start=get_data($cid,"start",$mysqli);
   
    if (get_stat($cid,'name',$mysqli)=='null'){

    	set_stat($cid,'name',$mysqli,strval($mtext));

    	
    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    	$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }
  
  
   //------------------ТОП----------------------------------------
   
    if($mtext=="Статистика"){
        $bot->sendMessage($message->getChat()->getId(), "Статистика:");
        $bot->sendMessage($message->getChat()->getId(), "Побед:".get_stat($cid,'win',$mysqli));
        $bot->sendMessage($message->getChat()->getId(), "Проигрышей:".get_stat($cid,'lose',$mysqli));
        $all_games=get_stat($cid,'win',$mysqli)+get_stat($cid,'lose',$mysqli);
        $all_games=(get_stat($cid,'win',$mysqli)*100)/$all_games;
        $bot->sendMessage($message->getChat()->getId(), "Ваш винрейт:".$all_games."%");
            $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"],  ["text" => "топ игроков"]]], true,true);
			 $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }

	if($mtext=="топ игроков"){
		print_top($cid,$mysqli,$bot);
		    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"]]], true, true);
    $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }


     //------------------НАЧАТЬ----------------------------------------  
    if(mb_stripos($mtext,"Начать") !== false){
	set_data($cid,"start",$mysqli,1);
	set_data($cid,"stop",$mysqli,0);
	set_data($cid,"player_score",$mysqli,rand(2,11));
	set_data($cid,"bot_score",$mysqli,rand(2,11));
    }
    
    	if (get_data($cid,"start",$mysqli)==1)  {
		                
		                if(mb_stripos($mtext,"Продолжить") !== false){

		                    set_data($cid,"player_score",$mysqli,get_data($cid,"player_score",$mysqli)+rand(2,11));
		                    set_data($cid,"bot_score",$mysqli,get_data($cid,"bot_score",$mysqli)+rand(2,11));
		                    
		                }
		
		                if(mb_stripos($mtext,"Стоп") !== false){
							set_data($cid,"stop",$mysqli,1);
							set_data($cid,"bot_score",$mysqli,get_data($cid,"bot_score",$mysqli)+rand(2,11));
		                }    		
    		
    		$player=get_data($cid,"player_score",$mysqli);
    		$casino=get_data($cid,"bot_score",$mysqli);
    		$stop=get_data($cid,"stop",$mysqli);
    		
    		
    				if ($player>21){
				    	$bot->sendMessage($message->getChat()->getId(), "Вы Проиграли! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"lose",$mysqli,get_stat($cid,"lose",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }

				    elseif ($casino>21){
				    	$bot->sendMessage($message->getChat()->getId(), "Вы Победили! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"win",$mysqli,get_stat($cid,"win",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }
		
				    elseif (($player>$casino and get_data($cid,"stop",$mysqli)==1) or $player==21 ){
				        $bot->sendMessage($message->getChat()->getId(), "Вы победили! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"win",$mysqli,get_stat($cid,"win",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }
				    
				    elseif (($player<$casino and get_data($cid,"stop",$mysqli)==1) or $casino==21){
				        $bot->sendMessage($message->getChat()->getId(), "Вы Проиграли! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"lose",$mysqli,get_stat($cid,"lose",$mysqli)+1);
				    	
				    	    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }

				    
				    
				    
				    
        		if ((get_data($cid,"stop",$mysqli)==0) and (get_data($cid,"start",$mysqli)==1)){
		        $bot->sendMessage($message->getChat()->getId(), "Ваш счет:$player | Счет казино:$casino");
		        $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Продолжить!"], ["text" => "Стоп"]]], true, true);
		        $bot->sendMessage($message->getChat()->getId(), "Хотите продолжить?", false, null, null, $keyboard);
    		}
	
    	}
    
}, function($message) use ($name){
    return true; // когда тут true - команда проходит
});<?php

header('Content-Type: text/html; charset=utf-8');
// подрубаем API
require_once("vendor/autoload.php");

// дебаг
if(true){
    error_reporting(E_ALL & ~(E_NOTICE | E_USER_NOTICE | E_DEPRECATED));
    ini_set('display_errors', 1);
}

$mysqli = new mysqli('localhost', 'ch25521_game', 'N2Xp4i7z', 'ch25521_game');
if (!empty($mysqli->connect_errno)) {
exit;
}

// создаем переменную бота
$token = "445664813:AAEKLPoQ7K34IZb1frGDtTbCMUj66vBLEi0";
$bot = new \TelegramBot\Api\Client($token,null);

if($_GET["bname"] == "revcombot"){
    $bot->sendMessage("@burgercaputt", "Тест");
}

// если бот еще не зарегистрирован - регистируем
if(!file_exists("registered.trigger")){
    /**
     * файл registered.trigger будет создаваться после регистрации бота.
     * если этого файла нет значит бот не зарегистрирован
     */

    // URl текущей страницы
    $page_url = "https://".$_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    $result = $bot->setWebhook($page_url);
    if($result){
        file_put_contents("registered.trigger",time()); // создаем файл дабы прекратить повторные регистрации
    } else die("ошибка регистрации");
}
//--------------------------------------------------------------------------------------------------------------------------------------------------


// Команды бота
// пинг. Тестовая
$bot->command('ping', function ($message) use ($bot) {
    $bot->sendMessage($message->getChat()->getId(), 'pong!');
});

// обязательное. Запуск бота
$bot->command('start', function ($message) use ($bot) {
    $answer = 'Добро пожаловать! Для начала игры введите команду /game
    Для просмотра правил наберите команду /rules';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

$bot->command('rules', function ($message) use ($bot) {
    $answer = 'Правила: Игроки по очереди получают очки от 2 до 11, 
    победившим считается тот игрок который набрал большее 
    количество очков при этом не больше 21. Если игрок 
    набрал больше 21 очка, он считается проигравшим. Игрок 
    может в любой момент выйти из игры, но при этом 
    оппонент берет еще 1 карту.';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});

// помощ
$bot->command('help', function ($message) use ($bot) {
    $answer = 'Команды:
/help - помощь
/game - игра
/rules - правила';
    $bot->sendMessage($message->getChat()->getId(), $answer);
});


//-----------------------------------------------------------------------------------------------------------------------------------------------------------------------

// Reply-Кнопки
	$bot->command("game", function ($message) use ($bot,$mysqli) {
    
    
    $mtext = $message->getText();
    $cid = $message->getChat()->getId();
    
    create_player($cid,$mysqli);
    
    if (get_stat($cid,'name',$mysqli)=='null'){
    	$bot->sendMessage($message->getChat()->getId(), "Введите никнейм");
    	
    }
    else{
    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }
});

	 function create_player($cid,$mysqli){
	 	$query = "SELECT `id` FROM `player_session` WHERE `id` = $cid ";
		$player = $mysqli->query($query)->fetch_row();
		if (empty($player)) {
		$mysqli->query(" INSERT INTO  `player_session` (`id`, `bot_score`,`player_score`,`start`,`stop`)VALUES ($cid,0,0,0,0);");
		$mysqli->query(" INSERT INTO  `player_stats` (`id`,`name`,`win`,`lose`)VALUES ($cid,'null',0,0);");
		}
	 }
	 
	 function get_data($cid,$column,$mysqli){
	 	$query = "SELECT `$column` FROM `player_session` WHERE `id` = $cid ";
		$data = $mysqli->query($query)->fetch_row();
		if (!empty($data)) {
			return $data[0];
		}
		return null;
	 }
	
	function set_data($cid,$column,$mysqli,$value){
	 	$query = "UPDATE `player_session` SET $column=$value WHERE `id` = $cid ";
		$data = $mysqli->query($query);
	}



	function get_stat($cid,$column,$mysqli){
	 	$query = "SELECT `$column` FROM `player_stats` WHERE `id` = $cid ";
		$data = $mysqli->query($query)->fetch_row();
		if (!empty($data)) {
			return $data[0];
		}
		return null;
	 }
	
	function set_stat($cid,$column,$mysqli,$value){
	 	$query = "UPDATE `player_stats` SET $column='$value' WHERE `id` = $cid ";
		$data = $mysqli->query($query);
	}
	
	function print_top($cid,$mysqli,$bot){
		$query="SELECT name,win FROM player_stats ORDER BY win DESC LIMIT 5";
		$data = $mysqli->query($query);
			$bot->sendMessage($cid, "Топ 5 игроков:");
		while ($row = $data->fetch_row()) {
        	
        	$bot->sendMessage($cid, "Игрок: $row[0] | Побед: $row[1]");
		 }
	}
	
	
// Отлов любых сообщений + обрабтка reply-кнопок
	$bot->on(function($Update) use ($bot,$mysqli){
	
    $message = $Update->getMessage();
    $mtext = $message->getText();
    $cid = $message->getChat()->getId();
	
	$start=get_data($cid,"start",$mysqli);
   
    if (get_stat($cid,'name',$mysqli)=='null'){

    	set_stat($cid,'name',$mysqli,strval($mtext));

    	
    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    	$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }
  
  
   //------------------ТОП----------------------------------------
   
    if($mtext=="Статистика"){
        $bot->sendMessage($message->getChat()->getId(), "Статистика:");
        $bot->sendMessage($message->getChat()->getId(), "Побед:".get_stat($cid,'win',$mysqli));
        $bot->sendMessage($message->getChat()->getId(), "Проигрышей:".get_stat($cid,'lose',$mysqli));
        $all_games=get_stat($cid,'win',$mysqli)+get_stat($cid,'lose',$mysqli);
        $all_games=(get_stat($cid,'win',$mysqli)*100)/$all_games;
        $bot->sendMessage($message->getChat()->getId(), "Ваш винрейт:".$all_games."%");
            $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"],  ["text" => "топ игроков"]]], true,true);
			 $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }

	if($mtext=="топ игроков"){
		print_top($cid,$mysqli,$bot);
		    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"]]], true, true);
    $bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
    }


     //------------------НАЧАТЬ----------------------------------------  
    if(mb_stripos($mtext,"Начать") !== false){
	set_data($cid,"start",$mysqli,1);
	set_data($cid,"stop",$mysqli,0);
	set_data($cid,"player_score",$mysqli,rand(2,11));
	set_data($cid,"bot_score",$mysqli,rand(2,11));
    }
    
    	if (get_data($cid,"start",$mysqli)==1)  {
		                
		                if(mb_stripos($mtext,"Продолжить") !== false){

		                    set_data($cid,"player_score",$mysqli,get_data($cid,"player_score",$mysqli)+rand(2,11));
		                    set_data($cid,"bot_score",$mysqli,get_data($cid,"bot_score",$mysqli)+rand(2,11));
		                    
		                }
		
		                if(mb_stripos($mtext,"Стоп") !== false){
							set_data($cid,"stop",$mysqli,1);
							set_data($cid,"bot_score",$mysqli,get_data($cid,"bot_score",$mysqli)+rand(2,11));
		                }    		
    		
    		$player=get_data($cid,"player_score",$mysqli);
    		$casino=get_data($cid,"bot_score",$mysqli);
    		$stop=get_data($cid,"stop",$mysqli);
    		
    		
    				if ($player>21){
				    	$bot->sendMessage($message->getChat()->getId(), "Вы Проиграли! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"lose",$mysqli,get_stat($cid,"lose",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }

				    elseif ($casino>21){
				    	$bot->sendMessage($message->getChat()->getId(), "Вы Победили! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"win",$mysqli,get_stat($cid,"win",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }
		
				    elseif (($player>$casino and get_data($cid,"stop",$mysqli)==1) or $player==21 ){
				        $bot->sendMessage($message->getChat()->getId(), "Вы победили! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"win",$mysqli,get_stat($cid,"win",$mysqli)+1);
				    	$keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }
				    
				    elseif (($player<$casino and get_data($cid,"stop",$mysqli)==1) or $casino==21){
				        $bot->sendMessage($message->getChat()->getId(), "Вы Проиграли! Ваш счет:$player | Счет казино:$casino");
				    	set_data($cid,"stop",$mysqli,0);
				    	set_data($cid,"start",$mysqli,0);
				    	set_stat($cid,"lose",$mysqli,get_stat($cid,"lose",$mysqli)+1);
				    	
				    	    $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Начать"], ["text" => "Статистика"], ["text" => "топ игроков"]]], true, true,true);
    						$bot->sendMessage($message->getChat()->getId(), "Главное меню", false, null,null, $keyboard);
				    }
   
        		if ((get_data($cid,"stop",$mysqli)==0) and (get_data($cid,"start",$mysqli)==1)){
		        $bot->sendMessage($message->getChat()->getId(), "Ваш счет:$player | Счет казино:$casino");
		        $keyboard = new \TelegramBot\Api\Types\ReplyKeyboardMarkup([[["text" => "Продолжить!"], ["text" => "Стоп"]]], true, true);
		        $bot->sendMessage($message->getChat()->getId(), "Хотите продолжить?", false, null, null, $keyboard);
    		}
	
    	}
    
}, function($message) use ($name){
    return true; // когда тут true - команда проходит
});

// запускаем обработку
$bot->run();

// запускаем обработку
$bot->run();











