callback_vk_bot
======
**callback_vk_bot** – простой callback-бот для группы на PHP для социальной сети Вконтакте (vk.com)

При получении сообщения с командой на отправку картинки/гифки/аниме, ищет контент в случайной группе из заданного списка и присылает пользовтелю.

Если сообщение не содержит ключевой фразы - отправляет заглушку с вашим текстом (благодарность за ожидание ответа например).

При выходе пользователя из группы, также отправляет ему сообщение (для этого пользователь должен хотя бы раз отправить сообщение в группу или разрешить отправку сообщений от данной группы).

Скрипт использует библиотеку vk_api  
Автор данной библиотеки: [Runnin](https://vk.com/runnin4ik)  

Для работы программы необходимо заполнить следующие переменные:

```php
...
//**********CONFIG**************
const CLIENT_ID = 3140623;
const CLIENT_SECRET = 'VeWdmVclDCtn6ihuP1nt';
const LOGIN = 'LOGIN';
const PASSWORD = 'PASSWORD';
const GROUP_TOKEN = 'GROUP_TOKEN'; //тот самый длинный ключ доступа сообщества
const CONFIRMATION_TOKEN = 'CONFIRMATION_TOKEN'; //например c40b9566, введите свой
const SECRET_KEY = 'SECRET_KEY';
const API_VERSION = '5.80'; //ваша версия используемого api
const ADMIN_ID = 123456789;
define('ACCESS_TOKEN', getToken(CLIENT_ID, CLIENT_SECRET, LOGIN, PASSWORD));

define('PICTURE_GROUPS', array(-166989747, -162289145, -168649092)); //список групп, откуда берём картинки
define('GIF_GROUPS', array(-39615703, -152567386)); //список групп, откуда берём gif-ки
define('ANIME_GROUPS', array(-30414110)); //список групп, откуда берём новинки аниме
define('STOP_WORDS', array('конкурс','розыгрыш','приз','итоги','результаты')); //слова для фильтрации (чтобы не отправить рекламную картинку/конкурс и тд)
//******************************
...
```

Где взять эти параметры
------------
![Где взять эти параметры](https://sun1-12.userapi.com/c824203/v824203252/1a928a/EhoN1g4Gvjw.jpg)

Во вкладке "Типы событий" поставить галочки:

* Входящее сообщение
* Выход из сообщества

Внимание
------------
Токен, полученный методом прямой авторизации, работает безвременно.
То есть достаточно получить его однократно.
А нижележащий кусок кода можно закомментировать или удалить.
$client_id и $client_secret были найдены в свободном доступе в интернете.
Подробнее о прямой авторизации:
[https://vk.com/dev/auth_direct](https://vk.com/dev/auth_direct)
```php
...
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
...
```
Рекомендуется получить пользовательский токен один раз и вписать в код скрипта вот сюда:
```php
...
define('ACCESS_TOKEN', 'YourToken1235BlaBlaBla65645');
...
```
Запуск
------------
После заполнения всех параметров, необходимо разместить ваш скрипт на сервере с поддержкой php и указать адрес расположения скрипта в группе: 
Группа -> Управление -> Работа с API -> Callback API -> Настройки сервера -> Адрес.
Жмём "Подтвердить", и ,если всё сделано верно, то появится надпись об успешном подтверждении.
После этого отправляем в сообщения группы письмо с ключевым словом и, если в ответ пришла картинка - значит всё работает правильно.  
  
Кнопки
------------
Для более удобного использования бот отправляет пользователю кнопки.  
Подробнее о кнопках можно прочесть здесь:  
[https://game-tips.ru/it/kak-sdelat-knopki-byistryih-otvetov-keyboard-dlya-botov-v-vk/](https://game-tips.ru/it/kak-sdelat-knopki-byistryih-otvetov-keyboard-dlya-botov-v-vk/)  
```php
...
const BTN_IMG =  [["action" => 'send_img'], "лоли", "blue"]; 
const BTN_GIF =  [["action" => 'send_gif'], "гиф", "blue"]; 
const BTN_ANIME = [["action" => 'send_gif'], "аниме", "blue"]; 
const BTN_ADMIN = [["action" => 'letter_to_admin'], "Связаться с админом", "white"]; 
...
```

Demo:
 https://vk.me/notice__me__senpai
