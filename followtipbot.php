<?php
/*
	Author:  @PeaceLoveDoge
	Website:  http:// twitter.com/PeaceLoveDoge
	This is a simple bot that will get up to 5,000 of the twitter users' followers, and
	use @ mention the followers in a tweet to @tipdoge account to tip the follower
	This little script was initially written to be run only by a chron job, but it would
	be great to be run interactively by the user through a web.
	I've put "TODO's" throughout for future programmers to extend the functionality.
	This uses the twitteroauth libray.  // https:// github.com/abraham/twitteroauth
	Future Features:
	1.  Create a website ex.  FollowTipbot.com - (I have @FollowTipBot and can give you access)
		-       twitter oauth connect
		-       no wallets required - using @tipdoge, @tipreddit, etc.
		-       Display Stats
		-	       Number of followers tipped
		-	       Total amount tipped
	2.  Search for @ mentions, retweets and tweets with "RT" of user's tweet and tip them too.
	3.      Periodically get the balance and store balance in the database.

*/

require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Noodlehaus\Config;
use TwitterOAuth\OAuth;
use TwitterOAuth\Api;

$log = new Logger('FollowTipbot');
$log->pushHandler(new StreamHandler(__DIR__.'/app.log', Logger::INFO));

$cfg = Config::load('app.json');
$twt_cfg = Config::load('twitter-uranther.json');

$debug = $cfg->get('debug');

// TODO: get balance from tipbot with '@tipdoge balance'
// TODO: calculate tip_amount by dividing balance by number of followers
$tip_cfg = Config::load('tipdoge-uranther.json');

/*** connect to SQLite database ***/
try {
	$dbh = new PDO("sqlite:".__DIR__."/tips.sdb");
	$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
	die($e->getMessage());
}
$schema = file_get_contents('./schema.sql');
$dbh->exec($schema); // create database if not exists (SQLite 3.3+)


// // // // // // MASTER TOKENS   // // // // // // // // 
// TODO:  Get the uid from the logged in user.  Spoofing for CLI.
//$uid = 1;

// TODO:  get the user's access tokey/secret from the database when user
// first connected their twitter account.
//$access_token = 'USER ACCESS TOKEN';
//$access_token_secret = 'USER ACCESS TOKEN SECRET';

// // // // // // MASTER KEYS     // // // // // // // // 
// TODO:  Get this from the database
//$consumer_key = 'APP CONSUMER KEY';
//$consumer_secret = 'APP CONSUMER SECRET';

// Connect to Twitter
$tweetie = new Api(
				$twt_cfg->get('consumer_key'),
				$twt_cfg->get('consumer_secret'),
				$twt_cfg->get('access_token'),
				$twt_cfg->get('access_token_secret')
			);
// Verify creds
$credentials = $tweetie->get("account/verify_credentials");
echo "Connected as @" . $credentials->screen_name . "\n";

$balance = coin_balance($cfg->get('tipbot.currency'));
if ($balance <= 0) {
	echo "ZERO BALANCE\n";
	echo "QUITTING...\n";
	exit;
}
echo "Balance:  $balance\n";

// TODO:  Tip twitter accounts that you follow.  :)
$followers = $tweetie->get(
	'followers/ids',
	array('screen_name' => $credentials->screen_name)
);
if ($debug) print_r($followers);

if (is_array($followers->ids)) {
	$num_followers = count($followers->ids);
} else {
	$log->addError('could not get followers!');
	$log->addError('EXITING!');
	die;
}

//echo "Wow. Much follow: " . $num_followers . " tweeps\n";

// TODO:  For when the user has over 5,000 followers returned, track the last next_cursor value and
// store it in the db and start from there.
// $followers=$tweetie->get('followers/ids', array('screen_name' => $credentials->screen_name, 'cursor'=> $followers->next_cursor));
// print_r($followers);

$follower_list = array ();
$count = 0;
$tip_again = $cfg->get('tip_again');

foreach ($followers->ids as $fid) {
	if (!$fid) break;

	// TODO:  option to select a follower(s) at random to randomly tip.
	if ($cfg->get('random')) {
		$log->addInfo('Random tipping enabled.');
	}

	// Find all the tips for this user and follower
	try {
		$sql = "SELECT COUNT(*) as c FROM tip_followers WHERE uid = ? AND fid = ?";
		$sth = $dbh->prepare($sql);
		$sth->execute(array($credentials->id, $fid));
		$tipped_count = $sth->fetch();
		$tipped_count = (int) $tipped_count['c'];
		if ($debug) $log->addDebug($results);
	} catch (PDOException $e) {
		$log->addError($e);
		die($e);
	}
	
	// Tip those followers again?
	if ($tip_again || (!$tip_again && $tipped_count == 0)) { // only tip if no previous tips

		// Figure out which list to add to, up to 100 per list as per twitter's limit
		$count++;

		$list_num = intval ($count / 100);

		if (isset($follower_list[$list_num])) {
			$follower_list[$list_num] .= ",$fid"; // append follower ID
		} else {
			$follower_list[$list_num] = "$fid";
		}
	}
}

echo "Tipping " . $count ." of ". $num_followers ." tweeps.\n";

$tip_amount = get_tip_amount($count);
echo "Tip amount: " . $tip_amount ." ". $cfg->get('tipbot.currency') ."\n";

foreach ($follower_list as $user_ids) {
	$results = $tweetie->get('users/lookup', array('user_id' => $user_ids));

	$log->addInfo('Balance (before): '.$balance);

	foreach ($results as $tweep) {

		if ($balance) {
			// Post our tip to our tipbot
			$msg = get_donor_msg();
			$tip = sprintf("%s tip @%s %s %s %s",
				$cfg->get('tipbot.screen_name')
				, $tweep->screen_name
				, $tip_amount
				, $cfg->get('tipbot.currency')
				, $msg
			);

			// Let's tip this puppy!
			if ($cfg->get('live')) {
				// TODO:  abstract the tipping into an overloaded function
				$tweetie->post('statuses/update', array('status' => $tip));
				sleep($cfg->get('delay')); // sleep for 2 minutos

				// TODO:  add the amount to the total tip amount, and track number of tips per person.
				// NOTE:  confirmed will have to be checked later when we get the notification from the tipbot

				try {
					$sql = "INSERT INTO  tip_followers
						(uid, fid, tip_type, screen_name, tipped, amount)
						VALUES 
						(?, ?, ?, ?, 1, ?)";
					$sth = $dbh->prepare($sql);
					$sth->execute(array(
						$credentials->id,
						$tweep->id,
						$cfg->get('tipbot.tip_type'),
						$tweep->screen_name,
						$tip_amount
					));
				} catch (PDOException $e) {
					$log->addError($e);
					die($e);
				}
			}
			$balance = $balance - $tip_amount;

			$log->addInfo($tip);
			if ($debug) echo $tip."\n";
		} else {
			$log->addError("You're out of coins!");
			die("I'M BROKE\n");
		}
	}

	$log->addInfo('Balance (after): '.$balance);
	echo "Balance: $balance\n";
}

// TODO:  Fill this functions
// TODO:  Make random amounts settings
function get_tip_amount($num_followers) {
	global $tip_cfg, $log;
	$amt = 0;

	if ($tip_cfg->get('distribution.even')) {
		echo "Distributing all DOGE to followers evenly\n";
		$log->addInfo('Distributing all DOGE to followers evenly');
		$amt = $tip_cfg->get('balance') / $num_followers;
	}

	if ($tip_cfg->get('distribution.rounded')) {
		echo "Rounding tip amount\n";
		$log->addInfo('Rounding tip amount');
		$amt = floor($amt);
	}

	return $amt;
}

// TODO:  Figure out how much coins left
// Spoofing for now.
function coin_balance() {
	global $tip_cfg;
	return $tip_cfg->get('balance');
}

function get_donor_msg () {
	$msg_cfg = Config::load('messages.json');

	// TODO:  Pick a random message that the user inputs and stored in the database
	$messages = $msg_cfg->get('messages');

	return $messages[0];
}

/*  More TODO:
	Automatically sign up for the bot by syntax:
	@FollowTipbot <command> <tipbot> <amount> <currency>
	@FollowTipbot <add_msg> <msg>	   // Add to the random messages that gets sent out after the tipping syntax
*/
