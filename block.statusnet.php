<!-- Copy this file to theme directory for modification. -->

<div class="block-<?php echo Utils::slugify($content->title); ?>">
	<a href="http://<?php echo $content->svc; ?>/<?php echo urlencode( $content->username ); ?>">
	<img src="<?php echo htmlspecialchars( $content->notices[0]->image_url ); ?>" alt="<?php echo urlencode( $content->username ); ?>" title="<?php echo $content->svc; ?>/<?php echo urlencode( $content->username ); ?>">
	</a>
	<ul>
		<?php foreach ($content->notices as $notice) : ?>
		<li>
			<?php echo $notice->text . ' @ <a href="' . $notice->permalink . '">' . $notice->time . '</a>'; ?>
		</li>
		<?php endforeach; ?>
	</ul>
	<p><small>via <a href="http://<?php echo $content->svc; ?>"><?php echo $content->svc; ?></a></small></p>
</div>
