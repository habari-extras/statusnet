<!-- This file can be copied and modified in a theme directory -->

<div id="laconicabox">
 <img src="<?php echo htmlspecialchars( $notice_image_url ); ?>" alt="<?php echo urlencode( Options::get( 'laconica__username' )); ?>">
 <p><?php echo $notice_text . ' @ ' . $notice_time; ?></p>
<p><small>via <a href="http://<?php echo Options::get('laconica__svc'); ?>/index.php?action=showstream&amp;nickname=<?php echo urlencode( Options::get( 'laconica__username' )); ?>"><?php echo Options::get('laconica__svc'); ?></a></small></p>
 </div>
