<?php 
	if (isset($_GET["source"]))
		die("Soon");
	
	require("config.php"); // for $secret.
	
	$sessionsdir = "sessions/";
	
	if (!empty($_SERVER["HTTP_IF_NONE_MATCH"])) {
		$etag = substr(str_replace(".", "", str_replace("/", "", str_replace("\\", "", $_SERVER["HTTP_IF_NONE_MATCH"]))), 0, 18);
	}
	else {
		$etag = substr(sha1($secret . sha1($_SERVER["REMOTE_ADDR"])), 0, 18);
	}
	
	function initsession($etag, $force_reinit = false) {
		global $session, $sessionsdir;
		if (!$force_reinit && file_exists($sessionsdir . $etag)) {
			$session = unserialize(file_get_contents($sessionsdir . $etag));
		}
		else {
			$session = array("visits" => 0, "last_visit" => time(), "your_string" => "");
		}
	}
	
	function updatesession() {
		global $session;
		$session["visits"] += 1;
		$session["last_visit"] = time();
	}
	
	function storesession($etag) {
		global $session, $sessionsdir;
		$fid = fopen($sessionsdir . $etag, "w");
		fwrite($fid, serialize($session));
		fclose($fid);
	}
	
	initsession($etag);
	
	// .htaccess rewrites to ?static if the 'static' file is requested.
	if (isset($_GET["static"])) {
		if (empty($_SERVER["HTTP_IF_NONE_MATCH"])) {
			unlink($sessionsdir . $etag);
			unset($session);
			initsession($etag);
		}
		updatesession();
		storesession($etag);
		header("Cache-Control: private, max-age=31415926, must-revalidate, proxy-revalidate");
		header("ETag: " . substr($etag, 0, 18));
		header("Content-type: image/jpeg");
		header("Content-length: " . filesize("1x1.jpg"));
		readfile("1x1.jpg");
		exit;
	}
	
	// Vulnerable to CSRF attacks, I know. I didn't think it really mattered
	// since XSS is impossible and no important data is stored.
	if (isset($_POST["newstring"])) {
		$session["your_string"] = substr(htmlentities($_POST["newstring"]), 0, 500);
		storesession($etag);
		header("Location: ./");
		exit;
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Lucb1e.com :: Cookieless Cookies</title>
	</head>
	<body>
		<img src="static.jpg"/>

		<div style="width: 632px; font-size: 1em; margin: 0 auto 0 auto; margin-top: 40px;">
			<div style="float: right; margin-left: 5px;">
				<img src="fingerprinting.jpg" height=150 />
			</div>
			<h2>Cookieless cookies</h2>
			
			This page demonstrates uniquely identifying your browser <b>without</b> using any of the following:<br/>
			<ul>
				<li>Cookies</li>
				<li>Javascript</li>
				<li>LocalStorage/SessionStorage/GlobalStorage</li>
				<li>Flash, Java or other plugins</li>
				<li>Your IP address or user agent string</li>
				<li>Any methods employed by <a href="https://panopticlick.eff.org" target="_blank">Panopticlick</a></li>
			</ul>

			Instead it will use another type of identification that is persistent between browser restarts: <b>cache</b>.<br/>
			<br/>
			It's known that websites already use this technique to track you, even when you disabled cookies entirely and have Javascript turned off.
			And you don't have to believe me, <a href="https://en.wikipedia.org/wiki/HTTP_ETag#Tracking_using_ETags" target="_blank">just read Wikipedia</a>.<br/>
			<br/>
			
			<hr/>
			
			<h3>Demonstration</h3>
			As you read this, you have already been tagged. Sorry. The good news is that I don't link your session id to any
			personally identifiable information. Here is everything I store about you:<br/>
			<br/>
			<form method="POST" action="./">
				<b>Number of visits:</b> <?php echo $session["visits"]; ?><br/>
				<br/>
				<b>Last visit:</b> <?php echo date("r", $session["last_visit"]); ?><br/>
				<br/>
				<b>Want to store some text here?</b><br/>
				<textarea name=newstring style="width: 632px;" rows=4><?php echo $session["your_string"]; ?></textarea><br/>
				(max. 350 characters)<br/>
				<input type=submit value=Store />
			</form>
			<br/>
			Go ahead, type something and store it. Then close your browser and open this page again. Is it still there?<br/>
			<br/>
			<hr/>
			
			<h3>So how does this work?</h3>
			This is a general overview:<br/>
			<br/>
			<img src="etags.jpg"/><br/>
			<br/>
			The ETag shown in the image is a sort of checksum. When the image changes, the checksum changes. So when the browser
			has the image and knows the checksum, it can send it to the webserver for verification. The webserver then checks
			whether the image has changed. If it hasn't, the image does not need to be retransmitted and lots of data is saved.<br/>
			<br/>
			Attentive readers might have noticed already how you can use this to track people: the browser sends the information
			to the server which it just received. That sounds an awful lot like cookies, doesn't it? The server can simply give
			each browser an unique ETag, and when they connect again it can look it up in its database.<br/>
			<br/>
			And that's what this page does too.<br/>
			<br/>
			<b>Technical stuff</b> (and bugs in this demo)<br/>
			For demonstrational purposes I want to show you what I store without having to use Javascript, which creates some
			restrictions on what I can do. Because the page is loaded before the hidden image is loaded (ETags on pages do not
			work very well, you need to use an image), and I want to show the data in the page, we have a chicken and egg problem.
			To solve this I use your IP address as the common piece of information, but this would not normally be needed.
			Not that trackers won't use it, your IP is a great method of identification even when you use a proxy, but it's
			just not required for this technique.<br/>
			<br/>
			Anyway, what I meant by "restrictions on what I can do" is: when you visit a page where you don't have an ETag,
			your session will be purged. For example if you open a private navigation window and load this page, it will be
			loaded without ETags attached. Your session file is then removed/reset and on next pageload you'll be using an
			empty session. But when you return to your original browsing session, it will also be purged because it was the
			same session. That is a genuine bug in this demonstration, but I did not see a simple solution to this. Sure some
			things can be done, but nothing that other websites would use, and I wanted to keep the basic use-case as close to
			reality as possible.<br/>
			<br/>
			<b>Source code</b><br/>
			What's a project without source code? <span style="color: #444; font-size: 0.9em;">Oh right, Microsoft Windows.</span><br/>
			<a href="https://github.com/lucb1e/cookielesscookies" target="_blank">https://github.com/lucb1e/cookielesscookies</a>
			<br/>
			<hr/>
			
			<h3>What can we do to stop it?</h3>
			One thing I would strongly recommend you to do anytime you visit a page where you want a little more
			security, is opening a private navigation window and using https exclusively. Doing this single-handedly
			eliminates attacks like BREACH (the latest https hack), disables any and all tracking cookies that you
			might have, and also eliminates cache tracking issues like I'm demonstrating on this page. I use this
			private navigation mode when I do online banking. In Firefox (and I think MSIE too) it's Ctrl+Shift+P,
			in Chrome it's Ctrl+Shift+N.<br/>
			<br/>
			Besides that, it depends on your level of paranoia.<br/>
			<br/>
			I currently have no straightforward answer since it's virtually undetectable, but also because caching itself
			is useful and saves people (including you) time and money. Website admins will consume less bandwidth (and if
			you think about it, in the end users are the ones that will have to pay the bill), your pages will load faster,
			and especially on mobile devices it makes a big difference if you don't have an unlimited 4G plan. It's even
			worse when you have a high-latency or low-bandwidth connection because you live in a rural area.<br/>
			<br/>
			If you're very paranoid, it's best to just disable caching altogether. This will stop any such tracking from
			happening, but I personally don't believe it's worth the downsides.<br/>
			<br/>
			The Firefox add-on Self-Destructing Cookies has the ability to empty your cache when you're not using your
			browser for a while. This might be an okay alternative to disabling caching; you can <i>only</i> be tracked during
			your visit, and they can already do that anyway by following which pages were visited by which IP address, so
			that's no big deal. Any later visits will appear as from a different user, assuming all other tracking
			methods have already been prevented.<br/>
			<br/>
			I'm not aware of any add-on that periodically removes your cache (e.g. once per 72 hours), but there might be.
			This would be another good alternative for 99% of the users because it has a relatively low performance impact
			while still limiting the tracking capabilities.<br/>
			<br/>
			
			<div style="margin-top: 50px; color: #888; font-size: 0.9em;">
				Written by lucb1e in 2013.<br/>
				All text, resources and methods on this page are hereby released as WTFPL - www.wtfpl.net
			</div>
		</div>
		<div style="height: 500px;">&nbsp;</div> <!-- I often scroll past the end of the document, it's nicer to read I think -->
	</body>
</html>