<?php

class points_awarding_system {

	public $action_hooks=array(
		'course' => array(	
			'started'=>'badgeos_wplms_start_course', // $course_id
			'finished'=>'badgeos_wplms_submit_course',// $course_id
			'score'=>'badgeos_wplms_evaluate_course',//$course_id,$marks,$user_id
			'badges_earned'=>'wplms_badge_earned',//$course_id,$badges,$user_id
			'certificates_earned'=>'wplms_certificate_earned',//$course_id,$certificates,$user_id
			),
		'quiz' => array(
			'started'=>'badgeos_wplms_start_quiz', //$quiz_id
			'finished'=>'badgeos_wplms_submit_quiz', //$quiz_id
			'score'=>'badgeos_wplms_evaluate_quiz',//$quiz_id,$marks,$user_id
			),
		'assignment' => array(
			'started'=>'badgeos_wplms_start_assignment', //$assignment_id,$marks, $user_id
			'finished'=>'badgeos_wplms_submit_assignment', //$assignment_id,$marks, $user_id
			'score'=>'badgeos_wplms_evaluate_assignment',// $assignment_id,$marks, $user_id
			),
		'unit' => array(
			'finished'=>'badgeos_wplms_unit_complete',
			));
	function __construct(){

		$eligibility_option = get_option('wplms_mycred_eligibility');
		if(!isset($eligibility_option) || !is_array($eligibility_option)){
			update_option('wplms_mycred_eligibility',$this->action_hooks); // initialize action hooks
		}

		foreach($this->action_hooks as $key => $hooks){
			foreach($hooks as $hook){
				add_action($hook,array($this,'check_eligibility'),10,3);
			}
		}
		add_action('bp_activity_register_activity_actions',array($this,'wplms_mycred_register_actions'));
	}

	function check_eligibility($id,$info=NULL,$user_id=NULL){

		$current_hook = current_filter();
		if(!$user_id || !is_numeric($user_id))
			$user_id = get_current_user_id();


		$eligibility_option = get_option('wplms_mycred_eligibility');
		foreach($this->action_hooks as $module => $hooks){
			foreach($hooks as $set=>$hook){
				if($current_hook == $hook){
					if(is_array($eligibility_option[$module][$set]))
						$point_criteria_ids=$eligibility_option[$module][$set];
					break;
					break;
				}
			}
		}

		//post ids are points criteria
		if(is_array($point_criteria_ids)){
		foreach($point_criteria_ids as $point_criteria_id){
			$module_id=get_post_meta($point_criteria_id,'wplms_module_id',true);
			if(isset($module_id) && is_numeric($module_id)){
				if($module_id != $id){
					return;
				}
			}
			//echo '#1';
			$expiry = get_post_meta($point_criteria_id,'expires',true);
			if(isset($expiry) && is_date($expiry) && time() > strtotime($expiry))
				return;
			//echo '#2';
			$global_usage = get_post_meta($point_criteria_id,'global',true);
			$total_usage = get_post_meta($point_criteria_id,'total',true);
			if(isset($global_usage) && is_numeric($global_usage) && $total_usage > $global_usage){
				return;
			}
			//echo '#3';
			$user_usage = get_post_meta($point_criteria_id,'user',true);
			$user_specific_usage = get_user_meta($user_id,$point_criteria_id,true);
			if(isset($user_usage) && is_numeric($user_usage) && $user_specific_usage>$user_usage)
				return;

			//echo '#4';
			$operator = get_post_meta($point_criteria_id,'wplms_module_score_operator',true);
			
			if($current_hook == 'badgeos_wplms_unit_complete' || !isset($user_id)){
				$user_id = get_current_user_id();
			}
			if(isset($operator) && $operator)
				$this->$operator($point_criteria_id,$id,$info,$user_id,$module,$total_usage,$user_specific_usage);
		}
	  }
	}


	function started($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(!$user_id || !is_numeric($user_id))
				$user_id = get_current_user_id();
			
		if(is_numeric($value)){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points on starting %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}

	function finished($point_criteria_id,$id,$info,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(!$user_id || !is_numeric($user_id))
				$user_id = get_current_user_id();
			
		if(is_numeric($value)){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points on finishing %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}

	function greater($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
		if(is_numeric($value) && $info > $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points fore getting marks more than %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}

	function lesser($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
		if(is_numeric($value) && $info < $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points fore getting marks less than %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function equal($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
		if(is_numeric($value) && $info == $module_score){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points fore getting marks qual to %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function highest_score($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		global $wpdb;
		$x = $wpdb->get_results($wpdb->prepare("SELECT MAX(meta_value) AS max, meta_key as user FROM {$wpdb->postsmeta} WHERE post_id = %d AND meta_key REGEXP '[0-9]+' AND meta_value REGEXP '[0-9]+'",$id),ARRAY_A);

		if(is_numeric($value) && $user_id ==  $x['user']){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points fore getting highest marks %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function lowest_score($point_criteria_id,$id,$info=NULL,$user_id,$module,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		global $wpdb;
		$x = $wpdb->get_results($wpdb->prepare("SELECT MIN(meta_value) AS max, meta_key as user FROM {$wpdb->postsmeta} WHERE post_id = %d AND meta_key REGEXP '[0-9]+' AND meta_value REGEXP '[0-9]+'",$id),ARRAY_A);

		if(is_numeric($value) && $user_id ==  $x['user']){
			$mycred = mycred();	
			$mycred->update_users_balance( $user_id, $value);
			$total_usage++;
			$user_specific_usage++;
			update_post_meta($point_criteria_id,'total',$total_usage);
			update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
			$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points fore getting lowers marks %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
		}
	}
	function badges_earned($point_criteria_id,$id,$info=NULL,$user_id,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(is_array($info)){
			$count = count($info);
			$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
			if(is_numeric($value) && $count >= $module_score){
				$mycred = mycred();	
				$mycred->update_users_balance( $user_id, $value);
				$total_usage++;
				$user_specific_usage++;
				update_post_meta($point_criteria_id,'total',$total_usage);
				update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
				$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points for earning badges %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
			}
		}
	}
	function certificates_earned($point_criteria_id,$id,$info=NULL,$user_id,$total_usage,$user_specific_usage){
		$value = get_post_meta($point_criteria_id,'value',true);
		if(is_array($info)){
			$count = count($info);
			$module_score = get_post_meta($point_criteria_id,'wplms_module_score',true);
			if(is_numeric($value) && $count >= $module_score){
				$mycred = mycred();	
				$mycred->update_users_balance( $user_id, $value);
				$total_usage++;
				$user_specific_usage++;
				update_post_meta($point_criteria_id,'total',$total_usage);
				update_user_meta($user_id,$point_criteria_id,$user_specific_usage);
				$this->record(array(
				'user_id'=>$user_id,
				'id'=>$id,
				'amount' => $value,
				'module'=>$module,
				'log_entry'=> sprintf(__('Student %s gained %s points','wplms-mycred'),bp_core_get_userlink($user_id),$value),
				'message' => sprintf(__('Student %s gained %s points for earning certificates %d in %s for points criteria %s','wplms-mycred'),bp_core_get_userlink($user_id),$value,get_the_title($id),get_the_title($point_criteria_id))
				));
			}
		}
	}

	function record($args=array()){
		$defaults =array(
			'action' => 'mycred_add',
			'user_id'=>get_current_user_id(),
			'module'=>'course',
			'amount'=>0,
			'logentry'=>'Started Course',
			'id'=>0,
			'message'=>'Student Started course'
			);

		$r = wp_parse_args( $args, $defaults );
		extract( $r, EXTR_SKIP );

		$mycred = mycred();
		$mycred->add_to_log($action,
			$user_id,
			$amount,
			$logentry,
			$id,
			$message);

		$bp_args= array(
			'user_id' => $user_id,
			'action' => $action,
			'content' => $message,
			'primary_link' => get_permalink($id),
			'component' => $module,
			'item_id' => $id
		);
		bp_course_record_activity($bp_args);
	}
	function wplms_mycred_register_actions(){
		global $bp;
		$bp_course_action_desc=array(
			'mycred_add' => __( 'Add MyCred credits', 'vibe' ),
			);
		foreach($bp_course_action_desc as $key => $value){
			bp_activity_set_action($bp->activity->id,$key,$value);	
		}
	}
}

new points_awarding_system();

?>