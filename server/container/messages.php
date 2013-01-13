<?php

include_once 'container/channels.php';
include_once 'container/users.php';

class Container_messages {
  
  /**
   * cid : recipient
   * uid : sender
   * msg : message to send
   */
  static public function postMsgToChannel($cid, $uid, $body, $type = 'msg') {

    $mid = self::generateMid($cid);
    $msg = array(
      'id'        => $mid,
      'sender'    => $uid,
      'recipient' => 'channel|'.$cid,
      'type'      => $type,
      'body'      => $body,
      'timestamp' => time(),
    );

    // when a join message is sent, body contains user's data
    if ($type == 'join') {
      $msg['body'] = Container_users::getUserData($uid);
    }

    // json encode msg before storing
    $msg = json_encode($msg);

    // search users subscribed to the channel
    foreach (Container_channels::getChannelUsers($cid) as $subuid) {
      // post this message on each users subscribed on the channel
      // /users/:uid/msg/
      if ($subuid != $uid) { // don't post message to the sender
        $umdir = Container_users::getDir().'/'.$subuid.'/messages';
        file_put_contents($umdir.'/'.$mid, $msg);
      }
    }
    
    return $msg;
  }
  
  /**
   * Generates a unique message id
   */
  static public function generateMid($cid) {
    $mid = sha1(uniqid('', true));
    return $mid;
  }

}

