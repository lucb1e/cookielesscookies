<?php
if (isset($_GET["source"])) {
    die("See <a href='https://github.com/lucb1e/cookielesscookies'>github.com/lucb1e/cookielesscookies</a>");
}

require("config.php"); // for $secret.

$sessionsdir = "sessions/";

// An ETag was sent to the webserver
if (!empty($_SERVER["HTTP_IF_NONE_MATCH"])) {
    // This is what you would normally do
    $etag = substr(str_replace(".", "", str_replace("/", "", str_replace("\\", "", $_SERVER["HTTP_IF_NONE_MATCH"]))), 0, 18);
} else { // No etag was sent. We need to generate one. Normally you would derive this from randomness.
    $etag = substr(sha1($secret . sha1($_SERVER["REMOTE_ADDR"]) . sha1($_SERVER["HTTP_USER_AGENT"])), 0, 18);
}

// Initialize a new or existing session given any etag.
function initsession($etag, $force_reinit = false)
{
    global $session, $sessionsdir;
    if (!$force_reinit && file_exists($sessionsdir . $etag)) {
        $session = unserialize(file_get_contents($sessionsdir . $etag));
    } else {
        $session = array("visits" => 1, "last_visit" => time(), "your_string" => "");
    }
}

function updatesession()
{
    global $session;
    $session["visits"] += 1;
    $session["last_visit"] = time();
}

// Write any changes to the disk
function storesession($etag)
{
    global $session, $sessionsdir;
    $fid = fopen($sessionsdir . $etag, "w");
    fwrite($fid, serialize($session));
    fclose($fid);
}

initsession($etag);

// .htaccess rewrites to ?tracker if the 'tracker.jpg' file is requested.
if (isset($_GET["tracker"])) {
    // No ETag sent? Make sure we use a new session.
    if (empty($_SERVER["HTTP_IF_NONE_MATCH"])) {
        @unlink($sessionsdir . $etag); // may or may not exist
        unset($session);
        initsession($etag);
    }
    updatesession();
    storesession($etag);
    header("Cache-Control: private, must-revalidate, proxy-revalidate");
    header("ETag: " . substr($etag, 0, 18)); // our "cookie"
    header("Content-type: image/jpeg");
    header("Content-length: " . filesize("fingerprinting.jpg"));
    readfile("fingerprinting.jpg");
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
    <style>
        body {
            font-family: Arial, serif;
        }
    </style>
</head>
<body>
<div style="width: 632px; font-size: 1em; margin: 40px auto 0;">
    <div style="float: right; margin-left: 15px;">
        <img src="tracker.jpg"/>
    </div>
    <h2>Cookieless cookies</h2>

    There is another obscure way of tracking users without using cookies or even Javascript. It has already being
    used by numerous websites but few people know of it. This page explains how it works and how to protect
    yourself.<br/>
    <br/>
    <br/>
    This tracking method works <b>without</b> needing to use:<br/>
    <ul>
        <li>Cookies</li>
        <li>Javascript</li>
        <li>LocalStorage/SessionStorage/GlobalStorage</li>
        <li>Flash, Java or other plugins</li>
        <li>Your IP address or user agent string</li>
        <li>Any methods employed by <a href="https://panopticlick.eff.org" target="_blank">Panopticlick</a></li>
    </ul>

    Instead it uses another type of storage that is persistent between browser restarts: <b>caching</b>.<br/>
    <br/>
    Even when you disabled cookies entirely, have Javascript turned off and use a VPN service, this technique will
    still be able to track you.<br/>
    <br/>
    <hr/>

    <a name="demo"></a>
    <h3>Demonstration</h3>
    As you read this, you have already been tagged. Sorry. The good news is that I don't link your session id to any
    personally identifiable information. Here is everything I store about you right now:<br/>
    <br/>
    <form method="POST" action="./">
        <b>Number of visits:</b> <?php echo $session["visits"]; ?><br/>
        <br/>
        <b>Last visit:</b> <?php echo date("r", $session["last_visit"]); ?><br/>
        <br/>
        <b>Want to store some text here?</b><br/>
        <textarea name=newstring style="width: 632px;" rows=4 title="newstring">
            <?php echo $session["your_string"]; ?>
        </textarea><br/>
        (max. 350 characters)<br/>
        <input type=submit value=Store/>
    </form>
    <br/>
    Go ahead, type something and store it. Then close your browser and open this page again. Is it still there?<br/>
    <br/>
    Check your cookies, is anything there? Nope, it's all in a fake image checksum that almost noone is aware of.
    Saw that eye on the right top of the page? That's our tracker.<br/>
    <br/>
    <hr/>

    <a name="how"></a>
    <h3>So how does this work?</h3>
    This is a general overview:<br/>
    <br/>
    <img src="etags.jpg"/><br/>
    <br/>
    The ETag shown in the image is a sort of checksum. When the image changes, the checksum changes. So when the browser
    has the image and knows the checksum, it can send it to the webserver for verification. The webserver then checks
    whether the image has changed.
    If it hasn't, the image does not need to be retransmitted and lots of data is saved.<br/>
    <br/>
    Attentive readers might have noticed already how you can use this to track people:
    the browser sends the information back to the server that it previously received (the ETag).
    That sounds an awful lot like cookies, doesn't it? The server can simply give each browser an unique ETag,
    and when they connect again it can look it up in its database.<br/>
    <br/>
    <a name="#demotech"></a>
    <b>Technical stuff (and bugs) specifically about this demo</b><br/>
    To demonstrate how this works without having to use Javascript,
    I had to find a piece of information that's relatively unique to you besides this ETag.
    The image is loaded <i>after</i> the page is loaded, but only the image contains the ETag.
    How can I display up to date info on the page?
    Turns out I can't really do that without dynamically updating the page, which requires javascript,
    which I wanted to avoid to show that it can be done without.<br/>
    <br/>
    This chicken and egg problem introduces a few bugs:<br/>
    - All information you see was from your previous pageload. Press F5 to see updated data.<br/>
    - When you visit a page where you don't have an ETag (like incognito mode), your session will be emptied.
    Again, this is only visible when you reload the page.<br/>
    <br/>
    I did not see a simple solution to these issues.
    Sure some things can be done, but nothing that other websites would use,
    and I wanted to keep the code as simple and as close to reality as possible.<br/>
    <br/>
    Note that these bugs normally don't exist when you really want to track someone
    because then you don't intend to show users that they are being tracked.<br/>
    <br/>
    <b>Source code</b><br/>
    What's a project without source code?
    <span style="color: #666; font-size: 0.9em;">Oh right, Microsoft Windows.</span><br/>
    <br/>
    <a href="https://github.com/lucb1e/cookielesscookies"
       target="_blank">https://github.com/lucb1e/cookielesscookies</a><br/>
    <br/>
    <hr/>

    <a name="whatdowedo"></a>
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
    I currently have no straightforward answer since cache tracking is virtually undetectable, but also because caching
    itself is useful and saves people (including you) time and money. Website admins will consume less bandwidth (and
    if you think about it, in the end users are the ones that will have to pay the bill), your pages will load faster,
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

    <div style="margin-top: 20px; color: #888; font-size: 0.9em;">
        Written by lucb1e in 2013.<br/>
        All text, resources and methods on this page are hereby released as WTFPL -
        <a rel='license' href='http://www.wtfpl.net'>www.wtfpl.net</a>
    </div>
</div>
<!-- I often scroll past the end of the document, it's nicer to read I think -->
<div style="height: 500px;">&nbsp;</div>
</body>
</html>
