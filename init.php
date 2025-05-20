<?php
/**
 * Helpdesk Support Ticket System for WooCommerce - STSWooCommerceInit Class
 *
 * @version 2.0.0
 *
 * @author  WPFactory
 */

defined( 'ABSPATH' ) || exit;

class STSWooCommerceInit {

	public $tab;
	public $activeTab;
	public $hideClosed            = '';
	public $textforTicketSave     ='Saved your ticket successfully! We will come back to you soon';
	public $textforResponseSave   ='Saved your response successfully! We will come back to you soon';
	public $renameAccountTabLink  = '';
	public $renameOrderButtonLink = '';
	public $mailToADmin           = 'mailToADmin';
	public $AdminEmailAddress     = 'AdminEmailAddress';
	public $mailToUser            = 'mailToUser';
	public $mailToCustomer        = '';
	public $mailIt_contentToCust  = '';
	public $mailIt_subjectToCust  = '';

	public $mailIt_allowed_html = array(
			'a' => array(
				'style' => array(),
				'href' => array(),
				'title' => array(),
				'class' => array(),
				'id'=>array()
			),
			'i' => array('style' => array(),'class' => array(),'id'=>array() ),
			'br' => array('style' => array(),'class' => array(),'id'=>array() ),
			'em' => array('style' => array(),'class' => array(),'id'=>array() ),
			'strong' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h1' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h2' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h3' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h4' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h5' => array('style' => array(),'class' => array(),'id'=>array() ),
			'h6' => array('style' => array(),'class' => array(),'id'=>array() ),
			'img' => array('style' => array(),'class' => array(),'id'=>array() ),
			'p' => array('style' => array(),'class' => array(),'id'=>array() ),
			'div' => array('style' => array(),'class' => array(),'id'=>array() ),
			'section' => array('style' => array(),'class' => array(),'id'=>array() ),
			'ul' => array('style' => array(),'class' => array(),'id'=>array() ),
			'li' => array('style' => array(),'class' => array(),'id'=>array() ),
			'ol' => array('style' => array(),'class' => array(),'id'=>array() ),
			'video' => array('style' => array(),'class' => array(),'id'=>array() ),
			'blockquote' => array('style' => array(),'class' => array(),'id'=>array() ),
			'figure' => array('style' => array(),'class' => array(),'id'=>array() ),
			'figcaption' => array('style' => array(),'class' => array(),'id'=>array() ),
			'style' => array(),
			'iframe' => array(
				'height' => array(),
				'src' => array(),
				'width' => array(),
				'allowfullscreen' => array(),
				'style' => array(),
				'class' => array(),
				'id'=>array()
			),
			'img' => array(
				'alt' => array(),
				'src' => array(),
				'title' => array(),
				'style' => array(),
				'class' => array(),
				'id'=>array()
			),
			'video' => array(
				'width' => array(),
				'height' => array(),
				'controls'=>array(),
				'class' => array(),
				'id'=>array()
			),
			'source' => array(
				'src' => array(),
				'type' => array(),
				'class' => array(),
				'id'=>array()
			),
		);

	public function adminHeader(){
		?>
			<h1 style='display:flex;align-items:center;' ><a target='_blank' href='<?php print esc_url( $this->proUrl ); ?>'>

			<img   style='width:170px;padding-right:30px' src='<?php echo plugins_url( 'images/extendwp.png', __FILE__ ); ?>' alt='<?php esc_html_e( 'Get more plugins by extendWP','support-ticket-system-for-woocommerce' ); ?> title='<?php esc_html_e( 'Get more plugins by extendWP','support-ticket-system-for-woocommerce' ); ?> />
				</a> <span style='color:#2271b1;'><?php print esc_html( $this->name ); ?></span></h1>

		<?php

	}

	public function adminSettings(){
			esc_html( $this->adminTabs() );	// add tabs for tickets screen

			?>
			<p><b><?php esc_html_e("Use Ticket System in any page with the shortcode [stsw_user_tickets]",'support-ticket-system-for-woocommerce' ); ?></b>
			<br/><i><?php esc_html_e("Important Note: If you don't see the tickets dashboard in my account page, flush your permalinks from WP Backend",'support-ticket-system-for-woocommerce' ); ?></i></p>
			<?php

			if( isset( $_GET['tab'] ) ){
				$this->activeTab = esc_html( $_GET['tab'] ) ;
				if( isset( $_GET['action'] ) ) $this->activeAction = esc_html( $_GET['action'] );
			}else $this->activeTab = 'general';

			if( $this->activeTab == 'settings') {
				?>
				<form method="post" id='<?php print esc_html( $this->plugin ); ?>Form' >

					<div class='result'><?php esc_html( $this->adminProcessSettings() ); ?> </div>

					<div id="tabs">
						<ul>
							<li><a href="#general"><?php esc_html_e( "General",'support-ticket-system-for-woocommerce' ) ; ?></a></li>
							<li><a href="#notifications"><?php esc_html_e( "Notifications",'support-ticket-system-for-woocommerce' ) ; ?></a></li>
						</ul>
						<div id="general">

							<?php
							settings_fields( esc_html( $this->plugin ).'general-options' );
							do_settings_sections( esc_html( $this->plugin ).'general-options' );

							?>

						</div>
						<div id="notifications">
							<h4>
							<span class='proVersion' >
								<?php esc_html_e( "Available placeholders for your Emails:",'support-ticket-system-for-woocommerce' ) ; ?>
								<i> {ticketId},{responseId},{title},{content},{toEmail},{toFirstName},{toLastName} </i>
								<?php esc_html_e( "in PRO Version",'support-ticket-system-for-woocommerce' ) ; ?>
								</span>
							</h4>
							<?php
							settings_fields( esc_html( $this->plugin ).'notifications-options' );
							do_settings_sections( esc_html( $this->plugin ).'notifications-options' );
							?>
					</div>
					<?php
						wp_nonce_field( esc_html( $this->plugin ) );
					?>
					<p id='save_changes' ><?php esc_html( submit_button() ); ?></p>

				</form>
				<?php
			}elseif( $this->activeTab == 'general' ){
				//display dashboard
				$tickets = new STSWooCommerceInc();
				$tickets->ticketsDashboard();

			}else{
				$tickets = new STSWooCommerceInc();
				$tickets->ticketsDashboard();
			}
	}

	/**
	 * adminTabs.
	 *
	 * @version 2.0.0
	 */
	public function adminTabs(){ // the tabs in tickets screen
			$this->tab = array( 'general'=>'Dashboard','all'=>'Tickets','settings'=>'Settings','priorities'=>'Priorities','subject'=>'Subject','more'=>"Go PRO");
			if( isset( $_GET['tab'] ) ){
				$this->activeTab = esc_html( $_GET['tab'] );

			}elseif( isset( $_GET['post_type'] ) && $_GET['post_type']=='stsw_tickets' ){

				$this->activeTab = 'all' ;

			}else $this->activeTab = 'general';

			echo '<h2 class="nav-tab-wrapper" >';
			foreach( $this->tab as $tab => $name ){
				$class = ( $tab == $this->activeTab ) ? ' nav-tab-active' : '';
				if($tab == 'all'){
					echo "<a class='nav-tab".esc_attr( $class )." contant' href='edit.php?post_type=stsw_tickets&tab=".esc_attr( $tab )."'>".esc_attr($name)."</a>";
				}elseif($tab == 'priorities'){
					echo "<a class='nav-tab".esc_attr( $class )." proVersion disabled' href='#'>".esc_attr( $name )."</a>";
				}elseif($tab == 'subject'){
					echo "<a class='nav-tab".esc_attr( $class )." proVersion disabled' href='#'>".esc_attr( $name )."</a>";
				}elseif($tab == 'more'){
					echo "<a class='nav-tab".esc_attr( $class )." proVersion' href='#'>".esc_attr( $name )."</a>";
				}
				else{
					echo "<a class='nav-tab".esc_attr( $class )." ' href='?page=support-ticket-system-woocommerce&tab=".esc_attr( $tab )."'>". wp_kses($name, $this->mailIt_allowed_html ) ."</a>";
				}

			}
			echo '</h2>';
	}

	public function adminFooter(){ ?>
		<hr>
		<a target='_blank' class='web_logo' href='https://extend-wp.com/wordpress-premium-plugins/'>
			<img  src='<?php echo esc_url( plugins_url( 'images/extendwp.png', __FILE__ ) ); ?>' alt='<?php esc_html_e("Get more plugins by extendWP",$this->plugin);?>' title='<?php esc_html_e("Get more plugins by extendWP",'support-ticket-system-for-woocommerce' );?>' />
		</a>
		<?php

	}

	public function adminPanels(){
		// add settings for ticket system
		add_settings_section( esc_html( $this->plugin )."general", "", null, esc_html( $this->plugin )."general-options");
		add_settings_section( esc_html( $this->plugin )."notifications", "", null, esc_html( $this->plugin )."notifications-options");

		add_settings_field('renameAccountTabLink',esc_html__( "Rename Tab Link in My Account Page for Tickets",'support-ticket-system-for-woocommerce' ), array($this, 'renameAccountTabLink'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general",  esc_html( $this->plugin ). esc_html( $this->renameAccountTabLink ) );

		add_settings_field('enableAttachments',"<span class='proVersion'>".esc_html__( "Enable Attachments to Ticket",'support-ticket-system-for-woocommerce' )."span>", array($this, 'enableAttachments'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting(esc_html( $this->plugin )."general", '' );

		add_settings_field('allowedExtensions',"<span class='proVersion'>".esc_html__( "Allowed Attachments File Types",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'allowedExtensions'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general", '' );

		add_settings_field('allowedSize',"<span class='proVersion'>".esc_html__( "Max File Size ",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'allowedSize'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general", '' );

		add_settings_field('allowedAttachNum',"<span class='proVersion'>".esc_html__( "Allowed Number of Attachments ",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'allowedAttachNum'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general", '' );

		add_settings_field('enablePriority',"<span class='proVersion'>".esc_html__( "Enable Priority Field to Ticket Creation",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'enablePriority'),  esc_html( $this->plugin )."general-options",esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general",  '' );

		add_settings_field('renameOrderButtonLink',esc_html__( "Rename Button on Orders Table in My Account Page for Tickets",'support-ticket-system-for-woocommerce' ), array($this, 'renameOrderButtonLink'), esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting(esc_html( $this->plugin )."general", esc_html( $this->plugin ).esc_html( $this->renameOrderButtonLink ) );

		add_settings_field('hideClosed',esc_html__( "Show only Open in Frontend",'support-ticket-system-for-woocommerce' ), array($this, 'hideClosed'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin )."general" , esc_html( $this->plugin ).esc_html( $this->hideClosed ) ) ;

		add_settings_field('assignToRole',"<span class='proVersion'>".esc_html__( "Default User Role for Ticket Assignment",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'assignToRole'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting(esc_html(  $this->plugin )."general",'' );

		add_settings_field('assignToUser',"<span class='proVersion'>".esc_html__( "Default Assignee",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'assignToUser'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting(esc_html(  $this->plugin )."general",  '' );

		add_settings_field('textforTicketSave',esc_html__( "Text to display once Ticket is saved",'support-ticket-system-for-woocommerce' ), array($this, 'textforTicketSave'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin  )."general", esc_html( $this->plugin ).esc_html( $this->textforTicketSave ) );

		add_settings_field('textforResponseSave',esc_html__( "Text to display once Response is saved",'support-ticket-system-for-woocommerce' ), array($this, 'textforResponseSave'),  esc_html( $this->plugin )."general-options", esc_html( $this->plugin )."general");
		register_setting( esc_html( $this->plugin  )."general", esc_html( $this->plugin ).esc_html( $this->textforResponseSave ) );

		add_settings_field('mailToAssignee',"<span class='proVersion'>".esc_html__( "Notify Assignee by Email",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'mailToAssignee'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", '' );

		add_settings_field('mailToADmin',esc_html__( "Notify Admin by Email",'support-ticket-system-for-woocommerce' ), array($this, 'mailToADmin'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", esc_html( $this->plugin ).esc_html( $this->mailToADmin ) );

		add_settings_field('AdminEmailAddress',esc_html__( "Admin Address",'support-ticket-system-for-woocommerce' ), array($this, 'AdminEmailAddress'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", esc_html( $this->plugin ).esc_html( $this->AdminEmailAddress ) );

		add_settings_field('mailToCustomer',esc_html__( "Notify Customer by Email",'support-ticket-system-for-woocommerce' ), array($this, 'mailToCustomer'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", esc_html( $this->plugin ).esc_html( $this->mailToCustomer ) );

		add_settings_field('mailIt_subjectToAs',"<span class='proVersion'>".esc_html__( "Email Subject sent to Assignee",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'mailIt_subjectToAs'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", '' );

		add_settings_field('mailIt_contentToAs',"<span class='proVersion'>".esc_html__( "Email Content sent to Assignee",'support-ticket-system-for-woocommerce' )."</span>", array($this, 'mailIt_contentToAs'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", '');

		add_settings_field('mailIt_subjectToCust',esc_html__( "Email Subject sent to Customer",'support-ticket-system-for-woocommerce' ), array($this, 'mailIt_subjectToCust'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");register_setting( esc_html( $this->plugin )."notifications", esc_html( $this->plugin ). esc_html( $this->mailIt_subjectToCust ) );

		add_settings_field('mailIt_contentToCust',esc_html__( "Email Content sent to Customer",'support-ticket-system-for-woocommerce' ), array($this, 'mailIt_contentToCust'),  esc_html( $this->plugin )."notifications-options", esc_html( $this->plugin )."notifications");
		register_setting( esc_html( $this->plugin )."notifications", esc_html( $this->plugin ). esc_html( $this->mailIt_contentToCust ) );

	}

	public function assignToRole(){
		?>

			<select disabled >

			   <?php esc_html( wp_dropdown_roles() ); ?>
			</select>
		<?php

	}
	public function assignToUser(){

		$users_query = new WP_User_Query( array(
			'fields' => 'all_with_meta',
			'orderby' => 'display_name'
		) );
		$results = $users_query->get_results();
		?>
			<select disabled >
				<?php

					foreach($results as $res){
						$user_info = get_userdata( (int)$res->ID );
						$userid = (int)$user_info->ID;
						$name = esc_html( $user_info->first_name )." ". esc_html( $user_info->last_name );
						echo "<option value=''>".esc_attr( $user_info->first_name )." ".esc_attr( $user_info->last_name )."</option>";
					}

				?>
			</select>
		<?php

	}

	public function renameAccountTabLink(){
		if( isset($_REQUEST[ esc_html( $this->plugin ).'renameAccountTabLink'] ) ){
			$this->renameAccountTabLink =  sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'renameAccountTabLink']);
		}else $this->renameAccountTabLink = get_option( esc_html( $this->plugin ).'renameAccountTabLink' );
		?>
			<input type="text" name="<?php esc_attr( print esc_html( $this->plugin ).'renameAccountTabLink' ); ?>" id="<?php print esc_attr( $this->plugin.'renameAccountTabLink' ); ?>" value='<?php if($this->renameAccountTabLink !='')print esc_attr( $this->renameAccountTabLink ); ?>' />
		<?php
	}

	public function renameOrderButtonLink(){
		if( isset($_REQUEST[esc_html( $this->plugin ).'renameOrderButtonLink'] ) ){
			$this->renameOrderButtonLink =  sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'renameOrderButtonLink']);
		}else $this->renameOrderButtonLink = get_option( esc_html( $this->plugin ).'renameOrderButtonLink' );
		?>
			<input type="text" name="<?php esc_attr( print esc_html( $this->plugin ).'renameOrderButtonLink' ); ?>" id="<?php print esc_attr( $this->plugin.'renameOrderButtonLink' ); ?>" value='<?php if($this->renameOrderButtonLink !='')print esc_attr( $this->renameOrderButtonLink ); ?>' />
		<?php
	}

	public function hideClosed(){
		if( isset($_REQUEST[$this->plugin.'hideClosed'] ) ){
			$this->hideClosed =  sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'hideClosed']);
		}else $this->hideClosed = get_option( esc_html( $this->plugin ).'hideClosed' );
		?>
			<input type="checkbox" name="<?php esc_attr( print $this->plugin.'hideClosed' ); ?>" id="<?php print esc_attr( $this->plugin.'hideClosed' ); ?>" value='1' <?php if($this->hideClosed === '1') print "checked"; ?> />
		<?php
	}

	public function enableAttachments(){

		?>
			<input type="checkbox" disabled />
		<?php
	}

	public function enablePriority(){
		?>
			<input type="checkbox" disabled />
		<?php
	}

	public function mailToAssignee(){
		?>
			<input <input type="checkbox" class='proVersion' disabled />
		<?php
	}
	public function mailToADmin(){
		if( isset($_REQUEST[ esc_html( $this->plugin ).'mailToADmin'] ) ){
			$this->mailToADmin =  sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'mailToADmin']);
		}else $this->mailToADmin = get_option( esc_html( $this->plugin ).'mailToADmin' );
		?>
			<input type="checkbox" name="<?php print esc_attr( $this->plugin.'mailToADmin' ); ?>" id="<?php print esc_attr( $this->plugin.'mailToADmin' ); ?>" value='1' <?php if($this->mailToADmin === '1') print "checked"; ?> />
		<?php
	}

	/**
	 * AdminEmailAddress.
	 *
	 * @version 2.0.0
	 */
	public function AdminEmailAddress(){
		if( isset($_REQUEST[$this->plugin.'AdminEmailAddress'] ) ){
			$this->AdminEmailAddress =  sanitize_email( $_REQUEST[ esc_html( $this->plugin ).'AdminEmailAddress']);
		}elseif( get_option( esc_html( $this->plugin ).'AdminEmailAddress' )!=''){
			$this->AdminEmailAddress = get_option( esc_html( $this->plugin ).'AdminEmailAddress' );
		}else $this->AdminEmailAddress = sanitize_email( get_bloginfo('admin_email') );
		?>
			<input type="text"  name="<?php print esc_attr( esc_html( $this->plugin ).'AdminEmailAddress' ); ?>" id="<?php print esc_attr( $this->plugin.'AdminEmailAddress' ); ?>" placeholder='<?php print esc_html__( 'Admin Email Address', 'support-ticket-system-for-woocommerce' ); ?>' value="<?php echo  esc_attr($this->AdminEmailAddress); ?>"  />
		<?php
	}

	public function mailToCustomer(){
		if( isset($_REQUEST[ esc_html( $this->plugin ).'mailToCustomer'] ) ){
			$this->mailToCustomer =  sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'mailToCustomer']);
		}else $this->mailToCustomer = get_option( esc_html( $this->plugin ).'mailToCustomer' );
		?>
			<input type="checkbox" name="<?php print esc_attr( $this->plugin .'mailToCustomer' ); ?>" id="<?php print esc_attr( $this->plugin.'mailToCustomer' ); ?>" value='1' <?php if($this->mailToCustomer === '1') print "checked"; ?> />
		<?php
	}

	public function mailIt_subjectToAs(){
		?>
			<input class='proVersion' disabled />
		<?php
	}

	public function mailIt_contentToAs(){

		?>
		<textarea class='proVersion' disabled placeholder='<?php print esc_html__( "Pro Vesion Only - html enabled",'support-ticket-system-for-woocommerce' ) ; ?>' ></textarea>
		<?php
	}

	public function textforTicketSave(){

		if( isset($_REQUEST[ esc_html( $this->plugin ).'textforTicketSave'] ) ){
			$this->textforTicketSave =  wp_kses($_REQUEST[ esc_html( $this->plugin ).'textforTicketSave'], $this->mailIt_allowed_html);
		}elseif(!empty(get_option( esc_html( $this->plugin ).'textforTicketSave' )) ){
			$this->textforTicketSave = get_option( esc_html( $this->plugin ).'textforTicketSave' );
		}
		echo wp_editor( apply_filters($this->textforTicketSave,$this->textforTicketSave), esc_html( $this->plugin ).'textforTicketSave', array("wpautop" => true, 'textarea_name' => esc_html( $this->plugin ).'textforTicketSave', 'textarea_rows' => '5','editor_height' => 125)  );
	}
	public function textforResponseSave(){

		if( isset($_REQUEST[ esc_html( $this->plugin ).'textforResponseSave'] ) ){
			$this->textforResponseSave =  wp_kses($_REQUEST[ esc_html( $this->plugin ).'textforResponseSave'], $this->mailIt_allowed_html);
		}elseif(!empty(get_option( esc_html( $this->plugin ).'textforResponseSave' )) ){
			$this->textforResponseSave = get_option( esc_html( $this->plugin ).'textforResponseSave' );
		}
		echo wp_editor( apply_filters($this->textforResponseSave,$this->textforResponseSave), esc_html( $this->plugin ).'textforResponseSave', array("wpautop" => true, 'textarea_name' => esc_html( $this->plugin ).'textforResponseSave', 'textarea_rows' => '5','editor_height' => 125)  );
	}

	/**
	 * mailIt_subjectToCust.
	 *
	 * @version 2.0.0
	 */
	public function mailIt_subjectToCust(){
		if( isset($_REQUEST[ esc_html( $this->plugin ).'mailIt_subjectToCust'] ) ){
			$this->mailIt_subjectToCust =  sanitize_text_field( $_REQUEST[$this->plugin.'mailIt_subjectToCust']);
		}else $this->mailIt_subjectToCust = get_option( esc_html( $this->plugin ).'mailIt_subjectToCust' );
		?>
			<input type="text"  name="<?php print esc_attr( $this->plugin.'mailIt_subjectToCust' ); ?>" id="<?php print esc_attr( $this->plugin.'mailIt_subjectToCust' ); ?>" placeholder='<?php print esc_html__( 'Mail Subject', 'support-ticket-system-for-woocommerce' ); ?>' value="<?php echo  esc_attr($this->mailIt_subjectToCust); ?>"  />
		<?php
	}

	public function allowedExtensions(){

		?>
			<input disabled class='proVersion' placeholder='<?php print esc_html__('Extensions allowed - Pro Version','support-ticket-system-for-woocommerce' ) ; ?>'  />
		<?php
	}
	public function allowedSize(){
		?>
			<input type="text" disabled class='proVersion' placeholder='<?php print esc_html__('Files size - Pro Version','support-ticket-system-for-woocommerce' ); ?>'   />
		<?php
	}
	public function allowedAttachNum(){
		?>
			<input type="number" disabled class='proVersion'  placeholder='<?php print esc_html__('Allowed N.Files  - Pro Version ','support-ticket-system-for-woocommerce' ); ?>'  />
		<?php
	}

	public function mailIt_contentToCust(){

		if( isset($_REQUEST[ esc_html( $this->plugin ).'mailIt_contentToCust'] ) ){
			$this->mailIt_contentToCust =  wp_kses($_REQUEST[ esc_html( $this->plugin ).'mailIt_contentToCust'], $this->mailIt_allowed_html);
		}else{
			$this->mailIt_contentToCust = get_option( esc_html( $this->plugin ).'mailIt_contentToCust' );
		}
		echo wp_editor( apply_filters($this->mailIt_contentToCust,$this->mailIt_contentToCust), esc_html( $this->plugin ).'mailIt_contentToCust', array("wpautop" => true, 'textarea_name' => esc_html( $this->plugin ).'mailIt_contentToCust', 'textarea_rows' => '5','editor_height' => 225)  );
	}

	public function adminProcessSettings(){

		if($_SERVER['REQUEST_METHOD'] == 'POST' && current_user_can('administrator') ){

			check_admin_referer( esc_html( $this->plugin ) );
			check_ajax_referer( esc_html( $this->plugin ) );

			if( isset($_REQUEST[ esc_html( $this->plugin ).'mailToADmin']) && sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'mailToADmin']) ==='1' ){
				update_option(esc_html( $this->plugin ).'mailToADmin',sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'mailToADmin']));
			}else update_option( esc_html( $this->plugin ).'mailToADmin','');

			if( isset($_REQUEST[ esc_html( $this->plugin ).'AdminEmailAddress']) ){
				update_option( esc_html( $this->plugin ).'AdminEmailAddress',sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'AdminEmailAddress']));
			}

			if( isset($_REQUEST[ esc_html( $this->plugin ).'mailToCustomer']) && sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'mailToCustomer']) ==='1' ){
				update_option( esc_html( $this->plugin ).'mailToCustomer',sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'mailToCustomer']));
			}else update_option( esc_html( $this->plugin ).'mailToCustomer','');

			if( isset($_REQUEST[ esc_html( $this->plugin ).'hideClosed']) && sanitize_text_field($_REQUEST[ esc_html( $this->plugin ).'hideClosed']) ==='1' ){
				update_option(esc_html( $this->plugin ).'hideClosed',sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'hideClosed']));
			}else update_option( esc_html( $this->plugin ).'hideClosed','' );

			if( isset($_REQUEST[ esc_html( $this->plugin ).'mailIt_contentToCust']) ){
			   $mailIt_contentToCust =  wp_kses($_REQUEST[$this->plugin.'mailIt_contentToCust'], $this->mailIt_allowed_html );
			   update_option( esc_html( $this->plugin ).'mailIt_contentToCust',$mailIt_contentToCust);
			}

			if( isset($_REQUEST[ esc_html( $this->plugin ).'textforTicketSave']) ){
			   $textforTicketSave =  wp_kses($_REQUEST[$this->plugin.'textforTicketSave'], $this->mailIt_allowed_html );
			   update_option( esc_html( $this->plugin ).'textforTicketSave',$textforTicketSave);
			}

			if( isset($_REQUEST[ esc_html( $this->plugin ).'textforResponseSave']) ){
			   $textforResponseSave =  wp_kses($_REQUEST[$this->plugin.'textforResponseSave'], $this->mailIt_allowed_html );
			   update_option( esc_html( $this->plugin ).'textforResponseSave',$textforResponseSave);
			}

			if( isset($_REQUEST[ esc_html( $this->plugin ).'mailIt_subjectToCust']) ){
			   update_option( esc_html( $this->plugin ).'mailIt_subjectToCust',sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'mailIt_subjectToCust']));
			}

			if( isset($_REQUEST[ esc_html( $this->plugin ).'renameOrderButtonLink']) ){
			   $renameOrderButtonLink =  update_option( esc_html( $this->plugin ).'renameOrderButtonLink',sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'renameOrderButtonLink']));
			}
			if( isset($_REQUEST[$this->plugin.'renameAccountTabLink']) ){
			   $renameAccountTabLink =  update_option( esc_html( $this->plugin ).'renameAccountTabLink',sanitize_text_field($_REQUEST[esc_html( $this->plugin ).'renameAccountTabLink']));
			}
		}
	}

}
