<?php
//const clientID = "2274003"                      //VK for Android app client_id
//const clientSecret = "hHbZxrka2uZ6jB1inYsH"     //VK for Android app client_secret
//const clientID = "3697615"                      //VK for Windows app client_id
//const clientSecret = "AlVXZFMUqyrnABp8ncuU"     //VK for Windows app client_secret
//const clientID = "3140623"                        //VK for iPhone app client_id
//const clientSecret = "VeWdmVclDCtn6ihuP1nt"       //VK for iPhone app client_secret


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

$client_id = 3140623;

$client_secret = 'VeWdmVclDCtn6ihuP1nt';

$login = 'login';

$password = 'password';

//...список групп, откуда берём картинки
$groups = array(-168416289, -168416289, -168416289);

//...слова для фильтрации (чтобы не отправить рекламную картинку/конкурс и тд)
$stop_words = array('конкурс','розыгрыш','приз','итоги','результаты');

if (!isset($_REQUEST)) {
    return;
}

//Строка для подтверждения адреса сервера из настроек Callback API
$confirmationToken = 'confirmationToken';

//Ключ доступа сообщества
$token = 'token';

// Secret key
$secretKey = 'secretKey';

//Получаем и декодируем уведомление
$data = json_decode(file_get_contents('php://input'));

// проверяем secretKey
if(strcmp($data->secret, $secretKey) !== 0 && strcmp($data->type, 'confirmation') !== 0)
    return;

//Проверяем, что находится в поле "type"
switch ($data->type) {
    //Если это уведомление для подтверждения адреса сервера...
    case 'confirmation':
        //...отправляем строку для подтверждения адреса
        echo $confirmationToken;
        break;

    //Если это уведомление о новом сообщении...
    case 'message_new':
        //...получаем id его автора
        $userId = $data->object->user_id;

        //...получаем тело сообщения и приводим его к нижнему регистру
        $body_var = $data->object->body;
        $body = mb_strtolower($body_var);
        

        //..проверяем, есть ли в тексте сообщения запрос на отправку картинки
        if ((strpos($body, 'лоли') !== false) or (strpos($body, 'loli') !== false) or (strpos($body, 'тян') !== false) or (strpos($body, 'лолю') !== false) or (strpos($body, 'лоля') !== false)) {
            try {
            	//...если есть файл с токеном пользователя - читает его из файла, если нет - получает токен и пишет в файл
                $accessToken = getToken($client_id, $client_secret, $login, $password);

                //...выбираем случайную группу из списка
                $rand_elem = array_rand($groups);
                $group_id = $groups[$rand_elem];

                //...получаем 100 первых записей со стены этой группы
                $request_params = array(
                        'owner_id' => $group_id,
                        'count' => 100,
                    	'filter' => 'owner',
                        'v' => '5.52',
                        'access_token' => $accessToken
                    );

                $get_params = http_build_query($request_params);
                $posts = json_decode(file_get_contents('https://api.vk.com/method/wall.get?'. $get_params));
                $posts_array = $posts->response->items;

                $loli = '';

                //...пока не получим объект фото - выбираем случайный пост из ста и проверяем: есть ли прикреплённые изображения/нет ли стоп-слов в тексте поста/не является ли пост рекламным
                while (!$loli) {
                  $got_stop_words = FALSE;
                  $i = rand(2, 99);
                  $post = $posts_array[$i];
                  foreach ($stop_words as $word) {
                    if (strpos(mb_strtolower($post->text), $word) !== false) {
                      $got_stop_words = TRUE;
                      break;
                    }
                  }
                  if (($post->attachments) && !$got_stop_words && ($post->marked_as_ads == 0)) {
                    $attachments = $post->attachments;
                    foreach ($attachments as $attachment) {
                      if ($attachment->type == 'photo') {
                        $photo = $attachment->photo;
                        $loli = sprintf( 'photo%d_%d', $photo->owner_id, $photo->id);
                        break;
                      }
                    }
                  }
                }

                //С помощью messages.send и токена сообщества отправляем ответное сообщение с картинкой
                $request_params = array(
                'user_id' => $userId,
                'attachment' => $loli,
                'access_token' => $token,
                'read_state' => 1,
                'v' => '5.0'
                );

                $get_params = http_build_query($request_params);

                file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);

                //Возвращаем "ok" серверу Callback API
                echo('ok');

                break;

            } catch (Exception $e) {}
        } else {
        	//...если в сообщении не было запроса на картинку - сразу отправляем ответ
            $request_params = array(
                'message' => "Я обязательно прочту твоё сообщение, как только смогу 😉<br>".
                                "Не скучай, можешь пока полистать стену нашей группы)",
                'user_id' => $userId,
                'access_token' => $token,
                'read_state' => 1,
                'v' => '5.0'
            );

            $get_params = http_build_query($request_params);

            file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);

            //Возвращаем "ok" серверу Callback API
            echo('ok');

            break;
        }

    // Если это уведомление о выходе из группы
    case 'group_leave':
        //...получаем id вышедшего участника
        $userId = $data->object->user_id;

        //С помощью messages.send и токена сообщества отправляем сообщение
        $request_params = array(
            'message' => "Жаль, что ты уже уходишь!<br>" .
                            "Если захочешь вернуться - здесь всегда тебе рады.<br>" .
                            "Удачи тебе ヽ(・∀・)ﾉ",
            'user_id' => $userId,
            'access_token' => $token,
            'read_state' => 1,
            'v' => '5.0'
        );

        $get_params = http_build_query($request_params);

        file_get_contents('https://api.vk.com/method/messages.send?' . $get_params);

        //Возвращаем "ok" серверу Callback API
        echo('ok');

        break;
}
?>