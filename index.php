<?php
$config = include('config.php');
$bearer_token = $config['bearer_token'];

if ($_SERVER['QUERY_STRING'] != "" && is_numeric($_SERVER['QUERY_STRING'])) {
	$uid = $_SERVER['QUERY_STRING'];
	$userdata = get_twitter_userdata($uid, $bearer_token);
	$user_o = json_decode($userdata);
	$tweetURL = "https://twitter.com/" . $user_o->data->username;
	output_rss_header($user_o->data->name . " Tweets", $tweetURL, "@" . $user_o->data->username, $user_o->data->profile_image_url);
	$tweetURL = $tweetURL . "/status/";
	$tweetdata = get_twitter_tweetsforuser($uid, $bearer_token);
	$tweets_o = json_decode($tweetdata);
	$tweets = $tweets_o->data;
	if (isset($tweets_o->includes)) {
		$includes = $tweets_o->includes;
	}
	foreach ($tweets as $tweet) {
		$title = remove_urls($tweet->text);
		$description = parse_urls($tweet->text, $tweet->id);
		$link = $tweetURL . $tweet->id;
		$pubDate = $tweet->created_at;
		$mediaContent = "";
		if (isset($includes) && isset($tweet->attachments) && isset($tweet->attachments->media_keys)) {
			foreach ($tweet->attachments->media_keys as $media_key) {
				foreach ($includes->media as $media) {
					if ($media_key == $media->media_key) {
						if (isset($media->preview_image_url)) {
							$mediaContent = make_rss_media($media->preview_image_url, $mediaContent);
//							$mediaContent = $description . "<img src='" . $media->preview_image_url . "'>";
						}
						if (isset($media->url)) {
							$mediaContent = make_rss_media($media->url, $mediaContent);
//							$description = $description . "<img src='" . $media->url . "'>";
						}

					}
				}
			}
		}
		output_rss_post($title, $link, $description . $mediaContent, $pubDate);
	}
	output_rss_footer();
} else {
	echo "No numeric Twitter ID specified. You can get the numeric ID from a username with sites like https://tweeterid.com/";
}

function make_rss_media($url, $mediaContent) {
	return $mediaContent . "<media:content url=/"" . $url . "/">\n";
}

//Twitter API v2 calls
function get_twitter_userdata($uid, $bearer_token) {
	$url = "https://api.twitter.com/2/users/" . $uid . "?user.fields=profile_image_url";
	$ch = init_curl_fortwitter($url, $bearer_token);

	$response = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo "Fetch error: " . curl_error($ch);
	}
	curl_close($ch);
	return $response;
}

function get_twitter_tweetsforuser($uid, $bearer_token) {
	$url = "https://api.twitter.com/2/users/" . $uid . "/tweets?max_results=20&tweet.fields=attachments,created_at&exclude=replies,retweets&expansions=attachments.media_keys&media.fields=type,url,preview_image_url,alt_text";
	$ch = init_curl_fortwitter($url, $bearer_token);

	$response = curl_exec($ch);
	if (curl_errno($ch)) {
	    echo "Fetch error: " . curl_error($ch);
	}
	curl_close($ch);
	return $response;
}

//cURL stuff
function init_curl_fortwitter($url, $bearer_token) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, 720);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$headers = [];
	$headers[] = 'Authorization: Bearer ' . $bearer_token ;
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	return $ch;
}

function resolve_curl_redirects($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_exec($ch);
	$info = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
	curl_close($ch);
	return $info;
}

//Link Handling
function parse_urls($text, $id) {
	$newText = $text;
	preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $matches);
	foreach ($matches[0] as $match) {
		$actualURL = resolve_curl_redirects($match);
		if (strpos($actualURL, $id) === false) {
			$fullLink = "<a href=\"$actualURL\">" . $actualURL . "</a>";
			$newText = str_replace($match, $fullLink, $newText);
		} else {
			$newText = str_replace($match, "", $newText);
		}
	}
	return $newText;
}

function remove_urls($text) {
	$newText = $text;
	preg_match_all('#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $text, $matches);
	foreach ($matches[0] as $match) {
		$newText = str_replace($match, "", $newText);
	}
	return $newText;
}

//RSS Stuff
function output_rss_header($title, $link, $description, $image) {
	header('Content-Type: text/xml');
	$currPath = "http://" . $_SERVER[HTTP_HOST] . $_SERVER[REQUEST_URI];
	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>\n";
	echo "<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\">\n";
	echo "<channel>\n";
	echo "  <title>$title</title>\n";
	echo "  <link>$link</link>\n";
	echo "  <description>$description</description>\n";
	echo "  <atom:link href=\"" . $currPath . "\" rel=\"self\" type=\"application/rss+xml\" />\n";
	echo "  <image><url>$image</url><title>$title</title><link>$link</link><width>48</width><height>48</height></image>\n";
}

function output_rss_post($title, $link, $description, $pubDate) {
	echo "  <item>\n";
	echo "    <title>$title</title>\n";
	echo "    <link>$link</link>\n";
 	echo "    <description><![CDATA[" . $description . "]]></description>\n";
	echo "    <guid isPermaLink=\"true\">$link</guid>\n";
	echo "  </item>\n";
}

function output_rss_footer() {
	echo "</channel>\n";
	echo "</rss>\n";
}
?>