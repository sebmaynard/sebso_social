<?php
   define("SEBSO_SOCIAL_STATEFILE", "social_state.ser");

   define("SEBSO_SOCIAL_TWITTER_CONSUMER_KEY", "yourkey");
   define("SEBSO_SOCIAL_TWITTER_CONSUMER_SECRET", "yourkey");
   define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN", "yourkey-yourkey");
   define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN_SECRET", "yourkey");

   define("SEBSO_SOCIAL_FACEBOOK_APPID", "yourkey");
   define("SEBSO_SOCIAL_FACEBOOK_APPSECRET", "yourkey");

   // https://github.com/abraham/twitteroauth
   require_once('twitteroauth/twitteroauth.php');

   class SebSoSocialItem {

      var $source;
      var $date;
      var $content;
      var $imgs;

      function __construct($source, $sourceLink, $sourceAccount, $date, $content, $imgs) {
         $this->source = $source;
         $this->sourceLink = $sourceLink;
         $this->sourceAccount = $sourceAccount;
         $this->date = $date;
         $this->content = $content;
         $this->imgs = $imgs;
      }

   };

   class SebSoSocial {

      var $state_file = SEBSO_SOCIAL_STATEFILE;
      var $state_timeout = 300; // 5 minute cache
      var $posts;
      var $updated = 0;

      function __construct($twitterAcc, $facebookAcc, $count) {
         if (!$this->load()) {
	   $fb = array();
	   $tw = array();
	   if(isset($twitterAcc)){
	     $tw = $this->latest_twitter($twitterAcc, $count);
	   }
	   if(isset($facebookAcc)){
	     $fb = $this->latest_facebook($facebookAcc, $count);
	   }
	   $posts = array_merge($fb, $tw);
	   usort($posts, Array($this, "social_sort"));
	   $this->posts = $posts;
	   $this->updated = time();
	   $this->save();
         }
      }

      function load() {
         if (!file_exists($this->state_file)) return false;
         if ($state = file_get_contents($this->state_file)) {
            $loaded = unserialize($state);
            // check if it's expired
            if (($loaded->updated + $this->state_timeout) < time()) return false;
            $this->posts = $loaded->posts;
            $this->updated = $loaded->updated;
            return true;
         }
         else return false;
      }

      function save() {
         file_put_contents($this->state_file, serialize($this));
      }

      function social_sort($social1, $social2) {
         $d1 = $social1->date;
         $d2 = $social2->date;
         if ($d1 == $d2) return 0;
         return ($d1 < $d2) ? 1 : -1;
      }

      function latest_twitter($account, $number) {

         $screen_name = $account;
         $number_of_tweets = $number; // how many do I want?

         $consumerkey = SEBSO_SOCIAL_TWITTER_CONSUMER_KEY;
         $consumersecret = SEBSO_SOCIAL_TWITTER_CONSUMER_SECRET;
         $accesstoken = SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN;
         $accesstokensecret = SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN_SECRET;

         $twitterconn = new TwitterOAuth($consumerkey, $consumersecret, $accesstoken, $accesstokensecret);

         $latesttweets = $twitterconn->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=".$screen_name."&count=".$number_of_tweets);

         $ret = Array();
         foreach ($latesttweets as $tweet) {
            $text = $tweet->text;

            if ($tweet->entities && $tweet->entities->urls) {
               foreach ($tweet->entities->urls as $url) {
                  $text = str_replace($url->url, "<a target='_blank' href='" . $url->expanded_url . "'>" . $url->display_url . "</a><br/>", $text);
               }
            }
            /* return $ret; */
            $date = strtotime($tweet->created_at);
            $imgs = Array();
            if (isset($tweet->entities->media)) {
               foreach ($tweet->entities->media as $media) {
                  $imgs[] = $media->media_url;
               }
            }
            $sourceLink = "https://twitter.com/" . $account . "/status/" . $tweet->id;
            $ret[] = new SebSoSocialItem("twitter", $sourceLink, $account, $date, $text, $imgs);
         }
         return $ret;
      }

      function latest_facebook($account, $number) {
         $appid = SEBSO_SOCIAL_FACEBOOK_APPID;
         $appsecret = SEBSO_SOCIAL_FACEBOOK_APPSECRET;
         $access_token = "$appid|$appsecret";
         $fb = sebso_fetch_url("https://graph.facebook.com/" . $account . "/feed?limit=" . $number . "&access_token=" . $access_token);
         $fb = json_decode($fb);
         $ret = Array();
         foreach ($fb->data as $post) {
	   $date = strtotime($post->created_time);
	   $text = isset($post->message) ? $post->message : "";
	   $imgs = Array();
	   if (isset($post->object_id)) {
	     $imgSizes = sebso_fetch_url("https://graph.facebook.com/v2.1/" . $post->object_id . "?access_token=" . $access_token);
	     $imgSizes = json_decode($imgSizes);
	     foreach ($imgSizes->images as $image) {
	       if (strpos($image->source, "p320x320") !== false) {
		 $imgs = Array($image->source);
		 break;
	       }
	     }
	   }
	   if (!$text && !$imgs) continue;
	   $sourceLink = "https://www.facebook.com/" . $account;
	   $ret[] = new SebSoSocialItem("facebook", $sourceLink, $account, $date, $text, $imgs);
         }
         return $ret;
      }
