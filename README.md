SebSoSocial
============

A simple php class to get the combined latest posts from combined Facebook and Twitter accounts

Usage
=====

First, you'll need to create Facebook and Twitter applications to get your developer keys, then define these before you include the lib.

Next up, you'll need to make sure you have the Twitter oauth library (I used this one: https://github.com/abraham/twitteroauth) available. The source file does:

```php
    require_once('twitteroauth/twitteroauth.php');
```

before anything else.

To use it, instantiate the SebSoSocial class, passing the desire Twitter account name, Facebook page name, and number of each type of post you'd like:

```php
    $social = new SebSoSocial($twitter, $facebook, $numOfEach);
```

Then loop over `$social->posts` to get classes with `->source` (e.g. "Facebook"), `->sourceLink` (link to the original post), `->sourceAccount` and `->imgs` (an array of urls of images for the post).

```php
<?php
   foreach ($social->posts as $post) {
      echo $post->content;
   }
?>
```

Example
=======

This chunk of code:

```php
<style>
   img { max-width: 100px; max-height: 100px; }
   .social-post { margin-bottom : 20px; }
</style>

<?php
   define("SEBSO_SOCIAL_TWITTER_CONSUMER_KEY", "yourkey");
   define("SEBSO_SOCIAL_TWITTER_CONSUMER_SECRET", "yourkey");
   define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN", "yourkey");
   define("SEBSO_SOCIAL_TWITTER_ACCESS_TOKEN_SECRET", "yourkey");

   define("SEBSO_SOCIAL_FACEBOOK_APPID", "yourkey");
   define("SEBSO_SOCIAL_FACEBOOK_APPSECRET", "yourkey");

   include("sebso_social.php");
   $twitter = "Google";
   $facebook = "Google";
   $numOfEach = 10;
   $social = new SebSoSocial($twitter, $facebook, $numOfEach); ?>
   <?php foreach ($social->posts as $post) : ?>
   <div class="social-post">
      <table border="1">
         <tr><td>Source</td><td><?php echo $post->source; ?></td></tr>
         <tr><td>Content</td><td><?php echo $post->content; ?></td></tr>
         <tr><td>Date</td><td><?php echo $post->date; ?></td></tr>
         <tr><td>Link</td><td><?php echo $post->sourceLink; ?></td></tr>
         <tr><td>Account</td><td><?php echo $post->sourceAccount; ?></td></tr>
         <tr><td>Images[0]</td><td><?php if ($post->imgs) : ?><img src="<?php echo $post->imgs[0]; ?>" /><?php endif; ?></td></tr>
      </table>
   </div>
   <?php endforeach; ?>
```

generates a table of recent Facebook and Twitter posts, sorted by reverse date.

Why sebso_social?
=================
Because my website is at http://seb.so; and this was a social plugin I built for a client for use in a WordPress site.
