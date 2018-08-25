<?php
include "vk_api.php"; //Подключаем библиотеку для работы с api vk

function getToken($client_id, $client_secret, $login, $password) {
	$file = 'access_token.txt';
	if (is_file($file)) {
		$accessToken = file_get_contents($file);
	} else {
		$getToken = json_decode(file_get_contents('https://oauth.vk.com/token?grant_type=password&client_id='.$client_id.'&client_secret='.$client_secret.'&username='.$login.'&password='.$password.'&v=5.37&2fa_supported=1'));
	    $accessToken = $getToken->access_token;
	}
	file_put_contents($file, $accessToken);
	return $accessToken;
}

//**********CONFIG**************
const CLIENT_ID = 3140623;
const CLIENT_SECRET = 'VeWdmVclDCtn6ihuP1nt';
const LOGIN = 'login';
const PASSWORD = 'password';
const GROUP_TOKEN = 'group_token'; //тот самый длинный ключ доступа сообщества
const CONFIRMATION_TOKEN = 'confirmation_token'; //например c40b9566, введите свой
const SECRET_KEY = 'secret_key';
const API_VERSION = '5.80'; //ваша версия используемого api
const ADMIN_ID = 123456; //тот, кому будет прислано оповещение при необходимости связаться с админом
define('ACCESS_TOKEN', getToken(CLIENT_ID, CLIENT_SECRET, LOGIN, PASSWORD));

define('PICTURE_GROUPS', array(-166989747, -162289145, -168649092)); //список групп, откуда берём картинки
define('GIF_GROUPS', array(-39615703, -152567386)); //список групп, откуда берём gif-ки
define('ANIME_GROUPS', array(-98592298)); //список групп, откуда берём новинки аниме
define('STOP_WORDS', array('конкурс','розыгрыш','приз','итоги','результаты')); //слова для фильтрации (чтобы не отправить рекламную картинку/конкурс и тд)
//******************************

const BTN_IMG =  [["action" => 'send_img'], "лоли", "blue"]; 
const BTN_GIF =  [["action" => 'send_gif'], "гиф", "blue"]; 
const BTN_ANIME = [["action" => 'send_gif'], "аниме", "blue"]; 
const BTN_ADMIN = [["action" => 'letter_to_admin'], "Связаться с админом", "white"]; 

if (!isset($_REQUEST)) {
    return;
}

$data = json_decode(file_get_contents('php://input')); //Получает и декодирует JSON пришедший из ВК

if(strcmp($data->secret, SECRET_KEY) !== 0 && strcmp($data->type, 'confirmation') !== 0) // проверяем secretKey
    return;
    
$vk = new vk_api(GROUP_TOKEN, API_VERSION); // создание экземпляра класса работы с api, принимает ключ и версию api

//Проверяем, что находится в поле "type"
switch ($data->type) {
    
    case 'confirmation': //Если это уведомление для подтверждения адреса сервера
        exit(CONFIRMATION_TOKEN); //Завершаем скрипт отправкой ключа

    case 'message_new': //Если это уведомление о новом сообщении
    	$peer_id = $data->object->from_id; //Получаем id пользователя, который написал сообщение
		$message = $data->object->text;
		$message = mb_strtolower($message);
		if (isset($data->object->payload)){  //получаем payload
	        	$payload = json_decode($data->object->payload, True);
	   	} else {
	      		$payload = null;
	   	}
	   	if ($message == 'начать' or $message == 'start') { //Если нажата кнопка начать 
  			$resp = $vk->sendButton($peer_id, 'Привет, рада тебя видеть 💖<br>'.
											'В нашей группе работает лоли-бот 😊<br>'.
											'лоли/loli - случайная картинка лолечки ❤<br>'.
											'гиф/gif - случайная аниме-гифка 🌈<br>'.
											'аниме/anime - и она посоветует аниме ✨', 
											[ //Отправляем кнопки пользователю
						  						[BTN_IMG, BTN_GIF, BTN_ANIME],
						  						[BTN_ADMIN]
						  					]);

  			$vk->sendOK(); //Говорим vk, что мы приняли callback
            break;
        //..проверяем, есть ли в тексте сообщения запрос на отправку картинки
        } elseif ((strpos($message, 'лоли') !== false) or (strpos($message, 'loli') !== false) or (strpos($message, 'тян') !== false) or (strpos($message, 'лолю') !== false) or (strpos($message, 'лоля') !== false)) {
                //...выбираем случайную группу из списка
                $rand_elem = array_rand(PICTURE_GROUPS);
                $group_id = PICTURE_GROUPS[$rand_elem];
                $user = new vk_api(ACCESS_TOKEN, API_VERSION); // создание экземпляра класса работы с api, принимает ключ и версию api
                $posts = $user->request('wall.get', ['owner_id' => $group_id,
							                        'count' => 100,
							                    	'filter' => 'owner',
							                        'access_token' => ACCESS_TOKEN,
							                    	'v' => API_VERSION]);
                $posts_array = $posts['items'];
                //...пока не получим объект фото - выбираем случайный пост из ста и проверяем: есть ли прикреплённые изображения/нет ли стоп-слов в тексте поста/не является ли пост рекламным
                while (!isset($loli)) {
                  $got_stop_words = FALSE;
                  $i = rand(2, 99);
                  $post = $posts_array[$i];
                  foreach (STOP_WORDS as $word) {
                    if (strpos(mb_strtolower($post['text']), $word) !== false) {
                      $got_stop_words = TRUE;
                      break;
                    }
                  }
                  if (isset($post['attachments']) && !$got_stop_words && ($post['marked_as_ads'] == 0)) {
                    $attachments = $post['attachments'];
                    foreach ($attachments as $attachment) {
                      if ($attachment['type'] == 'photo') {
                        $photo = $attachment['photo'];
                        $loli = sprintf( 'photo%d_%d', $photo['owner_id'], $photo['id']);
                        break;
                      }
                    }
                  }
                }

                //С помощью messages.send и токена сообщества отправляем ответное сообщение с картинкой
                $resp = $vk->request('messages.send', ['message' => 'Посмотри, что я нашла для тебя:',
									                'peer_id' => $peer_id,
									                'attachment' => $loli,
									                'access_token' => GROUP_TOKEN,
							                     	'v' => API_VERSION]);

                $vk->sendOK(); //Говорим vk, что мы приняли callback
                break;
        //.. проверяем, есть ли в тексте сообщения запрос на отправку гифки
        } elseif ((strpos($message, 'гиф') !== false) or (strpos($message, 'gif') !== false)) {
        		//...выбираем случайную группу из списка
                $rand_elem = array_rand(GIF_GROUPS);
                $group_id = GIF_GROUPS[$rand_elem];
                $user = new vk_api(ACCESS_TOKEN, API_VERSION); // создание экземпляра класса работы с api, принимает ключ и версию api
                $posts = $user->request('wall.get', ['owner_id' => $group_id,
							                        'count' => 100,
							                    	'filter' => 'owner',
							                        'access_token' => ACCESS_TOKEN,
							                    	'v' => API_VERSION]);
                $posts_array = $posts['items'];
                //...пока не получим объект гиф - выбираем случайный пост из ста и проверяем: есть ли прикреплённые изображения/нет ли стоп-слов в тексте поста/не является ли пост рекламным
                while (!isset($loli)) {
                  $got_stop_words = FALSE;
                  $i = rand(2, 99);
                  $post = $posts_array[$i];
                  foreach (STOP_WORDS as $word) {
                    if (strpos(mb_strtolower($post['text']), $word) !== false) {
                      $got_stop_words = TRUE;
                      break;
                    }
                  }
                  if (isset($post['attachments']) && !$got_stop_words && ($post['marked_as_ads'] == 0)) {
                    $attachments = $post['attachments'];
                    foreach ($attachments as $attachment) {
                      if ($attachment['type'] == 'doc') {
                        $doc = $attachment['doc'];
                        if ($doc['ext'] == 'gif'){
                        	$loli = sprintf( 'doc%d_%d', $doc['owner_id'], $doc['id']);
                        	break;
                        }
                      }
                    }
                  }
                }

                //С помощью messages.send и токена сообщества отправляем ответное сообщение с картинкой
                $resp = $vk->request('messages.send', ['message' => 'Посмотри, что я нашла для тебя:',
    									                'peer_id' => $peer_id,
    									                'attachment' => $loli,
								                        'access_token' => GROUP_TOKEN,
								                        'v' => API_VERSION]);

                $vk->sendOK(); //Говорим vk, что мы приняли callback
                break;
         //.. проверяем, есть ли в тексте сообщения запрос на аниме
        } elseif ((strpos($message, 'аниме') !== false) or (strpos($message, 'anime') !== false) or (strpos($message, 'анимэ') !== false)) {
            	//...выбираем случайную группу из списка
                $rand_elem = array_rand(ANIME_GROUPS);
                $group_id = ANIME_GROUPS[$rand_elem];
                $user = new vk_api(ACCESS_TOKEN, API_VERSION); // создание экземпляра класса работы с api, принимает ключ и версию api
                $posts = $user->request('wall.get', ['owner_id' => $group_id,
							                        'count' => 100,
							                    	'filter' => 'owner',
							                        'access_token' => ACCESS_TOKEN,
							                    	'v' => API_VERSION]);
                $posts_array = $posts['items'];

                while (!isset($resp)) {
                    //...пока не получим аниме - выбираем случайный пост из ста и проверяем:нет ли стоп-слов в тексте поста/не является ли пост рекламным
                    while (!isset($loli)) {
                      $got_stop_words = FALSE;
                      $i = rand(2, 99);
                      $post = $posts_array[$i];
                      foreach ($stop_words as $word) {
                        if (strpos(mb_strtolower($post['text']), $word) !== false) {
                          $got_stop_words = TRUE;
                          break;
                        }
                      }
                      if (isset($post['attachments']) && !$got_stop_words && ($post['marked_as_ads'] == 0)) {
                        $text = '';
                        $attachments = $post['attachments'];
                        foreach ($attachments as $attachment) {
                          if ($attachment['type'] == 'doc') {
                                $doc = $attachment['doc'];
                                $loli .= sprintf( 'doc%d_%d,', $doc['owner_id'], $doc['id']);
                            }
                          if ($attachment['type'] == 'photo') {
                                $photo = $attachment['photo'];
                                $text .= $photo['text'];
                                $loli .= sprintf( 'photo%d_%d,', $photo['owner_id'], $photo['id']);
                            }
                          if ($attachment['type'] == 'video') {
                                $video = $attachment['video'];
                                $loli .= sprintf( 'video%d_%d,', $video['owner_id'], $video['id']);
                            }
                      }
                    }
                  }

                //С помощью messages.send и токена сообщества отправляем ответное сообщение с аниме
                try{$resp = $vk->request('messages.send', ['message' => $text,
											                'peer_id' => $peer_id,
											                'attachment' => $loli,
									                        'access_token' => GROUP_TOKEN,
									                        'v' => API_VERSION]);
            		} catch (Exception $e) {}
                }

                $vk->sendOK(); //Говорим vk, что мы приняли callback
                break;

        } else {
        	if ($payload != null && $payload['action']=='letter_to_admin') { // если payload существует
			    $resp = $vk->request('messages.send', ['message' => sprintf( "С тобой хочет связаться @id%d", $peer_id),
										                'peer_id' => ADMIN_ID,
								                        'access_token' => GROUP_TOKEN,
								                        'v' => API_VERSION]);
			    $resp = $vk->request('messages.send', ['message' => "Админу отправлено оповещение, скоро он с тобой свяжется)",
										                'peer_id' => $peer_id,
								                        'access_token' => GROUP_TOKEN,
								                        'v' => API_VERSION]);
				$vk->sendOK(); //Говорим vk, что мы приняли callback
				break;

			} else {
	        	//...если в сообщении не было запроса на картинку - сразу отправляем ответ
	            $resp = $vk->request('messages.send', ['message' => "Я обязательно прочту твоё сообщение, как только смогу 😉<br>".
	                                								"Не скучай, можешь пока полистать стену нашей группы)",
										                'peer_id' => $peer_id,
										                'attachment' => $loli,
								                        'access_token' => GROUP_TOKEN,
								                        'v' => API_VERSION]);

	            $vk->sendOK(); //Говорим vk, что мы приняли callback
	            break;
        	}
        }

    // Если это уведомление о выходе из группы
    case 'group_leave':
        
        $peer_id = $data->object->user_id; // получаем id вышедшего участника

        //С помощью messages.send и токена сообщества отправляем сообщение
        $resp = $vk->request('messages.send', ['message' => "Жаль, что ты уже уходишь!<br>" .
								                            "Если захочешь вернуться - здесь всегда тебе рады.<br>" .
								                            "Удачи тебе ヽ(・∀・)ﾉ",
											                'peer_id' => $peer_id,
									                        'access_token' => GROUP_TOKEN,
									                        'v' => API_VERSION]);

        $vk->sendOK(); //Говорим vk, что мы приняли callback
        break;
}

?>
