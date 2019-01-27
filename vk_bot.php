<?php
include "vk_api.php";

// **********CONFIG**************
$config = json_decode(file_get_contents('config.json'));
$GROUP_TOKEN = $config->GROUP_TOKEN;
$CONFIRMATION_TOKEN = $config->CONFIRMATION_TOKEN;
$SECRET_KEY = $config->SECRET_KEY;
$ADMIN_ID = $config->ADMIN_ID;
$PICTURE_GROUPS = $config->PICTURE_GROUPS;
$GIF_GROUPS = $config->GIF_GROUPS;
$ANIME_GROUPS = $config->ANIME_GROUPS;
$STOP_WORDS = $config->STOP_WORDS;
$ACCESS_TOKEN = $config->ACCESS_TOKEN;
$V = $config->V;
// ******************************

const BTN_IMG = [["action" => 'send_img'], "лоли", "blue"];
const BTN_GIF = [["action" => 'send_gif'], "гиф", "blue"];
const BTN_ANIME = [["action" => 'send_gif'], "аниме", "blue"];
const BTN_ADMIN = [["action" => 'letter_to_admin'], "Связаться с админом", "white"];

if (!isset($_REQUEST)) {
    return;
}

$data = json_decode(file_get_contents('php://input'));

if (strcmp($data->secret, $SECRET_KEY) !== 0 && strcmp($data->type, 'confirmation') !== 0) {
    return;
}

$vk = new vk_api($GROUP_TOKEN, $V);

switch ($data->type) {
    case 'confirmation':
        exit($CONFIRMATION_TOKEN);

    case 'message_new':
        $peer_id = $data->object->from_id;
        $message = $data->object->text;
        $message = mb_strtolower($message);

        if (isset($data->object->payload)) {
            $payload = json_decode($data->object->payload, true);
        } else {
            $payload = null;
        }

        if ($message == 'начать' or $message == 'start') { //Если нажата кнопка начать
            $resp = $vk->sendButton(
                $peer_id,
              'Привет, рада тебя видеть 💖<br>' .
              'В нашей группе работает лоли-бот 😊<br>' .
              'лоли/loli - случайная картинка лолечки ❤<br>' .
              'гиф/gif - случайная аниме-гифка 🌈<br>' .
              'аниме/anime - и она посоветует аниме ✨',
              [[BTN_IMG, BTN_GIF, BTN_ANIME], [BTN_ADMIN]]
            );
            $vk->sendOK();
            break;
        } elseif (in_text($message, ['лоли', 'loli', 'тян', 'лолю', 'лоля'])) {
            $attachment = get_attachment('photo')['attachment'];
            $resp = $vk->request('messages.send', [
              'message' => 'Посмотри, что я нашла для тебя:',
              'peer_id' => $peer_id,
              'attachment' => $attachment,
            ]);
            $vk->sendOK();
            break;
        } elseif (in_text($message, ['гиф', 'gif'])) {
            $attachment = get_attachment('gif')['attachment'];
            $resp = $vk->request('messages.send', [
                'message' => 'Посмотри, что я нашла для тебя:',
                'peer_id' => $peer_id,
                'attachment' => $attachment,
              ]);
            $vk->sendOK();
            break;
        } elseif (in_text($message, ['аниме', 'anime', 'анимэ'])) {
            $res = get_attachment('anime');
            $attachment = $res['attachment'];
            $text = $res['text'];
            $resp = $vk->request('messages.send', [
              'message' => $text,
              'peer_id' => $peer_id,
              'attachment' => $attachment,
            ]);
            $vk->sendOK();
            break;
        } else {
            if ($payload != null && $payload['action'] == 'letter_to_admin') {
                $resp = $vk->request('messages.send', [
                  'message' => sprintf("С тобой хочет связаться @id%d", $peer_id),
                  'peer_id' => $ADMIN_ID,
                ]);
                $resp = $vk->request('messages.send', [
                  'message' => "Админу отправлено оповещение, скоро он с тобой свяжется)",
                  'peer_id' => $peer_id,
                ]);
                $vk->sendOK();
                break;
            } else {
                $resp = $vk->request('messages.send', [
                  'message' => "Я обязательно прочту твоё сообщение, как только смогу 😉<br>" .
                                "Не скучай, можешь пока полистать стену нашей группы)",
                  'peer_id' => $peer_id,
                ]);
                $vk->sendOK();
                break;
            }
        }

        // no break
    case 'group_leave':
        $peer_id = $data->object->user_id;
        $resp = $vk->request('messages.send', [
          'message' => "Жаль, что ты уже уходишь!<br>" .
                        "Если захочешь вернуться - здесь всегда тебе рады.<br>" .
                        "Удачи тебе ヽ(・∀・)ﾉ",
          'peer_id' => $peer_id,
        ]);
        $vk->sendOK();
        break;
}




function in_text($text, $words)
{
    foreach ($words as $word) {
        if (strpos($text, $word) !== false) {
            return true;
        }
    }
    return false;
}

function get_attachment($type)
{
    global $ACCESS_TOKEN, $V, $PICTURE_GROUPS, $GIF_GROUPS, $ANIME_GROUPS, $STOP_WORDS;
    $user = new vk_api($ACCESS_TOKEN, $V);
    while (true) {
        if ($type == 'photo') {
            $rand_elem = array_rand($PICTURE_GROUPS);
            $group_id = $PICTURE_GROUPS[$rand_elem];
        } elseif ($type == 'gif') {
            $rand_elem = array_rand($GIF_GROUPS);
            $group_id = $GIF_GROUPS[$rand_elem];
        } elseif ($type == 'anime') {
            $rand_elem = array_rand($ANIME_GROUPS);
            $group_id = $ANIME_GROUPS[$rand_elem];
        } else {
            return false;
        }
        $count = $user->request('wall.get', [
                    'owner_id' => $group_id
                  ])['count'];
        $post = $user->request('wall.get', [
                    'owner_id' => $group_id,
                    'offset' => random_int(2, $count - 1),
                    'count' => 1,
                    'filter' => 'owner'
                  ])['items'][0];
        $text = mb_strtolower($post['text']);
        if (in_text($text, $STOP_WORDS)) {
            continue;
        }
        if ($post['marked_as_ads']) {
            continue;
        }
        if (!isset($post['attachments'])) {
            continue;
        }
        $attachments = $post['attachments'];
        $res = '';
        foreach ($attachments as $attachment) {
            if ($type == 'photo') {
                if ($attachment['type'] == 'photo') {
                    $res = sprintf('photo%d_%d', $attachment['photo']['owner_id'], $attachment['photo']['id']);
                    break;
                }
            } elseif ($type == 'gif') {
                if ($attachment['type'] == 'doc') {
                    if ($attachment['doc']['ext'] == 'gif') {
                        $res = sprintf('doc%d_%d', $attachment['doc']['owner_id'], $attachment['doc']['id']);
                        break;
                    }
                }
            } elseif ($type == 'anime') {
                if ($attachment['type'] == 'doc') {
                    $res.= sprintf('doc%d_%d,', $attachment['doc']['owner_id'], $attachment['doc']['id']);
                }
                if ($attachment['type'] == 'photo') {
                    $text .= $attachment['photo']['text'];
                    $res.= sprintf('photo%d_%d,', $attachment['photo']['owner_id'], $attachment['photo']['id']);
                }
                if ($attachment['type'] == 'video') {
                    $res.= sprintf('video%d_%d,', $attachment['video']['owner_id'], $attachment['video']['id']);
                }
            }
        }
        if (!$res) {
            continue;
        }
        if ($type == 'photo') {
            $photo_info = $user->request('photos.getById', [
                      'photos' => str_replace('photo', '', $res)
                    ]);
            if (isset($photo_info['error'])) {
                continue;
            }
        } elseif ($type == 'gif') {
            $gif_info = $user->request('docs.getById', [
                      'docs' => str_replace('doc', '', $res)
                    ]);
            if (!$gif_info or isset($gif_info['error'])) {
                continue;
            }
        } elseif ($type == 'anime') {
            if (!in_text(mb_strtolower($text), ['приятного просмотра'])) {
                continue;
            }
        }
        break;
    }
    return ['attachment' => $res, 'text' => $text];
}
