<?php
/**
 * StatusNet Twitter-Compat API Plugin
 *
 * Show your latest StatusNet notices in your theme and/or
 * post your latest blog post to your StatusNet service.
 *
 * Usage: <?php $theme->statusnet(); ?> to show your latest notices.
 * Copy the statusnet.php template to your active theme to customize
 * output display.
 *
 **/

class StatusNet extends Plugin
{
	/**
	 * Pro-forma update beacon support
	 **/
	public function action_update_check()
	{
		Update::add( 'StatusNet', '8676A858-E4B1-11DD-9968-131C56D89593', $this->info->version );
	}

	/**
	 * Add StatusNet options to each user's profile page.
	 **/
	public function action_form_user( $form, $edit_user )
	{
		$statusnet_svc = ( isset( $edit_user->info->statusnet_svc ) ) ? $edit_user->info->statusnet_svc : '';
		$statusnet_name = ( isset( $edit_user->info->statusnet_name ) ) ? $edit_user->info->statusnet_name : '';
		$statusnet_pass = ( isset( $edit_user->info->statusnet_pass ) ) ? $edit_user->info->statusnet_pass : '';
		
		$statusnet = $form->insert( 'page_controls', 'wrapper', 'statusnet', _t( 'StatusNet', 'statusnet' ) );
		$statusnet->class = 'container settings';
		$statusnet->append( 'static', 'statusnet', '<h2>' . htmlentities( _t( 'StatusNet', 'statusnet' ), ENT_COMPAT, 'UTF-8' ) . '</h2>' );
		
		$form->move_after( $statusnet, $form->change_password );
		$statusnet_svc = $form->statusnet->append( 'text', 'statusnet_svc', 'null:null', _t('&micro;blog service:', 'statusnet' ), 'optionscontrol_text' );
		$statusnet_svc->class[] = 'item clear';
		$statusnet_svc->value = $edit_user->info->statusnet_svc;		
		$statusnet_svc->charlimit = 64;
		$statusnet_svc->helptext = _t( 'Enter the portion of your &micro;blog URL between the slash at the end of <tt>http://</tt> and the slash before your user name: <tt>http://</tt><strong>statusnet.service</strong><tt>/</tt><em>yourname</em>.', 'statusnet' );
		
		$statusnet_name = $form->statusnet->append( 'text', 'statusnet_name', 'null:null', _t( 'Service username', 'statusnet' ), 'optionscontrol_text' );
		$statusnet_name->class[] = 'item clear';
		$statusnet_name->value = $edit_user->info->statusnet_name;
		$statusnet_name->charlimit = 64;
		
		$statusnet_pass = $form->statusnet->append( 'text', 'statusnet_pass', 'null:null', _t( 'Service password', 'statusnet' ), 'optionscontrol_text' );
		$statusnet_pass->class[] = 'item clear';
		$statusnet_pass->type = 'password';
		$statusnet_pass->value = $edit_user->info->statusnet_pass;
		$statusnet_pass->helptext = '';
		
		$statusnet_prefix = $form->statusnet->append( 'text', 'statusnet_prefix', 'null:null', _t( 'Prefix (e.g., "New post: "):', 'statusnet' ), 'optionscontrol_text' );
		$statusnet_prefix->class[] = 'item clear';
		$statusnet_prefix->value = $edit_user->info->statusnet_prefix;
	}
	
	/**
	 * Add the StatusNet user options to the list of valid field names.
	 * This causes adminhandler to recognize the statusnet fields and
	 * to set the userinfo record appropriately.
	**/
	public function filter_adminhandler_post_user_fields( $fields )
	{
		$fields['statusnet_svc'] = 'statusnet_svc';
		$fields['statusnet_name'] = 'statusnet_name';
		$fields['statusnet_pass'] = 'statusnet_pass';
		$fields['statusnet_prefix'] = 'statusnet_prefix';
		return $fields;
	}

	/**
	 * Post a status to service
	 * @param string $svcurl Catenation of user server, API endpoints
	 * @param string $notice The new status to post
	 **/
	public function post_status( $svcurl, $notice, $name, $pw )
	{
		$request = new RemoteRequest( $svcurl, 'POST' );
		$request->add_header( array( 'Authorization' => 'Basic ' . base64_encode( "{$name}:{$pw}" ) ) );
		$request->set_body( 'source=habari&status=' . urlencode( $notice ) );
		$request->execute();
	}

	/**
	 * React to the update of a post status to 'published'
	 * @param Post $post The post object with the status change
	 * @param int $oldvalue The old status value
	 * @param int $newvalue The new status value
	 **/
	public function action_post_update_status( $post, $oldvalue, $newvalue )
	{
		if ( is_null( $oldvalue ) ) return;
		if ( $newvalue == Post::status( 'published' ) && $post->content_type == Post::type('entry') && $newvalue != $oldvalue ) {
			$user = User::get_by_id( $post->user_id );
			if ( ! empty( $user->info->statusnet_name ) && ! empty( $user->info->statusnet_pass ) ) {
				$name = $user->info->statusnet_name;
				$pw = $user->info->statusnet_pass;
				$svcurl = 'https://' . $user->info->statusnet_svc . '/api/statuses/update.xml';
				$this->post_status( $svcurl, $user->info->statusnet_prefix . $post->title . ' ' . $post->permalink, $name, $pw );
			}
		}
	}

	/**
	 * After
	 * @param Post $post
	 **/
	public function action_post_insert_after( $post )
	{
		return $this->action_post_update_status( $post, -1, $post->status );
	}

	/**
	 * Fetch notices from service.
	 * @param string $svc The statusnet server
	 * @param string $username The user on the statusnet server
	 * @param bool $hide_replies Suppress @-notices
	 * @param int $limit Number of notices to fetch
	 * @param bool $linkify_urls Output anchor HTML for URLS
	 * @param string $cache The name of the cache group to use
	 * @param int $cachettl Cache duration in seconds
	 * @return array notices The status messages
	 **/
	public function notices( $svc, $username, $hide_replies = false, $limit = 1, $linkify_urls = false, $cache = 'statusnet', $cachettl = 60 )
	{
		$notices = array();
		if ( $svc && $username != '' ) {
			$statusnet_url = 'http://' . $svc . '/api/statuses/user_timeline/' . urlencode( $username ) . '.xml';
			
			/* 
			 * Only need to get a single notice if @replies are hidden.
			 * (Otherwise, rely on the maximum returned and hope one is a non-reply.)
			 */
			if ( ! $hide_replies && $limit ) {
				$statusnet_url .= '?count=' . $limit;
			}
			// Get cache group.
			if ( Cache::has_group( $cache ) ) {
				$notices = Cache::get_group( $cache );
			}
			else {
				try {
					$response = RemoteRequest::get_contents( $statusnet_url );
					$xml = @new SimpleXMLElement( $response );
					// Check we've got a load of statuses returned
					if ( $xml->getName() === 'statuses' ) {
						foreach ( $xml->status as $status ) {
							if ( ( ! $hide_replies ) || ( strpos( $status->text, '@' ) === false) ) {
								$notice = (object) array (
									'text' => (string) $status->text, 
									'time' => (string) $status->created_at, 
									'image_url' => (string) $status->user->profile_image_url,
									'id' => (int) $status->id,
									'permalink' => 'http://' . $svc . '/notice/' . (string) $status->id,
								);
								
								$notices[] = $notice;
								if ( $hide_replies && count($notices) >= $limit )
									break;
							}
							else {
							// it's a @. Keep going.
							}
						}
						if ( !$notices ) {
							$notice->text = 'Only replies available from service.';
							$notice->permalink = '';
							$notice->time = '';
							$notice->image_url = '';
						}
					}
					// You can get error as a root element if service is in maintance mode.
					else if ( $xml->getName() === 'error' ) {
						$notice->text = (string) $xml;
						$notice->permalink = '';
						$notice->time = '';
						$notice->image_url = '';
					}
					// Should not be reached.
					else {
						$notice->text = 'Received unexpected XML from service.';
						$notice->permalink = '';
						$notice->time = '';
						$notice->image_url = '';
					}
				}
				catch ( Exception $e ) {
					$notice->text = 'Unable to contact service.';
					$notice->permalink = '';
					$notice->time = '';
					$notice->image_url = '';
				}
				if (!$notices) {
					$notices[] = $notice;
				}
				// Cache (even errors) to avoid hitting rate limit.
				// Use cache group to cache multiple statuses (objects).
				// Name caches dynamically, so one cache per cacher
				// (e.g., several Blocks with different users/services).
				foreach ($notices as $i => $notice) {
					Cache::set( array( $cache, $i), $notice, $cachettl );
				}
			}
			if ( $linkify_urls != FALSE ) {
				/* http: links */
				foreach ($notices as $notice) {
					$notice->text = preg_replace( '%https?://\S+?(?=(?:[.:?"!$&\'()*+,=]|)(?:\s|$))%i', "<a href=\"$0\">$0</a>", $notice->text );
				}
			}
		}
		else {
			$notice = (object) array (
			'text' => _t('Check "µblog service" and "Service username" settings in <a href="%s">StatusNet plugin config</a>', array( URL::get( 'admin' , 
			'page=plugins&configure=' . $this->plugin_id . '&configaction=Configure' ) . '#plugin_' . $this->plugin_id ) , 'statusnet' ), 
			'time' => '', 
			'image_url' => ''
			);
			$notices[] = $notice;
		}
		return $notices;
	}

	/**
	 * Add statusnet block to theme admin options.
	 */	
	public function filter_block_list($block_list)
	{
		$block_list['statusnet'] = _t('StatusNet');
		return $block_list;
	}
	
	/**
	 * Configure a block
	 **/
	public function action_block_form_statusnet( $form, $block )
	{
		
		$sn_fieldset = $form->append( 'fieldset', 'tweet_settings', _t( 'µblog Settings', 'statusnet' ) );
		
		$statusnet_svc = $sn_fieldset->append( 'text', 'svc', $block, _t( 'µblog service:', 'statusnet' ) );
		
		$statusnet_username = $sn_fieldset->append( 'text', 'username', $block, _t( 'Service username:', 'statusnet' ) );
				
		$statusnet_limit = $sn_fieldset->append( 'select', 'limit', $block, _t( ('Number of notices to display:'), 'statusnet' ) );
		$statusnet_limit->options = array_combine(range(1, 20), range(1, 20));
		
		$statusnet_show = $sn_fieldset->append( 'checkbox', 'hide_replies', $block, _t( 'Hide @replies', 'statusnet' ) );
		
		$statusnet_show = $sn_fieldset->append( 'checkbox', 'linkify_urls', $block, _t( 'Linkify URLs', 'statusnet' ) );
				
		$statusnet_cache_time = $sn_fieldset->append( 'text', 'cachettl', $block, _t( 'Cache expiry in seconds:', 'statusnet' ) );
		
		$form->append( 'submit', 'save', _t( 'Save', 'statusnet' ) );
	}
	
	/**
	 * Put content in block
	 */
	public function action_block_content_statusnet($block, $theme)
	{
		$block->notices = $this->notices( $block->svc, $block->username, $block->hide_replies, $block->limit,
										 $block->linkify_urls, $block->svc . '.' . $block->username, $block->cachettl );
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->add_template('block.statusnet', dirname(__FILE__) . '/block.statusnet.php');
	}	
	
}

?>
