<?php

   if (!defined("SEBSO_SOCIAL_STATEFILE")) define("SEBSO_SOCIAL_STATEFILE", "social_state.ser");

   if (!defined("SEBSO_SOCIAL_TWITTER_CONSUMER_KEY")) define("SEBSO_SOCIAL_TWITTER_CONSUMER_KEY", "yourkey");
   if (!defined("SEBSO_SOCIAL_TWITTER_CONSUMER_SECRET")) define("SEBSO_SOCIAL_TWITTER_CONSUMER_SECRET", "yourkey");
   if (!defined("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN")) define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN", "yourkey-yourkey");
   if (!defined("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN_SECRET")) define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN_SECRET", "yourkey");

   if (!defined("SEBSO_SOCIAL_FACEBOOK_APPID")) define("SEBSO_SOCIAL_FACEBOOK_APPID", "yourkey");
   if (!defined("SEBSO_SOCIAL_FACEBOOK_APPSECRET")) define("SEBSO_SOCIAL_FACEBOOK_APPSECRET", "yourkey");

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
            $tw = Array();
            $fb = Array();
            // double the count so after filtering of unwanted posts we can still try and get the desired number
            if ($twitterAcc) $tw = $this->latest_twitter($twitterAcc, $count * 2);
            if ($facebookAcc) $fb = $this->latest_facebook($facebookAcc, $count * 2);
            $tw = array_slice($tw, 0, 10);
            $fb = array_slice($fb, 0, 10);
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
            if (!is_object($tweet)) continue;
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
         if (!empty($fb->data)) {
            foreach ($fb->data as $post) {
               $date = strtotime($post->created_time);
               $text = isset($post->message) ? $post->message : "";
               $imgs = Array();
               if (isset($post->object_id)) {
                  $imgSizes = sebso_fetch_url("https://graph.facebook.com/v2.1/" . $post->object_id . "?access_token=" . $access_token);
                  $imgSizes = json_decode($imgSizes);
                  if (isset($imgSizes->images)) {
                     foreach ($imgSizes->images as $image) {
                        if (strpos($image->source, "p320x320") !== false) {
                           $imgs = Array($image->source);
                           break;
                        }
                     }
                  }
               }
               // no images, but existing text and a link?
               if (!$imgs && ($text && isset($post->link))) $text = $text . " " . $post->link;
               if (!$text && !$imgs) {
                  continue;
               }
               $sourceLink = "https://www.facebook.com/" . $account;
               $ret[] = new SebSoSocialItem("facebook", $sourceLink, $account, $date, $text, $imgs);
            }
         }
         return $ret;
      }

   }

   function sebso_fetch_url($url) {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $ret = curl_exec($ch);
      curl_close($ch);
      return $ret;
   }

