<?php
/**
 * Example combined event handler bot.
 *
 * Copyright 2016-2020 Daniil Gentili
 * (https://daniil.it)
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2023 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

use danog\MadelineProto\API;
use danog\MadelineProto\EventHandler;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use function Amp\async;
use function Amp\delay;
/*
 * Various ways to load MadelineProto
 */
if (file_exists('vendor/autoload.php')) {
    include 'vendor/autoload.php';
} else {
    if (!file_exists('madeline.php')) {
        copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
    }
    include 'madeline.php';
}

/**
 * Event handler class.
 */
class MyEventHandler extends EventHandler
{
    /**
     * @var int|string Username or ID of bot admin
     */
    public $array_bans = [];
    public $messages_id = [];
    public $future = [];
    const ADMIN = "looool0307"; // Change this
    /**
     * Get peer(s) where to report errors.
     *
     * @return int|string|array
     */
    public function getReportPeers()
    {
        return [self::ADMIN];
    }
    /**
     * Handle updates from supergroups and channels.
     *
     * @param array $update Update
     */
    public function onUpdateNewChannelMessage(array $update)
    {
        return $this->onUpdateNewMessage($update);
    }
    /**
     * Handle updates from users.
     *
     * @param array $update Update
     */
    public function onUpdateNewMessage(array $update): void
    {
        global $MadelineProtos,$array_bans,$messages_id;
        if ($update['message']['_'] === 'messageEmpty' || $update['message']['out'] ?? false) {
            return;
        }
        elseif (isset($update['message']['action']['_']) and $update['message']['action']['_']=='messageActionChatAddUser') {
            $num_chars=6;//number of characters for captcha image
            $characters=array_merge(range(0,9),range('A','Z'),range('a','z'));//creating combination of numbers & alphabets
            shuffle($characters);//shuffling the characters
            $captcha_text="";
            for($i=0;$i<$num_chars;$i++)
            {
                $captcha_text.=$characters[rand(0,count($characters)-1)];
            }
            $sentMessage = $MadelineProtos[0]->messages->sendMedia([
                'peer' => $this->getId($update['message']['peer_id']),
                'media' => [
                    '_' => 'inputMediaUploadedPhoto',
                    'file' => 'http://localhost/bogdan/antispam1/captcha.php?captcha='.$captcha_text
                ],
                'message' => 'input text you have 60 seconds',
                'parse_mode' => 'Markdown'
            ]);
            $ban_user = array();
            $ban_user['id'] = $update['message']['peer_id'];
            $ban_user['user_id'] = $update['message']['from_id'];
            $ban_user['text'] = $captcha_text;
            $ban_user['time'] = time();
            $array_bans[$update['message']['from_id']['user_id']] = $ban_user;
            $msg_gen['time'] = time();
            $msg_gen['channel'] = $sentMessage['updates'][1]['message']['peer_id'];
            $msg_gen['id'] = $sentMessage['updates'][0]['id'];
            $msg_gen['text'] = $captcha_text;
            $messages_id[] = $msg_gen;
            $future = async(function () {
                delay(60);
                global $MadelineProtos,$array_bans,$messages_id;
                foreach ($array_bans as $ban) {
                    $chatBannedRights = ['_' => 'chatBannedRights', 'view_messages' => true, 'send_messages' => true, 'send_media' => true, 'send_stickers' => true, 'send_gifs' => true, 'send_games' => true, 'send_inline' => true, 'embed_links' => true, 'send_polls' => true, 'change_info' => true, 'invite_users' => true, 'pin_messages' => true, 'manage_topics' => true, 'send_photos' => true, 'send_videos' => true, 'send_roundvideos' => true, 'send_audios' => true, 'send_voices' => true, 'send_docs' => true, 'send_plain' => true, 'until_date' => 10];
                    $ban_user = $MadelineProtos[0]->channels->editBanned(channel: $this->getId($ban['id']), participant: $ban['user_id'], banned_rights: $chatBannedRights); 
                    $this->logger($ban_user);
                    
                }
                foreach ($messages_id as $msg_gen) {
                    if ((time()-$msg_gen['time'])>=55) {
                        $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($msg_gen['channel']), id: [$msg_gen['id']], );
                    }
                }
            });
        }
        elseif (isset($update['message']['from_id']['user_id'])) {
            if (isset($array_bans[$update['message']['from_id']['user_id']]) and isset($update['message']['message']) and $array_bans[$update['message']['from_id']['user_id']]['text']==$update['message']['message']) {
                $this->logger($update);
                unset($array_bans[$update['message']['from_id']['user_id']]);
                foreach ($messages_id as $msg_gen) {
                    if ($msg_gen['text']==$update['message']['message']) {
                        $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($msg_gen['channel']), id: [$msg_gen['id']], );
                    }
                }
                $mute = async(function () use ($update) {
                    delay(5);
                    global $MadelineProtos;
                    $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($update['message']['peer_id']), id: [$update['message']['id']], );
                });
            }
        }
        if (isset($update['message']['message']) and $update['message']['message'] == '/ban') {
            $mute = async(function () use ($update) {
                delay(5);
                global $MadelineProtos;
                $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($update['message']['peer_id']), id: [$update['message']['id']], );
            });
            if (isset($update['message']['reply_to'])) {
                $messages_Messages = $MadelineProtos[0]->channels->getMessages(['channel'=>$this->getId($update['message']['peer_id']), 'id'=>[$update['message']['reply_to']['reply_to_msg_id']]]);

                $chatBannedRights = ['_' => 'chatBannedRights', 'view_messages' => true, 'send_messages' => true, 'send_media' => true, 'send_stickers' => true, 'send_gifs' => true, 'send_games' => true, 'send_inline' => true, 'embed_links' => true, 'send_polls' => true, 'change_info' => true, 'invite_users' => true, 'pin_messages' => true, 'manage_topics' => true, 'send_photos' => true, 'send_videos' => true, 'send_roundvideos' => true, 'send_audios' => true, 'send_voices' => true, 'send_docs' => true, 'send_plain' => true, 'until_date' => 10];

               $ban_user = $MadelineProtos[0]->channels->editBanned(channel: $this->getId($messages_Messages['messages'][0]['peer_id']), participant: $messages_Messages['messages'][0]['from_id']['user_id'], banned_rights: $chatBannedRights); 
               $this->logger($ban_user);

            }
        }
        if (isset($update['message']['message']) and strpos($update['message']['message'], '/mute')===0) {
            $timed= explode(' ', $update['message']['message']);
            $type = 'h';
            $time_count = $timed[1];
            $mute = async(function () use ($update) {
                delay(5);
                global $MadelineProtos;
                $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($update['message']['peer_id']), id: [$update['message']['id']], );
            });
            if (count($timed)>2) {
                $type = $timed[2];
            }
            switch ($type) {
                case 's':
                        if ($time_count<30) {
                            $time_count = 30;
                        }
                    break;
                case 'h':
                        if ($time_count>148) {
                            $time_count = 148;
                        }
                        $time_count = $time_count*60*60;
                    break;
                case 'd':
                        if ($time_count>7) {
                            $time_count = 7;
                        }
                            $time_count = $time_count*24*60*60;
                    break;
            }
            $time_count = time()+$time_count;
            if (isset($update['message']['reply_to'])) {
                $messages_Messages = $MadelineProtos[0]->channels->getMessages(['channel'=>$this->getId($update['message']['peer_id']), 'id'=>[$update['message']['reply_to']['reply_to_msg_id']]]);

                $chatBannedRights = ['_' => 'chatBannedRights', 'send_messages' => true, 'until_date' => $time_count];

               $ban_user = $MadelineProtos[0]->channels->editBanned(channel: $this->getId($messages_Messages['messages'][0]['peer_id']), participant: $messages_Messages['messages'][0]['from_id']['user_id'], banned_rights: $chatBannedRights); 
               $this->logger($time_count);

            }
        }
        if (isset($update['message']['message']) and strpos($update['message']['message'], '/unmute')===0) {
            $mute = async(function () use ($update) {
                delay(5);
                global $MadelineProtos;
                $MadelineProtos[0]->channels->deleteMessages(channel: $this->getId($update['message']['peer_id']), id: [$update['message']['id']], );
            });
            if (isset($update['message']['reply_to'])) {
                $messages_Messages = $MadelineProtos[0]->channels->getMessages(['channel'=>$this->getId($update['message']['peer_id']), 'id'=>[$update['message']['reply_to']['reply_to_msg_id']]]);

                $chatBannedRights = ['_' => 'chatBannedRights', 'send_messages' => false, 'until_date' => 0];

               $ban_user = $MadelineProtos[0]->channels->editBanned(channel: $this->getId($messages_Messages['messages'][0]['peer_id']), participant: $messages_Messages['messages'][0]['from_id']['user_id'], banned_rights: $chatBannedRights); 

            }
        }
        //$this->logger($update);
    } 
}
 $MadelineProtos = [];
foreach (['bot.madeline'] as $session => $message) {
    $MadelineProtos []= new API($session);
}
API::startAndLoopMulti($MadelineProtos, MyEventHandler::class);