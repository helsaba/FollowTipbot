<?php
/*
        Author:  @PeaceLoveDoge
        Website:  http://twitter.com/PeaceLoveDoge
        This is a simple bot that will get up to 5,000 of the twitter users' followers, and
        use @ mention the followers in a tweet to @tipdoge account to tip the follower
        This little script was initially written to be run only by a chron job, but it would
        be great to be run interactively by the user through a web.
        I've put "TODO's" throughout for future programmers to extend the functionality.
        This uses the twitteroauth libray.  #https://github.com/abraham/twitteroauth
        Future Features:
        1.  Create a website ex.  FollowTipbot.com - (I have @FollowTipBot and can give you access)
                -       twitter oauth connect
                -       no wallets required - using @tipdoge, @tipreddit, etc.
                -       Display Stats
                -               Number of followers tipped
                -               Total amount tipped
        2.  Search for @ mentions, retweets and tweets with "RT" of user's tweet and tip them too.
        3.      Periodically get the balance and store balance in the database.
 
*/
 
#       
#       TODO:  actually have a config file
//      require_once("./include/config.php");  
 
# TODO:  download the twitteroauth library and put correct path
require_once("./include/library/twitteroauth/twitteroauth.php");       
 
//      TODO:  Put all of the following in a config file.
 
/* database parameters */
$db_host = 'localhost';
$db_user = 'db_user';
$db_pw = 'pwd';
$db = 'db';
 
 
// connect to mySQL database
mysql_connect($db_host, $db_user, $db_pw) or die('Could not connect to database');
mysql_select_db($db) or die('Could not select database');
 
 
#       TODO:  Get this from config file
$live = 1;              //  execute the commands
//$live = 0;    //  just testing.
 
//      TODO:  make $tip_again option entered by the user from the website interface.
//      $tip_again=1;   // tip previously tipped followers
$tip_again=0;   //  do not tip previously tipped followers
 
//      SETUP
//      TODO:  Need Database structure.
 
//      TODO:  Should be able to support other tipbots.
$tipbot = array ('screen_name' => '@tipdoge', 'currency' => 'doge');
$tip_type = "dogecoin";
 
//  #####    MASTER TOKENS   ########
//  TODO:  Get the uid from the logged in user.  Spoofing for CLI.
$uid=1;
 
//      TODO:  get the user's access tokey/secret from the database when user
//      first connected their twitter account.
$access_token = 'USER ACCESS TOKEN';
$access_token_secret = 'USER ACCESS TOKEN SECRET';
 
 
//  #####    MASTER KEYS     ########
//  TODO:  Get this from the database
$consumer_key = 'APP CONSUMER KEY';
$consumer_secret = 'APP CONSUMER SECRET';
 
//  END CONFIG FILE
 
 
$tweetie = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);
 
// Send an API request to verify credentials
$credentials = $tweetie->get("account/verify_credentials");
 
echo "Connected as @" . $credentials->screen_name . "\n";
 
$balance=coin_balance($tipbot[currency]);
if ($balance<=0) {
        echo "You have 0 balance\n";
        exit;
}else{
        echo "Your current balance:  $balance\n";
}
 
//      TODO:  Tip twitter accounts that you follow.  :)
$followers=$tweetie->get('followers/ids', array('screen_name' => $credentials->screen_name));
//print_r($followers);
 
//      TODO:  For when the user has over 5,000 followers returned, track the last next_cursor value and
//      store it in the db and start from there.
//  $followers=$tweetie->get('followers/ids', array('screen_name' => $credentials->screen_name, 'cursor'=> $followers->next_cursor));
//print_r($followers);
 
$list = array ();
 
foreach ($followers->ids as $fid) {
        #       TODO:  Make this more efficient
        if ($tip_again) {
        }
        #       TODO:  option to select a follower(s) at random to randomly tip.
 
        $sql = "SELECT * FROM tip_".$uid."_followers where uid=$credentials->id and fid=$fid LIMIT 1";
       
        $result = mysql_query($sql);
        $numrows = mysql_num_rows($result);
 
        if (($numrows!=0) and (!$tip_again)){
                //      echo "found record fid=$fid\n";
                //      print_r($row);
 
                //      TODO:  check if they've been tipped and how much.
                //      $row = mysql_fetch_row($result);
                //      print_r($row);
        }else{
                #       Case to tip
                //      echo "NOT found record fid=$fid\n";
 
                #       Figure out which list to add to, up to 100 per list as per twitter's limit
                $count++;
 
                $list_num = intval ($count / 100);
 
                if (!strlen($list[$list_num])) { $list[$list_num] = "$fid"; }
                else { $list[$list_num] .= ",$fid"; }
        }
}
 
 
foreach ($list as $userids) {
        $results=$tweetie->get('users/lookup', array('user_id' => $userids));
 
        foreach ($results as $tweep) {
 
                if ($balance) {
                        // Post our tip to our tipbot
                        $tipbot = array ('screen_name' => '@tipdoge', 'currency' => 'doge');
                        $tip_amount=get_tip_amount();
                        $msg = get_donor_msg();
                        $tip = $tipbot[screen_name]." tip @".$tweep->screen_name." ".$tip_amount. " ".$tipbot[currency]." ".$msg;
 
                        //      Let's tip this puppy!
                        if ($live) {
                                //  TODO:  abstract the tipping into an overloaded function
                                $tweetie->post('statuses/update', array('status' => $tip));
 
                                #       TODO:  add the amount to the total tip amount, and track number of tips per person.
                                //      NOTE:  confirmed will have to be checked later when we get the notification from the tipbot
 
                                $sql = "INSERT INTO  tip_".$uid."_followers set uid=$credentials->id, tip_type=$tip_type, fid=$tweep->id, screen_name=$tweep->screen_name, tipped=1, amount=$tip_amount";
                       
                                mysql_query($sql);
                        }
                        $balance-=$tip_amount;
                }else{
                        //  TODO:       exit ??
                        echo "You're out of coins!\n";
                }
        }
}
 
#       TODO:  Fill this functions
#       TODO:  Make random amounts settings
function get_tip_amount(){
        return 10;
}
 
#       TODO:  Figure out how much coins left
#       Spoofing for now.
function coin_balance() {
        return 1000;
}
 
/*  More TODO:
        Automatically sign up for the bot by syntax:
        @FollowTipbot <command> <tipbot> <amount> <currency>
        @FollowTipbot <add_msg> <msg>           #  Add to the random messages that gets sent out after the tipping syntax
*/
 
function get_donor_msg () {
        global $tipbot;
        global $donor;
 
#       TODO:  Pick a random message that the user inputs and stored in the database
        $msg = "Doge tips for all my new followers! #TipItForward #AllUNeedIsDoge";
        return $msg;
}
