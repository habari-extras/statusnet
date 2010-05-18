<!-- Copy this file to theme directory for modification. -->

<div id="statusnetbox">
	<a href="http://<?php echo Options::get('statusnet__svc'); ?>/<?php echo urlencode( Options::get( 'statusnet__username' )); ?>">
	<img src="<?php echo htmlspecialchars( $notices[0]->image_url ); ?>" alt="<?php echo urlencode( Options::get( 'statusnet__username' ) ); ?>" title="<?php echo Options::get('statusnet__svc'); ?>/<?php echo urlencode( Options::get( 'statusnet__username' ) ); ?>">
	</a>
	<ul>
		<?php foreach ($notices as $notice) : ?>
		<li>
			<?php echo $notice->text . ' @ <a href="' . $notice->permalink . '">' . $notice->time . '</a>'; ?>
		</li>
		<?php endforeach; ?>
	</ul>
	<p><small>via <a href="http://<?php echo Options::get('statusnet__svc'); ?>"><?php echo Options::get('statusnet__svc'); ?></a></small></p>
</div>
