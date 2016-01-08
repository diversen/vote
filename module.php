<?php

namespace modules\vote;

use diversen\conf;
use diversen\db;
use diversen\html;
use diversen\lang;
use diversen\session;
use diversen\template;
use diversen\template\assets;

assets::setInlineCss(conf::getModulePath('vote') . '/assets/vote.css');
assets::setInlineJs(conf::getModulePath('vote') . '/assets/vote.js');

/**
 * Class vote for using vote. Easy to implemet as a submodule
 * in other modules. For an example se the module comment
 * @package vote
 */
class module {
    
    /**
     * display buttons for voting. 
     * @param type $id the reference id
     * @param type $reference the reference
     * @param type $count the current vote count
     * @return type 
     */
    public static function buttons ($parent_id, $reference, $count) {
        if (!session::isUser()) {
             $return_to = rawurlencode($_SERVER['REQUEST_URI']);
             $return_to.= rawurlencode("#vote-$reference-$parent_id");
             $return_to = "/account/index?return_to=$return_to";
            
            if (isset($_POST['vote_redirect'])) {    
                session::loginThenRedirect(lang::translate('Please login in order to make a vote'));
            }

            $extra =  array (
                'title' => lang::translate('In order to vote you need to log in. Press vote button and you will go to the log in page. After log in you will return here.'));
            $extra = html::parseExtra($extra);
            
            $str = <<<EOT
<form method="post" action="#!">
<input type="hidden" name ="vote_redirect" value="1"
<a id = "vote-$reference-$parent_id"></a>
<button type="submit" value="" class="vote_up_dummy" $extra><i class="fa fa-thumbs-up"></i></button>
<button type="submit" value="" class="vote_down_dummy" $extra><i class="fa fa-thumbs-down"></i></button>
<span id="$parent_id-$reference" class = "vote_response">
        $count
</span> 
</form>
EOT;
        } else {
            $extra = '';
            
            $str = <<<EOT
<a id="vote-$reference-$parent_id"></a>
<button type="submit" value="$parent_id-$reference" class="vote_up" $extra><i class="fa fa-thumbs-up"></i></button>
<button type="submit" value="$parent_id-$reference" class="vote_down" $extra><i class="fa fa-thumbs-down"></i></button>
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
     * A wrapper for fetching the vote HTML
     * @param array $args 
     * @return void
     */
    public static function events ($args) {
        if ($args['action'] == 'view') {
             return self::getButtons($args);
        }
        if ($args['action'] == 'get') {
            return self::getButtons($args);
        }        
    }
    
    /**
     * /vote/up action
     * @return void
     */
    public function upAction() {
        if (!session::isUser()) {
            return;
        }
        $this->ajaxVote('up');
    }

    /**
     * /vote/down action
     * @return void
     */
    public function downAction() {
        if (!session::isUser()) {
            return;
        }
        $this->ajaxVote('down');
    }
    
    /**
     * get row base  on parent_id and reference
     * @param int $id
     * @param string $reference
     * @return array $row
     */
    public function getVoteRow($id, $reference) {
        $db = new db();
        
        // Check if there a vote corresponding to 
        // the reference and the parent_id
        $search = array('reference' => $reference, 'parent_id' => $id);
        $row = $db->selectOne('vote', null, $search);

        // No row. Create one
        if (empty($row)) {
            $row['parent_id'] = $id;
            $row['reference'] = $reference;
            $row['vote'] = 0;
            $db->insert('vote', $row);
            $row['id'] = db::$dbh->lastInsertId();
        }
        return $row;
    }

    /**
     * Method for handling ajax votes
     * Access controlled in upAction and downAction
     * @param string $direction up or down 
     */
    public function ajaxVote($direction = 'up') {
        
        $ary = explode('-', $_POST['id']);
        if (!isset($ary[0]) OR !isset($ary[1])) {
            log::error('Wrong ID given to wote system');
            return;
        }
        
        $reference = (string)$ary[1];
        $id = (int)$ary[0];

        if (isset($id, $reference)) {
            $db = new db();
            db::$dbh->beginTransaction();
            $row = $this->getVoteRow($id, $reference);
            
            $result = $this->doVote($direction, $row);
        }

        // commit or rollback
        $commit = db::$dbh->commit();
        if (!$commit) {
            db::$dbh->rollback();
            log::error("Vote: commit");
            echo lang::translate('Something went wrong! Try again later');
        } else {
            echo $result;
        }
        die;
    }
    
    /**
     * 
     * @param string $direction up or down
     * @param array $row vote row
     * @return string $str string with result to echo back to user
     */
    public function doVote($direction, $row) {
        
        // Check if user has voted
        $db = new db();
        $search = array(
            'user_id' => session::getUserId(),
            'vote' => $row['id']
        );

        $vote_user = $db->selectOne('vote_user', null, $search);
        if (empty($vote_user)) {

            // insert
            $db->insert('vote_user', $search);

            // update vote table
            $vote = array();
            if ($direction == 'up') {
                $vote['vote'] = $row['vote'] + 1;
            } else {
                $vote['vote'] = $row['vote'] - 1;
            }

            // update reference table
            $db->update('vote', $vote, $row['id']);
            if ($db->fieldExists($row['reference'], 'vote')) {
                $db->update(
                        $row['reference'], $vote, array('id' => $row['parent_id']));
            }

            return $vote['vote'];

            // has voted - show table value. 
        } else {
            return $row['vote'] . ' ' . lang::translate('You have voted!');
        }
    }
}
