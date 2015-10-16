<?php
/*
FILE : class.init.php
DESC : Initilize MyCred Add on and hooks
*/

if ( !defined( 'ABSPATH' ) ) exit;

class wplms_points_init {
	
	public $version;
	public $subscription_duration_parameter = 86400;

	function __construct(){
		
		//add_filter( 'mycred_label', 'mycred_pro_relable_mycred' );
		add_filter('mycred_setup_addons',array($this,'wplms_mycred_setup_addons'));
		add_filter('wplms_course_product_metabox',array($this,'wplms_mycred_custom_metabox'));
		
		add_filter('wplms_course_credits_array',array($this,'wplms_course_credits_array'),10,2);
		add_action('wplms_header_top_login',array($this,'wplms_mycred_show_points'));
		add_filter('wplms_course_product_id',array($this,'wplms_mycred_take_this_course_label'),9);
		add_action('wplms_course_before_front_main',array($this,'wplms_error_message_handle'));
		add_action('wp_ajax_use_mycred_points',array($this,'use_mycred_points'));
		add_action('wp_print_styles',array($this,'add_styles'));
		add_action('wplms_front_end_pricing_content',array($this,'wplms_front_end_pricing'),10,1);
		add_Action('wplms_course_pricing_save',array($this,'save_pricing'),10,2);
		add_action('lms_general_settings',array($this,'add_buy_points_setting'));
		//add_action('wp_ajax_retake_inquiz',array($this,'custom_hook_quiz_retake'));
	}

	function mycred_pro_relable_mycred() {
		return __('Points','wplms-mycred');
	}
	function add_styles(){
		wp_enqueue_style('wplms_mycred',VIBE_PLUGIN_URL.'/wplms-mycred-addon/assets/wplms-mycred-addon.css',true);
		wp_enqueue_script('wplms_mycred',VIBE_PLUGIN_URL.'/wplms-mycred-addon/assets/wplms-mycred-addon.js',true);
	}
	function wplms_mycred_take_this_course_label($x){
		global $post;
		$points_required = get_post_meta($post->ID,'vibe_mycred_points',true);
		if(isset($points_required) && is_numeric($points_required)){
			$user_id = get_current_user_id();
			$mycred = mycred();
			$balance = $mycred->get_users_cred( $user_id );
			if($points_required <= $balance){
				echo '<script>jQuery(document).ready(function($){

					$( "body" ).delegate( ".course_button[href=\'#hasmycredpoints\']", "click", function(event){
						event.preventDefault();
						
						if($(this).hasClass("loader"))
							return;

						$(this).addClass("loader");
						$.ajax({
		                    type: "POST",
		                    url: ajaxurl,
		                    data: { action: "use_mycred_points", 
		                            security: $("#wplms_mycred_security").val(),
		                            id: '.$post->ID.'
		                          },
		                    cache: false,
		                    success: function (html) {
		                        $(this).removeClass("loader");
		                        $(this).html(html);
		                        setTimeout(function(){location.reload();}, 2000);
		                    }
		            });
						return false;
					});
				});</script>
				'.wp_nonce_field('security'.$user_id,'wplms_mycred_security').'
				';
				return '#hasmycredpoints';
			}else{	
				if(is_numeric($x)){
					if ( FALSE === get_post_status( $x ) ) {
					  return '?error=insufficient';
					} else {
					  return $x;
					}
					return $x;
				}else{
					return '?error=insufficient';
				}
			}
		}else{
			return $x;
		}
	}
	function wplms_error_message_handle(){
	  global $post;
	  if(isset($_REQUEST['error'])){ 
	  switch($_REQUEST['error']){
	    case 'insufficient':
	      echo '<div id="message" class="notice"><p>'.__('Purchase points to take this course','vibe').' : <a href="'.$this->wplms_get_mycred_purchase_points().'">'.__('Add Points','wplms-mycred').'</a></p></div>';
	    break;
	    }
	  }
	}
	function wplms_mycred_setup_addons($installed){
		if ( isset( $_GET['addon_action'] ) && isset( $_GET['addon_id'] ) && $_GET['addon_id'] == 'wplms' && $_GET['addon_action'] == 'activate'){
			$mycred_addons=get_option('mycred_pref_addons');
			
			if(!isset($mycred_addons['installed']['wplms']))
				delete_option('mycred_pref_addons');
		}
		// Transfer Add-on
		$installed['wplms'] = array(
			'name'        => 'WPLMS',
			'description' => __( 'MyCred points options for WPLMS Learning Management', 'wplms-mycred' ),
			'addon_url'   => 'http://github.com/vibethemes/wplms-mycred-addon',
			'version'     => '1.0',
			'author'      => 'VibeThemes',
			'author_url'  => 'http://www.vibethemes.com',
			'path'        => realpath(dirname(__FILE__)). 'myCRED-addon-wplms.php'
		);

		return $installed;
	}


	function wplms_mycred_custom_metabox($metabox){
		$prefix = 'vibe_';
		if(function_exists('calculate_duration_time')){
			$parameter = calculate_duration_time($this->subscription_duration_parameter);
		}else
			$parameter = 'DAYS';

		$mycred_metabox = apply_filters('wplms_mycred_metabox',array(  
			$prefix.'mycred_points' => array( // Text Input
				'label'	=> __('MyCred Points','vibe-customtypes'), // <label>
				'desc'	=> __('MyCred Points required to take this course.','vibe-customtypes'),
				'id'	=> $prefix.'mycred_points', // field id and name
				'type'	=> 'number' // type of field
			),
		    $prefix.'mycred_subscription' => array( // Text Input
				'label'	=> __('MyCred Subscription ','vibe-customtypes'), // <label>
				'desc'	=> __('Enable subscription mode for this Course','vibe-customtypes'), // description
				'id'	=> $prefix.'mycred_subscription', // field id and name
				'type'	=> 'showhide', // type of field
		        'options' => array(
		          array('value' => 'H',
		                'label' =>'Hide'),
		          array('value' => 'S',
		                'label' =>'Show'),
		        ),
		                'std'   => 'H'
			),
		     $prefix.'mycred_duration' => array( // Text Input
				'label'	=> __('Subscription Duration','vibe-customtypes'), // <label>
				'desc'	=> __('Duration for Subscription Products (in ','vibe-customtypes').$parameter.')', // description
				'id'	=> $prefix.'mycred_duration', // field id and name
				'type'	=> 'number' // type of field
			),
		));
		
		$metabox = array_merge($metabox,$mycred_metabox);
		return $metabox;
	}

	function wplms_course_credits_array($price_html,$course_id){

		$points=get_post_meta($course_id,'vibe_mycred_points',true);
		if(isset($points) && is_numeric($points)){
			$mycred = mycred();
			$points_html ='<strong>'.$mycred->format_creds($points);
			$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
			if(isset($subscription) && $subscription && $subscription !='H'){
				$duration = get_post_meta($course_id,'vibe_mycred_duration',true);
				$duration = $duration*$this->subscription_duration_parameter;

				if(function_exists('tofriendlytime'))
					$points_html .= ' <span class="subs"> '.__('per','vibe').' '.tofriendlytime($duration).'</span>';
			}
			$key = '#hasmycredpoints';
			$points_html .='</strong>';
			if(is_user_logged_in()){
				$user_id = get_current_user_id();
				$balance = $mycred->get_users_cred( $user_id );
				if($balance < $points){
					$key = '?error=insufficient';
				}
			}else{
				$key = '?error=login';
			}
			$price_html[$key]=$points_html;
		}
		return $price_html;
	}

	function wplms_mycred_show_points(){
		echo '<li><a href="'.$this->wplms_get_mycred_link().'"><strong>'.$this->get_wplms_mycred_points().'</strong></a></li>';
	}

	function wplms_get_mycred_link(){
		$mycred = get_option('mycred_pref_core');

		if(isset($mycred['buddypress']) && isset($mycred['buddypress']['history_url']) && isset($mycred['buddypress']['history_location']) && $mycred['buddypress']['history_location']){
			$link=bp_get_loggedin_user_link().$mycred['buddypress']['history_url'];
		}else{
			$link='#';
		}
		return $link;
	}
	function add_buy_points_setting($settings){
		$settings[] = array(
				'label' => __('Buy Points Link','vibe-customtypes'),
				'name' =>'mycred_buy_points',
				'type' => 'textbox',
				'desc' => __('Buy Points for MyCred, displayed when user points are less than required','wplms-mycred')
			);
		return $settings;
	}
	function wplms_get_mycred_purchase_points(){
		$settings = get_option('lms_settings');
		if(!empty($settings['general']['mycred_buy_points'])){
			$link = $settings['general']['mycred_buy_points'];
		}else
			$link='#';
			
		return $link;
	}
	function get_wplms_mycred_points() {
		if ( is_user_logged_in() && class_exists( 'myCRED_Core' ) ) {
			$mycred = mycred();
			$balance = $mycred->get_users_cred( get_current_user_id() );
			return $mycred->format_creds( $balance );
		}else {
			return $mycred->format_creds(0);
		}
	}

	function use_mycred_points(){
		$user_id=get_current_user_id();
		$course_id = $_POST['id'];
		if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security'.$user_id) ){
		     _e('Security check Failed.','wplms-mycred');
		     die();
		}	

		if(!is_numeric($course_id) || get_post_type($course_id) != 'course'){
			 _e('Incorrect Course','wplms-mycred');
		     die();
		}

		$points = get_post_meta($course_id,'vibe_mycred_points',true);
		$mycred = mycred();
		$balance = $mycred->get_users_cred( $user_id );

		if($balance < $points){
			_e('Not enough balance','wplms-mycred');
		     die();
		}
		$deduct = -1*$points;

		$start_date = get_post_meta($course,'vibe_start_date',true);
		$time=0;
		if(isset($start_date) && $start_date){
			$time=strtotime($start_date);
		}
		if($time<time())
			$time=time();

		$subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
		if(isset($subscription) && $subscription && $subscription !='H'){

			$duration = get_post_meta($course_id,'vibe_mycred_duration',true);

		    $course_duration_parameter = apply_filters('vibe_course_duration_parameter',86400);
		    
		    $start_date = get_post_meta($course,'vibe_start_date',true);
		    $time=0;

		    if(isset($start_date) && $start_date){
		      $time=strtotime($start_date);
		    }
		    if($time<time())
		      $time=time();

		    $t=$time+$duration*$course_duration_parameter;

		    update_post_meta($course_id,$user_id,0);
		  
		    $existing = get_user_meta($user_id,$course_id,true);
		    if(empty($existing)){
		      update_user_meta($user_id,'course_status'.$course_id,1);
		      $students++;
		      update_post_meta($course_id,'vibe_students',$students);
		    }else{
		      update_user_meta($user_id,'course_status'.$course_id,2);
		    }

		    update_user_meta($user_id,$course_id,$t);

		    $group_id=get_post_meta($course_id,'vibe_group',true);
		    if(isset($group_id) && $group_id !='')
		      groups_join_group($group_id, $user_id );  
		    else
		      $group_id ='';

		    
		    do_action('wplms_course_subscribed',$course_id,$user_id,$group_id);
		}else{
			if(empty($duration))
		      $duration=get_post_meta($course_id,'vibe_duration',true);
		    
		    $course_duration_parameter = apply_filters('vibe_course_duration_parameter',86400);
		    
		    $start_date = get_post_meta($course,'vibe_start_date',true);
		    $time=0;

		    if(isset($start_date) && $start_date){
		      $time=strtotime($start_date);
		    }
		    if($time<time())
		      $time=time();

		    $t=$time+$duration*$course_duration_parameter;

		    update_post_meta($course_id,$user_id,0);
		  
		    $existing = get_user_meta($user_id,$course_id,true);
		    if(empty($existing)){
		      update_user_meta($user_id,'course_status'.$course_id,1);
		      $students++;
		      update_post_meta($course_id,'vibe_students',$students);
		    }else{
		      update_user_meta($user_id,'course_status'.$course_id,2);
		    }

		    update_user_meta($user_id,$course_id,$t);

		    $group_id=get_post_meta($course_id,'vibe_group',true);
		    if(isset($group_id) && $group_id !='')
		      groups_join_group($group_id, $user_id );  
		    else
		      $group_id ='';

		    
		    do_action('wplms_course_subscribed',$course_id,$user_id,$group_id);
		}	

		$mycred->update_users_balance( $user_id, $deduct);
		$mycred->add_to_log('take_course',
			$user_id,
			$deduct,
			__('Student subscibed for course','wplms-mycred'),
			$course_id,
			__('Student Subscribed to course , ends on ','wplms-mycred').date("jS F, Y",$expiry));


		$durationtime = $duration.' '.calculate_duration_time($duration_parameter);

		bp_course_record_activity(array(
		      'action' => __('Student subscribed for course ','vibe').get_the_title($course_id),
		      'content' => __('Student ','vibe').bp_core_get_userlink( $user_id ).__(' subscribed for course ','vibe').get_the_title($course_id).__(' for ','vibe').$durationtime,
		      'type' => 'subscribe_course',
		      'item_id' => $course_id,
		      'primary_link'=>get_permalink($course_id),
		      'secondary_item_id'=>$user_id
        ));   
        $instructors[$course]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course_id),$course_id);

        // Commission calculation
        
        if(function_exists('vibe_get_option'))
      	$instructor_commission = vibe_get_option('instructor_commission');
      	if($instructor_commission == 0)
      		return;

      	if(!isset($instructor_commission) || !$instructor_commission)
	      $instructor_commission = 70;

	  	$instructors[$course_id]=apply_filters('wplms_course_instructors',get_post_field('post_author',$course_id),$course_id);

	    $commissions = get_option('instructor_commissions');
	    if(isset($commissions) && is_array($commissions)){
	    	if(is_array($instructors)){
	    		$instructors = array_unique($instructors);
	    		foreach($instructors as $instructor){
	    			if(isset($commissions[$course_id]) && isset($commissions[$course_id][$instructor])){
						$calculated_commission_base = round(($points*$commissions[$course_id][$instructor]/100),2);
					}else{
						$instructor_commission = $instructor_commission/count($instructors);
						$calculated_commission_base = round(($points*$instructor_commission/100),2);
					}
					$mycred->add_to_log('instructor_commission',
					$instructor,
					$calculated_commission_base,
					__('Instructor earned commission','wplms-mycred'),
					$course_id,
					__('Instructor earned commission for student purchasing the course via points ','wplms-mycred')
					);
	    		}
	    	}else{
	    		if(isset($commissions[$course_id][$instructors])){
					$calculated_commission_base = round(($points*$commissions[$course_id][$instructors]/100),2);
				}else{
					$calculated_commission_base = round(($points*$instructor_commission/100),2);
				}
				$mycred->add_to_log('instructor_commission',
					$instructor,
					$calculated_commission_base,
					__('Instructor earned commission','wplms-mycred'),
					$course_id,
					__('Instructor earned commission for student purchasing the course via points ','wplms-mycred')
					);
	    	}
		} // End Commissions_array 


        do_action('wplms_course_mycred_points_puchased',$course_id,$user_id,$points);
        die();
	}	

	function wplms_front_end_pricing($course_id){

		if(isset($course_id) && $course_id){
			$vibe_mycred_points = get_post_meta($course_id,'vibe_mycred_points',true);
			$vibe_mycred_subscription = get_post_meta($course_id,'vibe_mycred_subscription',true);
			$vibe_mycred_duration = get_post_meta($course_id,'vibe_mycred_duration',true);	
		}else{
			$vibe_mycred_points=0;
			$vibe_mycred_subscription = 'H';
			$vibe_mycred_duration = 0;
		}
		


		echo '<li class="course_product" data-help-tag="19">
                <h3>'.__('Set Course Points','vibe').'<span>
                 <input type="text" id="vibe_mycred_points" class="small_box right" value="'.$vibe_mycred_points.'" /></span></h3>
            </li>
            <li class="course_product" >
                <h3>'.__('Subscription Type','vibe').'<span>
                    <div class="switch mycred-subscription">
                            <input type="radio" class="switch-input vibe_mycred_subscription" name="vibe_mycred_subscription" value="H" id="disable_cred_sub" '; checked($vibe_mycred_subscription,'H'); echo '>
                            <label for="disable_cred_sub" class="switch-label switch-label-off">'.__('Full Course','vibe').'</label>
                            <input type="radio" class="switch-input vibe_mycred_subscription" name="vibe_mycred_subscription" value="S" id="enable_cred_sub" '; checked($vibe_mycred_subscription,'S'); echo '>
                            <label for="enable_cred_sub" class="switch-label switch-label-on">'.__('Subscription','vibe').'</label>
                            <span class="switch-selection"></span>
                          </div>
                </span></h3>
            </li>
            <li class="credsubscription course_product" '.(($vibe_mycred_subscription == 'S')?'style="display:block;"':'style="display:none;"').'>
                <h3>'.__('Set Subscription','vibe').'<span>
                <input type="text" id="vibe_mycred_duration" class="small_box" value="'.$vibe_mycred_duration.'" /> '.calculate_duration_time($this->subscription_duration_parameter).'</span></h3>
            </li>
            ';
	}

	function custom_hook_quiz_retake(){
		$quiz_id= $_POST['quiz_id'];
	    if ( !isset($_POST['security']) || !wp_verify_nonce($_POST['security'],'security') || !is_numeric($quiz_id)){
	       die();
	    }

	    $count_retakes = $wpdb->get_var($wpdb->prepare( "
										SELECT count(activity.content) FROM {$table_name} AS activity
										WHERE 	activity.component 	= 'course'
										AND 	activity.type 	= 'retake_quiz'
										AND 	user_id = %d
										AND 	item_id = %d
										ORDER BY date_recorded DESC
									" ,$user_id,$quiz_id));

	    do_action('mycred_quiz_retakes',$quiz_id,$count_retakes);
		die();
	}

	function save_pricing($course_id,$pricing){
		
        if(isset($pricing->vibe_mycred_points) && is_numeric($pricing->vibe_mycred_points)){
            update_post_meta($course_id,'vibe_mycred_points',$pricing->vibe_mycred_points);
            update_post_meta($course_id,'vibe_mycred_subscription',$pricing->vibe_mycred_subscription);
            update_post_meta($course_id,'vibe_mycred_duration',$pricing->vibe_mycred_duration);
            do_action('wplms_course_pricing_mycred_updated',$course_id,$pricing->vibe_mycred_points,$pricing->vibe_mycred_subscription,$pricing->vibe_mycred_duration);
        }else{
        	delete_post_meta($course_id,'vibe_mycred_points');
        }
	}
}

new wplms_points_init();