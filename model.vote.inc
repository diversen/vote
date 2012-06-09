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
        $str = <<<EOT
<button type="submit" value="$parent_id-$reference" class="up"></button>
<button type="submit" value="$parent_id-$reference" class="down"></button>
<span id="$parent_id-$reference" class = "vote_response">
        $count
</span>    
EOT;
        return $str;
    }
    
    /**
     * get html for buttons as an event
     * @param array $args array ('reference' => 'reference', 'parent_id' => parent_id')
     * @return string $str html buttons for voting 
     */
    
    public static function getButtons ($args) {
        //print_r($args); die;
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
    
    function events ($args) {
        if ($args['action'] == 'view') {
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
        $parent_id = $ary[0];
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
                if (!$res) { 
                    cos_error_log("Could not use vote with values" . var_export ($values));        
                    echo "Something went wrong - your vote was not accepted";
                    die;
                }

                $row['id'] = db::$dbh->lastInsertId();
            }

            // we have a vote
            // check if user has voted
            $search = array (
                'ip' => $_SERVER['REMOTE_ADDR'],
                'vote' => $row['id']);

            $vote_user = $db->selectOne('vote_user', null, $search);
            
            //$vote_user = '';
            // user has not voted
            if (empty($vote_user)) {
                
                
                // insert
                $res = $db->insert('vote_user', $search);
                if (!$res) {
                    cos_error_log("Could not insert into vote_user: " . var_export ($vote_user));
                    echo "Could not register your vote";
                    die;
                }

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
                    $res = $db->update($row['reference'], $vote, array('id' => $row['parent_id']));
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
            cos_error_log('Could not commit');
        } 
        die;
    }
    
    /**
     * method for initing a vote field in table with a 0 value
     */
    public static function init ($reference, $parent_id) {
        $db = new db();
        $values = array ('reference' => $reference, 'parent_id' => $parent_id, 'vote' => '0');
        $row = $db->insert('vote', $values);
    }
}
