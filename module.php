<?php

/**
 * File which holds all logic of the voting system
 * @see /vote/assets/vote.js for the ajax javascript
 * @package vote
 */

/**
 * set assets of voting system
 * @ignore
 */
template::setInlineCss(config::getModulePath('vote') . '/assets/vote.css');
template::setInlineJs(config::getModulePath('vote') . '/assets/vote.js');

/**
 * Class vote for using vote. Easy to implemet as a submodule
 * in other modules. For an example se the module comment
 * @package vote
 */
class vote {
    
    /**
     * display buttons for voting. 
     * @param type $id the reference id
     * @param type $reference the reference
     * @param type $count the current vote count
     * @return type 
     */
    public static function buttons ($parent_id, $reference, $count) {
        //$login = '';
        //echo 
        if (!session::isUser()) {
             $return_to = rawurlencode($_SERVER['REQUEST_URI']);
             $return_to.= rawurlencode("#vote-$reference-$parent_id");
             $return_to = "/account/index?return_to=$return_to";
            
            if (isset($_POST['vote_redirect'])) {    
                http::locationHeader($return_to, lang::translate('Please login in order to make a vote'));
            }
            
            //$link = html::createLink(
            //        "/account/index?return_to=$return_to", 
            //        lang::translate('Login'));
            //$login = lang::translate('<span class="notranslate">{LOGIN_LINK}</span> to vote ', array ('LOGIN_LINK' => $link));
            //$login.=$parent_id; 
            $extra =  array (
                //'disabled' => 'disabled',
                'title' => lang::translate('In order to vote you need to log in. Press vote button and you will go to the log in page. After log in you will return here.'));
            $extra = html::parseExtra($extra);
            
            $str = <<<EOT
<form method="post" action="#!">
<input type="hidden" name ="vote_redirect" value="1"
<a id = "vote-$reference-$parent_id"></a>
<button type="submit" value="" class="vote_up_dummy" $extra></button>
<button type="submit" value="" class="vote_down_dummy" $extra></button>
<span id="$parent_id-$reference" class = "vote_response">
        $count
</span> 
</form>
EOT;
        } else {
            $extra = '';
            
            $str = <<<EOT
<a id="vote-$reference-$parent_id"></a>
<button type="submit" value="$parent_id-$reference" class="vote_up" $extra></button>
<button type="submit" value="$parent_id-$reference" class="vote_down" $extra></button>
<span id="$parent_id-$reference" class = "vote_response">$count</span>    
EOT;
        }

        return $str;
    }
    
    /**
     * get html for buttons as an event
     * @param array $args array ('reference' => 'reference', 'parent_id' => parent_id')
     * @return string $str html buttons for voting 
     */
    
    public static function getButtons ($args) {
        $db = new db();
        $search = array ('reference' => $args['reference'], 'parent_id' => $args['parent_id']);
        $row = $db->selectOne('vote', null, $search);
        
        if (empty($row)) { 
            $args['vote'] = 0;
        } else {
            $args['vote'] = $row['vote'];
        }
        return self::buttons($args['parent_id'], $args['reference'], $args['vote']);
    }
    
   
    /**
     * method for enabling the vote system as a submodule. 
     * @param array $options array ('parent_id' => 'parent_id', 'reference' => 'reference');
     * @return string $str html voting buttons
     */
    public static function subModulePreContent ($options) {
        $db = new db();
        $search = array ('reference' => $options['reference'], 'parent_id' => $options['parent_id']);
        $row = $db->selectOne('vote', null, $search);
        
        if (empty($row)) { 
            $count = 0;
        } else {
            $count = $row['vote'];
        }       
        return self::buttons($options['parent_id'], $options['reference'], $count);
    }
    
    public static function events ($args) {

        if ($args['action'] == 'view') {
             return self::getButtons($args);
        }
        if ($args['action'] == 'get') {
            return self::getButtons($args);
        }
        return true;
        
    }

    /**
     * method for handling ajax votes
     * se /vote/up.php and /vote/down.php
     * @param string $direction up or down 
     */
    public static function ajaxVote($direction = 'up') {

        $id=$_POST['id'];
        $ary = explode('-', $id);
        $reference = $ary[1];
        
        // Could make better check, but ok for now. 
        $final_res = false;
        if (isset($id, $reference)) {
            $db = new db();
            db::$dbh->beginTransaction();

            // check if user has voted
            $search = array ('reference' => $reference, 'parent_id' => $id);
            $row = $db->selectOne('vote', null, $search);

            // no row with this reference and this parent_id
            // create row
            if (empty($row)) {

                $row['parent_id'] = (int)$id;
                $row['reference'] = (string)$reference;
                $row['vote'] = 0;
                $res = $db->insert('vote', $row);
                $row['id'] = db::$dbh->lastInsertId();
            }
                
            if (!session::getUserId()) {
                //$link = html::createLink("/account/index", lang::translate('Login'));
                //echo $row['vote'] . ' ';
                //echo lang::translate('<span class="notranslate">{LOGIN_LINK}</span> to vote ', array ('LOGIN_LINK' => $link));
                die();
            } 

            $search = array (
                 'user_id' => session::getUserId(),
                 'vote' => $row['id']
            );

            $vote_user = $db->selectOne('vote_user', null, $search);
            if (empty($vote_user)) {
                       
                // insert
                $res = $db->insert('vote_user', $search);

                // update vote table
                $vote = array();
                if ($direction == 'up') {
                    $vote['vote'] = $row['vote'] +1;
                } else {
                    $vote['vote'] = $row['vote'] -1;
                }

                // update reference table
                $res = $db->update('vote', $vote, $row['id']);
                if ($db->fieldExists($row['reference'], 'vote')) {
                    $res = $db->update(
                            $row['reference'], 
                            $vote, 
                            array('id' => $row['parent_id']));
                }
                                
                if ($res) {
                    echo $vote['vote'];
                }
            // has voted - show table value. 
            } else {
                echo $row['vote'] . ' ' . lang::translate('vote_user_has_voted');
            }
        }

        // commit or rollback
        $res = db::$dbh->commit();
        if (!$res) {
            db::$dbh->rollback();
        } 
        die;
    }
    
    /**
     * method for initing a vote field in table with a 0 value
     */
    public static function init ($reference, $parent_id) {
        $db = new db();
        $values = array (
            'reference' => $reference, 
            'parent_id' => $parent_id, 
            'vote' => '0');
        $row = $db->insert('vote', $values);
    }
}
