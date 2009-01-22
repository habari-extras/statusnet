<?php
/**
 * Laconica Twitter-Compat API Plugin
 *
 * Show your current status at your chosen Laconica server in your theme,
 * as well as optionally post your new posts to your Laconica service.
 *
 * Usage: <?php $theme->laconica(); ?> to show your latest notice.
 * Copy the laconica.tpl.php template to your active theme to customize
 * output display.
 *
 **/

class Laconica extends Plugin
{
	/**
	 * Required plugin information
	 * @return array The array of information
	 **/
	public function info()
	{
		return array(
			'name' => 'Laconica',
			'version' => '0.6',
			'url' => 'http://habariproject.org/',
			'author' => 'Habari Community',
			'authorurl' => 'http://habariproject.org/',
			'license' => 'Apache License 2.0',
			'description' => 'Post to and display from <a href="http://laconi.ca">Laconica</a> servers.',
			'copyright' => '2009'
		);
	}

	/**
	 * Update beacon support. UID is real, but not reg'd with project.
	 **/

	public function action_update_check()
	{
	 	Update::add( 'Laconica', '8676A858-E4B1-11DD-9968-131C56D89593', $this->info->version );
	}

	public function help()
	{
		$help = _t('<p>For the <strong>Laconica Service</strong> setting,
				enter the portion of your Laconica server home page
				URL between the slash at the end of <tt>http://</tt>
				and the slash before your user name:
				<tt>http://</tt><strong>laconica.service</strong><tt>/</tt><em>yourname</em>.</p>
				<p>To use identi.ca, for example, since your URL is
				something like <tt>http://identi.ca/yourname</tt>,
				you would enter <tt>identi.ca</tt>.</p>');
		return $help;
	}

	/**
	 * Add actions to the plugin page for this plugin
	 * @param array $actions An array of actions that apply to this plugin
	 * @param string $plugin_id The string id of a plugin, generated by the system
	 * @return array The array of actions to attach to the specified $plugin_id
	 **/
	public function filter_plugin_config( $actions, $plugin_id )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			$actions[] = 'Configure';
		}

		return $actions;
	}

	/**
	 * Set defaults.
	 **/
	public function action_plugin_activation( $file )
	{
		if(Plugins::id_from_file($file) == Plugins::id_from_file(__FILE__)) {
			if ( Options::get( 'laconica__hide_replies' ) == null ) {
				Options::set( 'laconica__hide_replies', 0 );
			}
			if ( Options::get( 'laconica__linkify_urls' ) == null ) {
				Options::set( 'laconica__linkify_urls', 0 );
			}
			if ( Options::get( 'laconica__svc' ) == null ) {
				Options::set( 'laconica__svc', 'identi.ca' );
			}
			if ( Options::get( 'laconica__show' ) == null ) {
				Options::set( 'laconica__show', 1 );
			}
		}
	}

	/**
	 * Respond to the user selecting an action on the plugin page
	 * @param string $plugin_id The string id of the acted-upon plugin
	 * @param string $action The action string supplied via the filter_plugin_config hook
	 **/
	public function action_plugin_ui( $plugin_id, $action )
	{
		if ( $plugin_id == $this->plugin_id() ) {
			
			if ( $action == _t( 'Configure' ) ) {
				
				$ui = new FormUI( strtolower( get_class( $this ) ) );
				$laconica_svc = $ui->append( 'text', 'svc', 'laconica__svc', _t('Laconica Service:') );
//				$laconica_svc->add_validator('validate_url');
				$laconica_username = $ui->append( 'text', 'username', 'laconica__username', 
					_t('Service Username:') );
				$laconica_password = $ui->append( 'password', 'password', 'laconica__password', 
					_t('Service Password:') );
				$laconica_post = $ui->append( 'select', 'post_status', 'laconica__post_status', 
					_t('Autopost to Service:') );
				$laconica_post->options = array( '0' => _t('Disabled'), '1' => _t('Enabled') );
				$laconica_post = $ui->append( 'text', 'prepend', 'laconica__prepend',
				 _t('Prepend to Autopost:'));
				$laconica_post->value = "New Blog Post:";
				$laconica_show = $ui->append( 'select', 'show', 'laconica__show', 
					_t('Make Posts available to Habari') );
				$laconica_show->options = array( '0' => _t('No'), '1' => _t('Yes') );
				$laconica_show = $ui->append( 'select', 'hide_replies', 
					'laconica__hide_replies', _t('Hide @replies') );
				$laconica_show->options = array( '1' => _t('Yes') , '0' => _t('No') );
				$laconica_show = $ui->append( 'select', 'linkify_urls', 
					'laconica__linkify_urls', _t('Linkify URLs') );
				$laconica_show->options = array( '1' => _t('Yes') , '2' => _t('Yes - and shorten link text to domains only'),
					 '0' => _t('No') );
				$laconica_cache_time = $ui->append( 'text', 'cache', 'laconica__cache', 
					_t('Cache expiry in seconds:') );
				// $ui->on_success( array( $this, 'updated_config' ) );
				$ui->append( 'submit', 'save', _t('Save') );
				$ui->out();
			
			}
		}
	}

	/**
	 * Returns true if plugin config form values defined in action_plugin_ui should be stored in options by Habari
	 * @return bool True if options should be stored
	 **/
	public function updated_config( $ui )
	{
		return true;
	}

	/**
	 * Add the Laconica user options to the list of valid field names.
	 * This causes adminhandler to recognize the laconica fields and
	 * to set the userinfo record appropriately
	**/
	public function filter_adminhandler_post_user_fields( $fields )
	{
		$fields['laconica_name'] = 'laconica_name';
		$fields['laconica_pass'] = 'laconica_pass';
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
			if ( Options::get( 'laconica__post_status' ) == '1' ) {
				$user = User::get_by_id( $post->user_id );
				if ( ! empty( $user->info->laconica_name ) && ! empty( $user->info->laconica_pass ) ) {
					$name = $user->info->laconica_name;
					$pw = $user->info->laconica_pass;
				} else {
					$name = Options::get( 'laconica__username' );
					$pw = Options::get( 'laconica__password' );
				}
				$svcurl = 'http://' . Options::get('laconica__svc') . '/index.php?action=api&apiaction=statuses&method=update.xml';
				$this->post_status( $svcurl, Options::get( 'laconica__prepend' ) . $post->title . ' ' . $post->permalink, $name, $pw );
			}
		}
	}

	public function action_post_insert_after( $post )
	{
		return $this->action_post_update_status( $post, -1, $post->status );
	}

	/**
	 * Add last service status, time, and image to the available template vars
	 * @param Theme $theme The theme that will display the template
	 **/
	public function theme_laconica( $theme )
	{
		if ( Options::get( 'laconica__show' ) && Options::get( 'laconica__svc' ) && Options::get( 'laconica__username' ) != '' ) {
			$laconica_url = 'http://' . Options::get( 'laconica__svc' ) . '/index.php?action=api&apiaction=statuses&method=user_timeline&argument=' . urlencode( Options::get( 'laconica__username' ) ) . '.xml';
			
			/* 
			 * Only need to get a single notice if @replies are hidden.
			 * (Otherwise, rely on the maximum returned and hope one is a non-reply.)
			 */
			if ( Options::get( 'laconica__hide_replies' ) == '0' ) {
				$laconica_url .= '&count=1';
			}

			if ( Cache::has( 'laconica_notice_text' ) && Cache::has( 'laconica_notice_time' ) && Cache::has( 'notice_image_url' ) ) {
				$theme->notice_text = Cache::get( 'laconica_notice_text' );
				$theme->notice_time = Cache::get( 'laconica_notice_time' );
				$theme->notice_image_url = Cache::get( 'notice_image_url' );
			}
			else {
				try {
					$response = RemoteRequest::get_contents( $laconica_url );
					$xml = @new SimpleXMLElement( $response );
					// Check we've got a load of statuses returned
					if ( $xml->getName() === 'statuses' ) {
						foreach ( $xml->status as $status ) {
							if ( ( Options::get( 'laconica__hide_replies' ) == '0' ) || ( strpos( $status->text, '@' ) !== 0) ) {
								$theme->notice_text = (string) $status->text;
								$theme->notice_time = (string) $status->created_at;
								$theme->notice_image_url = (string) $status->user->profile_image_url;
								break;
							}
							else {
							// it's a @. Keep going.
							}
						}
						if ( !isset( $theme->notice_text ) ) {							
							$theme->notice_text = 'No non-replies replies available from service.';
							$theme->notice_time = '';
							$theme->notice_image_url = '';
						}
					}
					// You can get error as a root element if service is in maintance mode.
					else if ( $xml->getName() === 'error' ) {
						$theme->notice_text = (string) $xml;
						$theme->notice_time = '';
						$theme->notice_image_url = '';
					}
					// Should not be reached.
					else {
						$theme->notice_text = 'Received unexpected XML from service.';
						$theme->notice_time = '';
						$theme->notice_image_url = '';
					}
					// Cache (even errors) to avoid hitting rate limit.
					Cache::set( 'laconica_notice_text', $theme->notice_text, Options::get( 'laconica__cache' ) );
					Cache::set( 'laconica_notice_time', $theme->notice_time, Options::get( 'laconica__cache' ) );
					Cache::set( 'notice_image_url', $theme->notice_image_url, Options::get( 'laconica__cache' ) );
				}
				catch ( Exception $e ) {
					$theme->notice_text = 'Unable to contact service.';
					$theme->notice_time = '';
					$theme->notice_image_url = '';
				}
			}
		}
		else {
			$theme->notice_text = _t('Please set your username in the <a href="%s">Laconica plugin config</a>', array( URL::get( 'admin' , 
			'page=plugins&configure=' . $this->plugin_id . '&configaction=Configure' ) . '#plugin_' . 
			$this->plugin_id ) , 'laconica' );			
			$theme->notice_time = '';
			$theme->notice_image_url = '';
		}
		if ( Options::get( 'laconica__linkify_urls' ) != FALSE ) {
			$theme->notice_text = 
preg_replace("/(http\:\/\/)?(www.)?([a-z]+\.[a-z]{2,5})([a-z\/]*)/", "<a href=\"http://$2$3$4\">" . 
			( Options::get( 'laconica__linkify_urls' ) == 2 ? "$3" : "$2$3$4" ) . "</a>", $theme->notice_text);

}
		return $theme->fetch( 'laconica.tpl' );
	}

	/**
	 * On plugin init, add the template included with this plugin to the available templates in the theme
	 */
	public function action_init()
	{
		$this->add_template('laconica.tpl', dirname(__FILE__) . '/laconica.tpl.php');
	}
}

?>
